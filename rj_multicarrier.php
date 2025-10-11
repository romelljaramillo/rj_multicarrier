<?php

/**
 * Copyright (C) 2025 Roanja
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 *
 *  @author    Roanja
 *  @license   Academic Free License (AFL 3.0)
 */

declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

if (!defined('RJ_MULTICARRIER_VAR_DIR')) {
    define('RJ_MULTICARRIER_VAR_DIR', _PS_MODULE_DIR_ . 'rj_multicarrier/var/');
}

if (!defined('IMG_ICON_COMPANY_DIR')) {
    define('IMG_ICON_COMPANY_DIR', RJ_MULTICARRIER_VAR_DIR . 'icons/');
}

if (!defined('RJ_MULTICARRIER_LABEL_DIR')) {
    define('RJ_MULTICARRIER_LABEL_DIR', _PS_MODULE_DIR_ . 'rj_multicarrier/labels/');
}

use PrestaShop\PrestaShop\Core\Module\WidgetInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Roanja\Module\RjMulticarrier\Service\Installer\ModuleInstaller;
use PrestaShop\PrestaShop\Adapter\SymfonyContainer;
use Twig\Environment;

class Rj_Multicarrier extends Module implements WidgetInterface
{
    private const HOOKS = [
        'displayBackOfficeHeader',
        'displayAdminOrder',
        'displayBeforeCarrier',
        'displayAfterCarrier',
        'actionAdminControllerSetMedia',
        'displayProductAdditionalInfo',
    ];

    public function __construct()
    {
        $this->name = 'rj_multicarrier';
        $this->tab = 'administration';
        $this->version = '3.0.0';
        $this->author = 'Roanja';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Roanja Multi-carrier');
        $this->description = $this->l('Modern multi-carrier integration for PrestaShop 8 powered by Symfony.');
        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => _PS_VERSION_];
    }

    public function isUsingNewTranslationSystem(): bool
    {
        return true;
    }

    public function install(): bool
    {
        if (!parent::install()) {
            return false;
        }

        if (!$this->registerModuleHooks()) {
            return false;
        }

        return $this->getModuleInstaller()->install();
    }

    private function getModuleInstaller(): ModuleInstaller
    {
        try {
            return $this->get('roanja.module.rj_multicarrier.installer');
        } catch (\Throwable $exception) {
            return new ModuleInstaller();
        }
    }

    public function uninstall(): bool
    {
        $result = $this->getModuleInstaller()->uninstall() && parent::uninstall();

        return $result;
    }

    public function getContent()
    {
        try {
            // Obtener contenedor Symfony global
            $sfContainer = SymfonyContainer::getInstance();
            /** @var Symfony\Component\Routing\Generator\UrlGeneratorInterface $router */
            $router = $sfContainer->get('router');

            // Redirigir a la ruta Symfony del módulo
            Tools::redirectAdmin(
                $router->generate('admin_rj_multicarrier_configuration')
            );

            return '';
        } catch (\Throwable $e) {
            return $this->trans(
                'No se pudo generar la URL de configuración de Symfony: %error%',
                ['%error%' => $e->getMessage()],
                'Modules.RjMulticarrier.Admin'
            );
        }
    }

    public function hookDisplayBackOfficeHeader(): void
    {
        $this->context->controller->addCSS($this->_path . 'views/css/admin.css');
        $this->context->controller->addJS($this->_path . 'views/js/admin.js');
    }

    public function hookActionAdminControllerSetMedia(): void
    {
        $this->hookDisplayBackOfficeHeader();
    }

    public function hookDisplayAdminOrder(array $params)
    {
        $idOrder = (int) ($params['id_order'] ?? 0);

        if (0 === $idOrder) {
            return '';
        }

        try {
            $presenter = $this->get('roanja.module.rj_multicarrier.presenter.order_view');

            return $presenter->present($idOrder);
        } catch (\Throwable $e) {
            return '';
        }
    }

    public function hookDisplayBeforeCarrier(array $params)
    {
        return $this->renderWidget('displayBeforeCarrier', $params);
    }

    public function hookDisplayAfterCarrier(array $params)
    {
        return $this->renderWidget('displayAfterCarrier', $params);
    }

    public function renderWidget($hookName, array $configuration)
    {
        return $this->get('roanja.module.rj_multicarrier.widget.presenter')->render($hookName, $configuration);
    }

    public function getWidgetVariables($hookName, array $configuration)
    {
        return $this->get('roanja.module.rj_multicarrier.widget.presenter')->getVariables($hookName, $configuration);
    }

    private function getSymfonyConfigureUrl(): ?string
    {
        try {
            /** @var Symfony\Component\Routing\Generator\UrlGeneratorInterface $router */
            $router = $this->get('router');

            if (!$this->isRouteRegistered($router, 'admin_rj_multicarrier_configuration')) {
                $this->clearRoutingCache();

                return null;
            }

            return $router->generate('admin_rj_multicarrier_configuration', [], UrlGeneratorInterface::ABSOLUTE_URL);
        } catch (\Throwable $exception) {
            $this->clearRoutingCache();

            return null;
        }
    }

    private function isRouteRegistered($router, string $routeName): bool
    {
        if (!is_object($router) || !method_exists($router, 'getRouteCollection')) {
            return true;
        }

        $routeCollection = $router->getRouteCollection();

        if (null === $routeCollection) {
            return false;
        }

        return null !== $routeCollection->get($routeName);
    }

    private function clearRoutingCache(): void
    {
        if (class_exists('\\Tools')) {
            \Tools::clearSf2Cache();
        }
    }

    private function registerModuleHooks(): bool
    {
        foreach (self::HOOKS as $hook) {
            if (!$this->registerHook($hook)) {
                return false;
            }
        }

        return true;
    }

    private function renderErrorTemplate(string $message): string
    {
        try {
            /** @var Environment $twig */
            $twig = $this->get('twig');
            return $twig->render('@Modules/rj_multicarrier/views/templates/admin/configuration/error.html.twig', [
                'message' => $message,
            ]);
        } catch (Exception $exception) {
            return sprintf('<div class="alert alert-danger">%s</div>', Tools::safeOutput($message));
        }
    }
}
