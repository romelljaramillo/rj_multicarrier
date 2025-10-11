<?php
/**
 * Handler returning a shipment view for presentation layers.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Shipment\Handler;

use Roanja\Module\RjMulticarrier\Domain\Shipment\Query\GetShipmentForView;
use Roanja\Module\RjMulticarrier\Domain\Shipment\View\ShipmentView;
use Roanja\Module\RjMulticarrier\Entity\InfoPackage;
use Roanja\Module\RjMulticarrier\Repository\LabelRepository;
use Roanja\Module\RjMulticarrier\Repository\ShipmentRepository;

final class GetShipmentForViewHandler
{
    public function __construct(
    private readonly ShipmentRepository $shipmentRepository,
    private readonly LabelRepository $labelRepository
    ) {
    }

    public function handle(GetShipmentForView $query): ?ShipmentView
    {
        $shipment = $this->shipmentRepository->find($query->getShipmentId());

        if (!$shipment) {
            return null;
        }

        $package = $this->buildPackageView($shipment->getInfoPackage());

        $labels = $this->labelRepository->findBy(['shipment' => $shipment]);

        $metadata = [
            'account' => $shipment->getAccount(),
            'product' => $shipment->getProduct(),
            'requestPayload' => $shipment->getRequestPayload(),
            'responsePayload' => $shipment->getResponsePayload(),
            'deleted' => $shipment->isDeleted(),
        ];

        return ShipmentView::fromEntities(
            $shipment,
            $labels,
            $package,
            $metadata
        );
    }

        private function buildPackageView(?InfoPackage $infoPackage): array
        {
            if (!$infoPackage) {
                return [];
            }

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

            return [
                'id' => $infoPackage->getId(),
                'quantity' => $infoPackage->getQuantity(),
                'weight' => $infoPackage->getWeight(),
                'cash_on_delivery' => $infoPackage->getCashOnDelivery(),
                'dimensions' => [
                    'length' => $infoPackage->getLength(),
                    'width' => $infoPackage->getWidth(),
                    'height' => $infoPackage->getHeight(),
                ],
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
