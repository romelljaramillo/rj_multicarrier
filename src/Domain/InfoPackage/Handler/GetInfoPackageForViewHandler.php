<?php
/**
 * Handler returning info package view objects for detail modals.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\InfoPackage\Handler;

use Roanja\Module\RjMulticarrier\Domain\InfoPackage\Query\GetInfoPackageForView;
use Roanja\Module\RjMulticarrier\Domain\InfoPackage\View\InfoPackageView;
use Roanja\Module\RjMulticarrier\Repository\InfoPackageRepository;

final class GetInfoPackageForViewHandler
{
    public function __construct(private readonly InfoPackageRepository $infoPackageRepository)
    {
    }

    public function handle(GetInfoPackageForView $query): ?InfoPackageView
    {
        $infoPackage = $this->infoPackageRepository->find($query->getInfoPackageId());

        if (!$infoPackage) {
            return null;
        }

        return InfoPackageView::fromEntity($infoPackage);
    }
}
