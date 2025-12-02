<?php
/**
 * Handler that hydrates the shipment manifest view.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Shipment\Handler;

use Roanja\Module\RjMulticarrier\Domain\Shipment\Query\GetShipmentManifest;
use Roanja\Module\RjMulticarrier\Domain\Shipment\View\ShipmentManifestItemView;
use Roanja\Module\RjMulticarrier\Domain\Shipment\View\ShipmentManifestView;
use Roanja\Module\RjMulticarrier\Entity\Shipment;
use Roanja\Module\RjMulticarrier\Repository\ShipmentRepository;
use Roanja\Module\RjMulticarrier\Service\Shipment\ManifestCarrierLabelResolver;
use Roanja\Module\RjMulticarrier\Util\EmployeeFormatter;

final class GetShipmentManifestHandler
{
    public function __construct(
        private readonly ShipmentRepository $shipmentRepository,
        private readonly ManifestCarrierLabelResolver $labelResolver
    ) {
    }

    public function handle(GetShipmentManifest $query): ShipmentManifestView
    {
        $shipments = $this->shipmentRepository->findForManifest(
            $query->getDateFrom(),
            $query->getDateTo(),
            $query->getReferenceCarrierId()
        );

        $items = [];

        foreach ($shipments as $shipment) {
            if (!$shipment instanceof Shipment) {
                continue;
            }

            $info = $shipment->getInfoShipment();
            $type = $info->getTypeShipment();

            $items[] = new ShipmentManifestItemView(
                $shipment->getId() ?? 0,
                $shipment->getOrderId(),
                $shipment->getOrderReference(),
                $shipment->getShipmentNumber(),
                $shipment->getCarrier()?->getShortName(),
                $type?->getName(),
                $info->getQuantity(),
                (float) $info->getWeight(),
                $shipment->getCreatedAt()?->format('Y-m-d H:i'),
                EmployeeFormatter::resolveName($shipment->getEmployeeId()),
                $info->getReferenceCarrierId()
            );
        }

        $carrierLabel = $this->labelResolver->resolveLabel($query->getReferenceCarrierId());

        return new ShipmentManifestView(
            $query->getDateFrom(),
            $query->getDateTo(),
            $query->getReferenceCarrierId(),
            $carrierLabel,
            $items
        );
    }
}
