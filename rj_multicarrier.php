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

use PrestaShop\PrestaShop\Adapter\SymfonyContainer;
use PrestaShop\PrestaShop\Core\Module\WidgetInterface;
use Roanja\Module\RjMulticarrier\Carrier\CarrierCompany;
use Roanja\Module\RjMulticarrier\Domain\InfoPackage\Command\UpsertInfoPackageCommand;
use Roanja\Module\RjMulticarrier\Domain\InfoPackage\Handler\UpsertInfoPackageHandler;
use Roanja\Module\RjMulticarrier\Domain\Shipment\Command\DeleteShipmentCommand;
use Roanja\Module\RjMulticarrier\Domain\Shipment\Exception\ShipmentGenerationException;
use Roanja\Module\RjMulticarrier\Domain\Shipment\Handler\DeleteShipmentHandler;
use Roanja\Module\RjMulticarrier\Repository\InfoPackageRepository;
use Roanja\Module\RjMulticarrier\Repository\ShipmentRepository;
use Roanja\Module\RjMulticarrier\Repository\TypeShipmentRepository;
use Roanja\Module\RjMulticarrier\Service\Installer\ModuleInstaller;
use Roanja\Module\RjMulticarrier\Service\Presenter\OrderViewPresenter;
use Roanja\Module\RjMulticarrier\Service\Shipment\ShipmentGenerationService;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
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

        $actionContext = $this->handleAdminOrderActions($idOrder);

        try {
            /** @var OrderViewPresenter $presenter */
            $presenter = $this->get('roanja.module.rj_multicarrier.presenter.order_view');

            return $presenter->present($idOrder, $actionContext);
        } catch (\Throwable $e) {
            if (_PS_MODE_DEV_) {
                return sprintf(
                    '<div class="alert alert-danger">%s</div>',
                    Tools::safeOutput($e->getMessage())
                );
            }

            return '<div class="alert alert-danger">RJ Multicarrier: unable to render order panel.</div>';
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
        try {
            return $this->get('roanja.module.rj_multicarrier.widget.presenter')->render($hookName, $configuration);
        } catch (\Throwable $exception) {
            if (_PS_MODE_DEV_) {
                return sprintf(
                    '<div class="alert alert-danger">%s</div>',
                    Tools::safeOutput($exception->getMessage())
                );
            }

            return '';
        }
    }

    public function getWidgetVariables($hookName, array $configuration)
    {
        try {
            return $this->get('roanja.module.rj_multicarrier.widget.presenter')->getVariables($hookName, $configuration);
        } catch (\Throwable $exception) {
            if (_PS_MODE_DEV_) {
                return [
                    'errors' => [$exception->getMessage()],
                ];
            }

            return [];
        }
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

    /**
     * Process legacy-style form actions triggered on the admin order view.
     *
     * @return array<string, mixed>
     */
    private function handleAdminOrderActions(int $orderId): array
    {
        $messages = [
            'success' => [],
            'errors' => [],
            'warning' => [],
            'info' => [],
        ];

        $lastPackage = null;
        $context = \Context::getContext();
        $shopId = (int) ($context->shop->id ?? 0);
        $submittedValues = Tools::getAllValues();

        if (Tools::isSubmit('submitDeleteShipment')) {
            $shipmentId = (int) Tools::getValue('id_shipment');

            if ($shipmentId > 0) {
                try {
                    /** @var DeleteShipmentHandler $deleteShipmentHandler */
                    $deleteShipmentHandler = $this->get(DeleteShipmentHandler::class);
                    $deleteShipmentHandler->handle(new DeleteShipmentCommand($shipmentId));
                    $messages['success'][] = $this->trans('Shipment deleted successfully.', [], 'Modules.RjMulticarrier.Admin');
                } catch (\Throwable $exception) {
                    $messages['errors'][] = $this->trans(
                        'Unable to delete shipment: %message%',
                        ['%message%' => $exception->getMessage()],
                        'Modules.RjMulticarrier.Admin'
                    );
                }
            } else {
                $messages['errors'][] = $this->trans('Invalid shipment identifier.', [], 'Modules.RjMulticarrier.Admin');
            }
        }

        $needsPackageSave = Tools::isSubmit('submitFormPackCarrier')
            || Tools::isSubmit('submitSavePackSend')
            || Tools::isSubmit('submitShipment');

        if ($needsPackageSave) {
            try {
                /** @var TypeShipmentRepository $typeShipmentRepository */
                $typeShipmentRepository = $this->get(TypeShipmentRepository::class);
                /** @var InfoPackageRepository $infoPackageRepository */
                $infoPackageRepository = $this->get(InfoPackageRepository::class);
                /** @var ShipmentRepository $shipmentRepository */
                $shipmentRepository = $this->get(ShipmentRepository::class);

                $lastPackage = $this->persistInfoPackageFromRequest(
                    $orderId,
                    $typeShipmentRepository,
                    $infoPackageRepository,
                    $shipmentRepository,
                    $messages,
                    $shopId
                );

                if ($lastPackage) {
                    $messages['success'][] = $this->trans('Package information saved.', [], 'Modules.RjMulticarrier.Admin');
                }
            } catch (\Throwable $exception) {
                $messages['errors'][] = $this->trans(
                    'Unable to save package information: %message%',
                    ['%message%' => $exception->getMessage()],
                    'Modules.RjMulticarrier.Admin'
                );
            }
        }

        if (Tools::isSubmit('submitShipment') && empty($messages['errors'])) {
            $infoPackageId = isset($lastPackage['id_infopackage']) ? (int) $lastPackage['id_infopackage'] : 0;

            if ($infoPackageId <= 0) {
                try {
                    /** @var InfoPackageRepository $infoPackageRepository */
                    $infoPackageRepository = $this->get(InfoPackageRepository::class);
                    $packageRow = $infoPackageRepository->getPackageByOrder($orderId, $shopId);
                    $infoPackageId = (int) ($packageRow['id_infopackage'] ?? 0);
                } catch (\Throwable $exception) {
                    $messages['errors'][] = $exception->getMessage();
                }
            }

            if ($infoPackageId > 0 && empty($messages['errors'])) {
                $this->generateShipmentForInfoPackage($infoPackageId, $messages);
            } elseif ($infoPackageId <= 0) {
                $messages['errors'][] = $this->trans(
                    'No package information available to generate the shipment.',
                    [],
                    'Modules.RjMulticarrier.Admin'
                );
            }
        }

        return [
            'notifications' => $messages,
            'package' => $lastPackage,
            'submitted' => $submittedValues,
        ];
    }

    /**
     * Persist info package data coming from the admin order view.
     *
     * @return array<string, mixed>|null
     */
    private function persistInfoPackageFromRequest(
        int $orderId,
        TypeShipmentRepository $typeShipmentRepository,
        InfoPackageRepository $infoPackageRepository,
        ShipmentRepository $shipmentRepository,
        array &$messages,
        int $shopId
    ): ?array {
        $typeShipmentId = (int) Tools::getValue('id_type_shipment');

        if ($typeShipmentId <= 0) {
            throw new \RuntimeException($this->trans('Please select a shipment type.', [], 'Modules.RjMulticarrier.Admin'));
        }

        $typeShipment = $typeShipmentRepository->findOneById($typeShipmentId);

        if (!$typeShipment instanceof \Roanja\Module\RjMulticarrier\Entity\TypeShipment) {
            throw new \RuntimeException($this->trans('The selected shipment type does not exist.', [], 'Modules.RjMulticarrier.Admin'));
        }

        $referenceCarrierId = (int) ($typeShipment->getReferenceCarrierId() ?? 0);

        if ($referenceCarrierId <= 0) {
            $referenceCarrierId = (int) Tools::getValue('id_reference_carrier');
        }

        if ($referenceCarrierId <= 0) {
            throw new \RuntimeException($this->trans('The selected shipment type is not linked to a carrier reference.', [], 'Modules.RjMulticarrier.Admin'));
        }

        $infoPackageIdRaw = Tools::getValue('id_infopackage');
        $infoPackageId = ($infoPackageIdRaw !== null && '' !== trim((string) $infoPackageIdRaw))
            ? (int) $infoPackageIdRaw
            : null;

        $quantity = max(1, (int) Tools::getValue('rj_quantity', 1));
        $weight = (float) Tools::getValue('rj_weight', 0);
        $length = $this->toNullableFloat(Tools::getValue('rj_length'));
        $width = $this->toNullableFloat(Tools::getValue('rj_width'));
        $height = $this->toNullableFloat(Tools::getValue('rj_height'));
        $cashOnDelivery = $this->normalizeStringValue(Tools::getValue('rj_cash_ondelivery'));
        $message = $this->normalizeStringValue(Tools::getValue('rj_message'));
        $hourFrom = $this->normalizeTimeValue(Tools::getValue('rj_hour_from'));
        $hourUntil = $this->normalizeTimeValue(Tools::getValue('rj_hour_until'));
        $retornoRaw = Tools::getValue('rj_retorno');
        $retorno = ($retornoRaw === null || $retornoRaw === '') ? null : (int) $retornoRaw;
        $rcs = (bool) Tools::getValue('rj_rcs');
        $vsec = $this->normalizeStringValue(Tools::getValue('rj_vsec'));
        $dorig = $this->normalizeStringValue(Tools::getValue('rj_dorig'));

        /** @var UpsertInfoPackageHandler $handler */
        $handler = $this->get(UpsertInfoPackageHandler::class);
        $handler->handle(
            new UpsertInfoPackageCommand(
                $infoPackageId,
                $orderId,
                $referenceCarrierId,
                $typeShipmentId,
                $quantity,
                $weight,
                $length,
                $width,
                $height,
                $cashOnDelivery,
                $message,
                $hourFrom,
                $hourUntil,
                $retorno,
                $rcs,
                $vsec,
                $dorig,
                $shopId
            )
        );

        $package = $infoPackageRepository->getPackageByOrder($orderId, $shopId);

        if (!$package) {
            return null;
        }

        $newCarrierId = $typeShipment->getCarrier()->getId();
        $currentShipment = $shipmentRepository->findOneByOrderId($orderId);

        if ($currentShipment && $currentShipment->getCarrier() && $currentShipment->getCarrier()->getId() !== $newCarrierId) {
            try {
                /** @var DeleteShipmentHandler $deleteShipmentHandler */
                $deleteShipmentHandler = $this->get(DeleteShipmentHandler::class);
                $deleteShipmentHandler->handle(new DeleteShipmentCommand((int) $currentShipment->getId()));
                $messages['info'][] = $this->trans(
                    'Existing shipment removed because the carrier changed.',
                    [],
                    'Modules.RjMulticarrier.Admin'
                );
            } catch (\Throwable $exception) {
                $messages['errors'][] = $this->trans(
                    'Unable to remove the previous shipment: %message%',
                    ['%message%' => $exception->getMessage()],
                    'Modules.RjMulticarrier.Admin'
                );
            }
        }

        return $package;
    }

    private function generateShipmentForInfoPackage(int $infoPackageId, array &$messages): void
    {
        try {
            /** @var ShipmentGenerationService $generationService */
            $generationService = $this->get(ShipmentGenerationService::class);
            $generationService->generateForInfoPackage($infoPackageId);
            $messages['success'][] = $this->trans('Shipment generated successfully.', [], 'Modules.RjMulticarrier.Admin');
        } catch (ShipmentGenerationException $exception) {
            $messages['errors'][] = $exception->getMessage();
        } catch (\Throwable $exception) {
            $messages['errors'][] = $this->trans(
                'Unexpected error while generating the shipment: %message%',
                ['%message%' => $exception->getMessage()],
                'Modules.RjMulticarrier.Admin'
            );
        }
    }

    private function normalizeTimeValue($value): ?string
    {
        if ($value === null || '' === trim((string) $value)) {
            return null;
        }

        $time = trim((string) $value);

        if (preg_match('/^\d{2}:\d{2}$/', $time)) {
            $time .= ':00';
        }

        if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
            return null;
        }

        return CarrierCompany::validateFormatTime($time) ? $time : null;
    }

    private function toNullableFloat($value): ?float
    {
        if ($value === null || $value === '' || $value === false) {
            return null;
        }

        return (float) $value;
    }

    private function normalizeStringValue($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return '' === $trimmed ? null : $trimmed;
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
