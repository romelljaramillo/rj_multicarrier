<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\InfoPackage\Handler;

use Roanja\Module\RjMulticarrier\Domain\InfoPackage\Query\GetInfoPackagesForExport;
use Roanja\Module\RjMulticarrier\Domain\InfoPackage\View\InfoPackageView;
use Roanja\Module\RjMulticarrier\Entity\InfoPackage as InfoPackageEntity;
use Roanja\Module\RjMulticarrier\Repository\InfoPackageRepository;

final class GetInfoPackagesForExportHandler
{
    public function __construct(private readonly InfoPackageRepository $infoPackageRepository)
    {
    }

    /**
     * @return InfoPackageView[]
     */
    public function handle(GetInfoPackagesForExport $query): array
    {
        $rows = $this->infoPackageRepository->findForExport($query->getFilters());

        $views = [];
        foreach ($rows as $row) {
            if ($row instanceof InfoPackageEntity) {
                $views[] = InfoPackageView::fromEntity($row);
                continue;
            }

            if (is_array($row)) {
                // If repository returned a mixed array with entities (e.g. [0 => InfoPackage, 1 => Shipment])
                foreach ($row as $item) {
                    if ($item instanceof InfoPackageEntity) {
                        $views[] = InfoPackageView::fromEntity($item);
                        continue 2;
                    }
                }

                // If it's an associative array (DBAL fetch), use fromArray
                try {
                    $views[] = InfoPackageView::fromArray($row);
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
