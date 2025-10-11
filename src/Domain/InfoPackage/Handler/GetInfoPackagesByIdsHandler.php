<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\InfoPackage\Handler;

use Roanja\Module\RjMulticarrier\Domain\InfoPackage\Query\GetInfoPackagesByIds;
use Roanja\Module\RjMulticarrier\Domain\InfoPackage\View\InfoPackageView;
use Roanja\Module\RjMulticarrier\Repository\InfoPackageRepository;

final class GetInfoPackagesByIdsHandler
{
    public function __construct(private readonly InfoPackageRepository $infoPackageRepository)
    {
    }

    /**
     * @return InfoPackageView[]
     */
    public function handle(GetInfoPackagesByIds $query): array
    {
        $entities = $this->infoPackageRepository->findByIds($query->getInfoPackageIds());

        return array_map(static fn($e) => InfoPackageView::fromEntity($e), $entities);
    }
}
