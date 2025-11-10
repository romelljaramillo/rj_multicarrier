<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\InfoShipment\Handler;

use Roanja\Module\RjMulticarrier\Domain\InfoShipment\Query\GetInfoShipmentsByIds;
use Roanja\Module\RjMulticarrier\Domain\InfoShipment\View\InfoShipmentView;
use Roanja\Module\RjMulticarrier\Repository\InfoShipmentRepository;

final class GetInfoShipmentsByIdsHandler
{
    public function __construct(private readonly InfoShipmentRepository $infoShipmentRepository)
    {
    }

    /**
     * @return InfoShipmentView[]
     */
    public function handle(GetInfoShipmentsByIds $query): array
    {
        $entities = $this->infoShipmentRepository->findByIds($query->getInfoShipmentIds());

        return array_map(static fn($entity) => InfoShipmentView::fromEntity($entity), $entities);
    }
}
