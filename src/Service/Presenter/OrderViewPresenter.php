<?php
/**
 * Provides data and rendering helpers for the admin order view integration.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Service\Presenter;

use PrestaShop\PrestaShop\Core\CommandBus\CommandBusInterface;
use Roanja\Module\RjMulticarrier\Domain\CompanyConfiguration\Query\GetCompanyConfigurationsForCompany;
use Roanja\Module\RjMulticarrier\Domain\CompanyConfiguration\View\CompanyConfigurationView;
use Roanja\Module\RjMulticarrier\Domain\Shipment\Handler\GetShipmentByOrderIdHandler;
use Roanja\Module\RjMulticarrier\Domain\Shipment\Query\GetShipmentByOrderId;
use Roanja\Module\RjMulticarrier\Domain\TypeShipmentConfiguration\Query\GetTypeShipmentConfigurations;
use Roanja\Module\RjMulticarrier\Domain\TypeShipmentConfiguration\View\TypeShipmentConfigurationView;
use Roanja\Module\RjMulticarrier\Repository\InfoPackageRepository;
use Roanja\Module\RjMulticarrier\Repository\CompanyRepository;
use Roanja\Module\RjMulticarrier\Repository\TypeShipmentRepository;
use Roanja\Module\RjMulticarrier\Domain\Shipment\View\ShipmentView;
use Twig\Environment;

final class OrderViewPresenter
{
    public function __construct(
        private readonly Environment $twig,
        private readonly GetShipmentByOrderIdHandler $getShipmentByOrderIdHandler,
        private readonly ?InfoPackageRepository $infoPackageRepository = null,
        private readonly ?CompanyRepository $companyRepository = null,
        private readonly ?TypeShipmentRepository $typeShipmentRepository = null,
        private readonly ?CommandBusInterface $queryBus = null,
    ) {
    }

    public function present(int $orderId): string
    {
        if (0 === $orderId) {
            return '';
        }

        $shipmentView = $this->getShipmentByOrderIdHandler->handle(new GetShipmentByOrderId($orderId));

        // Prepare legacy-compatible variables so the Twig template can render a similar form
        $infoPackage = [];
        $infoShipment = null;
        $labels = [];
        $carriers = [];
        $carrierName = '';

        // Normalize shipment to an array so Twig deals with consistent structure.
        $shipmentArray = null;
        if ($shipmentView instanceof ShipmentView) {
            $shipmentArray = $shipmentView->toArray();
        } elseif (is_array($shipmentView)) {
            $shipmentArray = $shipmentView;
        }

        if ($shipmentArray instanceof ShipmentView) {
            $shipmentArray = $shipmentArray->toArray();
        }

        if (is_array($shipmentArray)) {
            $infoPackage = $shipmentArray['package'] ?? [];
            $labels = $shipmentArray['labels'] ?? [];
            $carrierName = $shipmentArray['carrierShortName'] ?? '';
            $infoShipment = ['id_shipment' => $shipmentArray['id'] ?? null];
        }

        // Modern presenter: do not rely on legacy Context/Carrier classes.
        $carriers = [];
        $urlAjax = '';

        // Load companies and default type shipments for the form (modern repositories)
        $companies = [];
        $typeShipments = [];
        try {
            if ($this->companyRepository instanceof CompanyRepository) {
                $companies = $this->companyRepository->findAllOrdered();
            }
        } catch (\Throwable $e) {
            $companies = [];
        }

        try {
            if ($this->typeShipmentRepository instanceof TypeShipmentRepository) {
                // if shipment exists, prefer to load types for its company; otherwise load all active types
                if (isset($shipmentArray['companyId']) && $shipmentArray['companyId']) {
                    // Attempt to load company entity and then its types
                    $companyEntity = null;
                    foreach ($companies as $c) {
                        if (method_exists($c, 'getId') && $c->getId() === ($shipmentArray['companyId'] ?? null)) {
                            $companyEntity = $c;
                            break;
                        }
                    }

                    if ($companyEntity !== null) {
                        $typeShipments = $this->typeShipmentRepository->findByCompany($companyEntity, true);
                    }
                }

                if (empty($typeShipments)) {
                    // Fallback: collect active types grouped by company (we'll pass raw objects to Twig)
                    // We simply leave $typeShipments empty and let controllers/grids handle detailed selection.
                    $typeShipments = [];
                }
            }
        } catch (\Throwable $e) {
            $typeShipments = [];
        }

        $configurations = $this->buildConfigurationPayload($shipmentArray);

        return $this->twig->render('@Modules/rj_multicarrier/views/templates/admin/order/panel.html.twig', [
            'orderId' => $orderId,
            'shipment' => $shipmentArray,
            'info_package' => $infoPackage,
            'info_shipment' => $infoShipment,
            'labels' => $labels,
            'carriers' => $carriers,
            'carrier_name' => $carrierName,
            'id_order' => $orderId,
            'url_ajax' => $urlAjax,
            'config_extra_info' => $configurations,
            'info_customer' => [],
            'info_shop' => [],
            // modern lists for form rendering when no shipment exists
            'companies' => $companies,
            'type_shipments' => $typeShipments,
        ]);
    }

    /**
     * @param array<string, mixed>|null $shipment
     *
     * @return array<string, array<string, string|null>>
     */
    private function buildConfigurationPayload(?array $shipment): array
    {
        if (null === $this->queryBus) {
            return [
                'company' => [],
                'type_shipment' => [],
            ];
        }

        $companyId = (int) ($shipment['companyId'] ?? 0);
        $typeShipmentId = (int) ($shipment['typeShipmentId'] ?? $shipment['id_type_shipment'] ?? 0);

        $companyConfig = [];
        $typeConfig = [];

        if ($companyId > 0) {
            try {
                /** @var CompanyConfigurationView[] $views */
                $views = $this->queryBus->handle(new GetCompanyConfigurationsForCompany($companyId));
                foreach ($views as $view) {
                    $companyConfig[$view->getName()] = $view->getValue();
                }
            } catch (\Throwable) {
                $companyConfig = [];
            }
        }

        if ($typeShipmentId > 0) {
            try {
                /** @var TypeShipmentConfigurationView[] $views */
                $views = $this->queryBus->handle(new GetTypeShipmentConfigurations($typeShipmentId));
                foreach ($views as $view) {
                    $typeConfig[$view->getName()] = $view->getValue();
                }
            } catch (\Throwable) {
                $typeConfig = [];
            }
        }

        return [
            'company' => $companyConfig,
            'type_shipment' => $typeConfig,
        ];
    }
}
