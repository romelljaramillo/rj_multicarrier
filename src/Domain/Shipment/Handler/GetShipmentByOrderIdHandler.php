<?php
/**
 * Handler fetching shipment data via Doctrine repositories.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Shipment\Handler;

use Roanja\Module\RjMulticarrier\Domain\Shipment\Query\GetShipmentByOrderId;
use Roanja\Module\RjMulticarrier\Domain\Shipment\View\ShipmentView;
use Roanja\Module\RjMulticarrier\Repository\InfoPackageRepository;
use Roanja\Module\RjMulticarrier\Repository\LabelRepository;
use Roanja\Module\RjMulticarrier\Repository\ShipmentRepository;

final class GetShipmentByOrderIdHandler
{
    public function __construct(
        private readonly ShipmentRepository $shipmentRepository,
        private readonly InfoPackageRepository $infoPackageRepository,
        private readonly LabelRepository $labelRepository
    ) {
    }

    public function handle(GetShipmentByOrderId $query): ?ShipmentView
    {
        $shipment = $this->shipmentRepository->findOneByOrderId($query->getOrderId());

        if (!$shipment) {
            return null;
        }

        $package = [];
        $metadata = [
            'account' => $shipment->getAccount(),
            'product' => $shipment->getProduct(),
            'requestPayload' => $shipment->getRequestPayload(),
            'responsePayload' => $shipment->getResponsePayload(),
            'deleted' => $shipment->isDeleted(),
        ];
        $infoPackageId = $this->shipmentRepository->getInfoPackageIdByOrderId($query->getOrderId());

        if ($infoPackageId !== null) {
            $infoPackage = $this->infoPackageRepository->find($infoPackageId);
            if ($infoPackage !== null) {
                $hourFrom = $infoPackage->getHourFrom()?->format('H:i:s');
                $hourUntil = $infoPackage->getHourUntil()?->format('H:i:s');

                $retorno = $infoPackage->getRetorno();

                $vsecRaw = $infoPackage->getVsec();
                $vsec = null;
                if (null !== $vsecRaw) {
                    $vsecFloat = (float) $vsecRaw;
                    if ($vsecFloat > 0.0) {
                        $vsec = $vsecFloat;
                    }
                }

                $dorigRaw = $infoPackage->getDorig();
                $dorig = ($dorigRaw !== null && $dorigRaw !== '') ? $dorigRaw : null;

                $package = [
                    'id' => $infoPackage->getId(),
                    'id_reference_carrier' => $infoPackage->getReferenceCarrierId(),
                    'id_type_shipment' => $infoPackage->getTypeShipment()?->getId(),
                    'id_carrier' => $infoPackage->getTypeShipment()?->getCarrier()->getId(),
                    'quantity' => $infoPackage->getQuantity(),
                    'weight' => $infoPackage->getWeight(),
                    // Legacy template expects key "cash_ondelivery" (without second underscore)
                    'cash_ondelivery' => $infoPackage->getCashOnDelivery(),
                    'length' => $infoPackage->getLength(),
                    'width' => $infoPackage->getWidth(),
                    'height' => $infoPackage->getHeight(),
                    'message' => $infoPackage->getMessage(),
                    'hour_from' => $hourFrom,
                    'hour_until' => $hourUntil,
                    'retorno' => $retorno,
                    'rcs' => $infoPackage->isRcsEnabled(),
                    'vsec' => $vsec,
                    'dorig' => $dorig,
                ];
            }
        }

        $labels = $this->labelRepository->findBy(['shipment' => $shipment]);

        return ShipmentView::fromEntities(
            $shipment,
            $labels,
            $package,
            $metadata
        );
    }
}
