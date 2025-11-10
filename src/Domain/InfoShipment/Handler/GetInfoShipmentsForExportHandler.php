<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\InfoShipment\Handler;

use Roanja\Module\RjMulticarrier\Domain\InfoShipment\Query\GetInfoShipmentsForExport;
use Roanja\Module\RjMulticarrier\Domain\InfoShipment\View\InfoShipmentView;
use Roanja\Module\RjMulticarrier\Entity\InfoShipment as InfoShipmentEntity;
use Roanja\Module\RjMulticarrier\Repository\InfoShipmentRepository;

final class GetInfoShipmentsForExportHandler
{
    public function __construct(private readonly InfoShipmentRepository $infoShipmentRepository)
    {
    }

    /**
     * @return InfoShipmentView[]
     */
    public function handle(GetInfoShipmentsForExport $query): array
    {
    $rows = $this->infoShipmentRepository->findForExport($query->getFilters());

        $views = [];
        foreach ($rows as $row) {
            if ($row instanceof InfoShipmentEntity) {
                $views[] = InfoShipmentView::fromEntity($row);
                continue;
            }

            if (is_array($row)) {
                // If repository returned a mixed array with entities (e.g. [0 => InfoShipment, 1 => Shipment])
                foreach ($row as $item) {
                    if ($item instanceof InfoShipmentEntity) {
                        $views[] = InfoShipmentView::fromEntity($item);
                        continue 2;
                    }
                }

                // If it's an associative array (DBAL fetch), use fromArray
                try {
                    $views[] = InfoShipmentView::fromArray($row);
                    continue;
                } catch (\TypeError $e) {
                    // skip invalid rows
                    continue;
                }
            }

            // skip nulls or unknown formats
        }

        return $views;
    }
}
