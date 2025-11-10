<?php
/**
 * Handler returning info package view objects for detail modals.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\InfoShipment\Handler;

use Roanja\Module\RjMulticarrier\Domain\InfoShipment\Query\GetInfoShipmentForView;
use Roanja\Module\RjMulticarrier\Domain\InfoShipment\View\InfoShipmentView;
use Roanja\Module\RjMulticarrier\Repository\InfoShipmentRepository;

final class GetInfoShipmentForViewHandler
{
    public function __construct(private readonly InfoShipmentRepository $infoShipmentRepository)
    {
    }

    public function handle(GetInfoShipmentForView $query): ?InfoShipmentView
    {
        $infoShipment = $this->infoShipmentRepository->find($query->getInfoShipmentId());

        if (!$infoShipment) {
            return null;
        }

        return InfoShipmentView::fromEntity($infoShipment);
    }
}
