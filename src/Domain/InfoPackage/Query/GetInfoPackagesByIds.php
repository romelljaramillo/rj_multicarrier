<?php

declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\InfoPackage\Query;

final class GetInfoPackagesByIds
{
    /**
     * @var int[]
     */
    private array $infoPackageIds;

    /**
     * @param int[] $infoPackageIds
     */
    public function __construct(array $infoPackageIds)
    {
        $this->infoPackageIds = $infoPackageIds;
    }

    /**
     * @return int[]
     */
    public function getInfoPackageIds(): array
    {
        return $this->infoPackageIds;
    }
}
