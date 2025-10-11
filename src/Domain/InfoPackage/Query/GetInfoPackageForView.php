<?php
/**
 * Query to fetch info package details for presentation.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\InfoPackage\Query;

final class GetInfoPackageForView
{
    public function __construct(private readonly int $infoPackageId)
    {
    }

    public function getInfoPackageId(): int
    {
        return $this->infoPackageId;
    }
}
