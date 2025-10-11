<?php
/**
 * Symfony controller for the module configuration experience.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Controller\Admin;

use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Roanja\Module\RjMulticarrier\Form\ExtraConfigType;
use Roanja\Module\RjMulticarrier\Form\InfoShopType;
use Roanja\Module\RjMulticarrier\Service\Configuration\ConfigurationManager;
use Roanja\Module\RjMulticarrier\Grid\Company\CompanyGridFactory;
use Roanja\Module\RjMulticarrier\Grid\TypeShipment\TypeShipmentGridFactory;
use Roanja\Module\RjMulticarrier\Grid\Company\CompanyFilters;
use Roanja\Module\RjMulticarrier\Grid\TypeShipment\TypeShipmentFilters;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

final class ConfigurationController extends FrameworkBundleAdminController
{
    private const TRANSLATION_DOMAIN = 'Modules.RjMulticarrier.Admin';

    public function __construct(
        private readonly ConfigurationManager $configurationManager,
        private readonly TranslatorInterface $translator
    ) {
    }

    public function indexAction(Request $request): Response
    {
        // Tabs removed: configuration view now uses a single unified layout without BO tabs
        $infoShopDefaults = $this->configurationManager->getInfoShopDefaults();
        $extraConfigDefaults = $this->configurationManager->getExtraConfigDefaults();
        $currentModule = isset($extraConfigDefaults['RJ_MODULE_CONTRAREEMBOLSO']) ? (string) $extraConfigDefaults['RJ_MODULE_CONTRAREEMBOLSO'] : null;

        $moduleChoices = $this->configurationManager->getCashOnDeliveryModuleChoices($currentModule);
        if (null !== $currentModule && '' !== $currentModule && !in_array($currentModule, $moduleChoices, true)) {
            $label = $this->translate('Módulo inactivo: %module%', ['%module%' => $currentModule]);
            $moduleChoices = [$label => $currentModule] + $moduleChoices;
        }

        $infoShopForm = $this->createForm(InfoShopType::class, $infoShopDefaults, [
            'country_choices' => $this->configurationManager->getCountryChoices(),
        ]);
        $extraConfigForm = $this->createForm(ExtraConfigType::class, $extraConfigDefaults, [
            'module_choices' => $moduleChoices,
        ]);

        $infoShopForm->handleRequest($request);
        if ($infoShopForm->isSubmitted() && $infoShopForm->isValid()) {
            $response = $this->handleInfoShopSubmit($infoShopForm->getData());
            if ($response instanceof RedirectResponse) {
                return $response;
            }
        }

        $extraConfigForm->handleRequest($request);
        if ($extraConfigForm->isSubmitted() && $extraConfigForm->isValid()) {
            $response = $this->handleExtraConfigSubmit($extraConfigForm->getData());
            if ($response instanceof RedirectResponse) {
                return $response;
            }
        }

        // Attempt to build company and type shipment grids if the factories are available
        $companyGridRendered = null;
        $typeShipmentGridRendered = null;

        try {
            $container = $this->get('service_container');

            if ($container->has(CompanyGridFactory::class)) {
                /** @var CompanyGridFactory $companyGridFactory */
                $companyGridFactory = $container->get(CompanyGridFactory::class);
                $companyFilters = CompanyFilters::getDefaults();
                $companyGrid = $companyGridFactory->getGrid(new CompanyFilters($companyFilters));
                $companyGridRendered = $this->presentGrid($companyGrid);
            }

            if ($container->has(TypeShipmentGridFactory::class)) {
                /** @var TypeShipmentGridFactory $typeShipmentGridFactory */
                $typeShipmentGridFactory = $container->get(TypeShipmentGridFactory::class);
                $typeShipmentFilters = TypeShipmentFilters::getDefaults();
                $typeGrid = $typeShipmentGridFactory->getGrid(new TypeShipmentFilters($typeShipmentFilters));
                $typeShipmentGridRendered = $this->presentGrid($typeGrid);
            }
        } catch (\Throwable $e) {
            // Non-fatal: grids are optional in configuration view
        }

        return $this->render('@Modules/rj_multicarrier/views/templates/admin/configuration/index.html.twig', [
            'infoShopForm' => $infoShopForm->createView(),
            'extraConfigForm' => $extraConfigForm->createView(),
            'companyGrid' => $companyGridRendered,
            'typeShipmentGrid' => $typeShipmentGridRendered,
        ]);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function handleInfoShopSubmit(array $data): RedirectResponse
    {
        try {
            $this->configurationManager->saveInfoShop($data);
            $this->addFlash('success', $this->translate('Datos del remitente guardados correctamente.'));
        } catch (Throwable $exception) {
            $this->addFlash('error', $this->translate('No se pudieron guardar los datos del remitente: %error%', [
                '%error%' => $exception->getMessage(),
            ]));
        }

        return $this->redirectToConfiguration();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function handleExtraConfigSubmit(array $data): RedirectResponse
    {
        try {
            $this->configurationManager->saveExtraConfig($data);
            $this->addFlash('success', $this->translate('Configuración adicional guardada.'));
        } catch (Throwable $exception) {
            $this->addFlash('error', $this->translate('No se pudo guardar la configuración adicional: %error%', [
                '%error%' => $exception->getMessage(),
            ]));
        }

        return $this->redirectToConfiguration();
    }
    private function redirectToConfiguration(): RedirectResponse
    {
        return $this->redirect($this->generateConfigurationUrl());
    }

    private function generateConfigurationUrl(): string
    {
        try {
            return $this->generateUrl('admin_rj_multicarrier_configuration');
        } catch (RouteNotFoundException $exception) {
            return $this->configurationManager->getLegacyConfigurationUrl();
        }
    }

    private function translate(string $id, array $parameters = []): string
    {
        return $this->translator->trans($id, $parameters, self::TRANSLATION_DOMAIN);
    }
}
