<?php
/**
 * Provides data and rendering helpers for the admin order view integration.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Service\Presenter;

use Roanja\Module\RjMulticarrier\Domain\Shipment\Handler\GetShipmentByOrderIdHandler;
use Roanja\Module\RjMulticarrier\Domain\Shipment\Query\GetShipmentByOrderId;
use Roanja\Module\RjMulticarrier\Repository\InfoPackageRepository;
use Roanja\Module\RjMulticarrier\Repository\CompanyRepository;
use Roanja\Module\RjMulticarrier\Repository\TypeShipmentRepository;
use Twig\Environment;

final class OrderViewPresenter
{
    public function __construct(
        private readonly Environment $twig,
        private readonly GetShipmentByOrderIdHandler $getShipmentByOrderIdHandler,
        private readonly ?InfoPackageRepository $infoPackageRepository = null,
        private readonly ?CompanyRepository $companyRepository = null,
        private readonly ?TypeShipmentRepository $typeShipmentRepository = null,
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
        if ($shipmentView instanceof \Roanja\Module\RjMulticarrier\Domain\Shipment\View\ShipmentView) {
            $shipmentArray = $shipmentView->toArray();
        } elseif (is_array($shipmentView)) {
            $shipmentArray = $shipmentView;
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
            'config_extra_info' => [],
            'info_customer' => [],
            'info_shop' => [],
            // modern lists for form rendering when no shipment exists
            'companies' => $companies,
            'type_shipments' => $typeShipments,
        ]);
    }
}
