<?php

declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\InfoPackage\Query;

final class GetInfoPackagesForExport
{
    /**
     * @param array<string, mixed> $filters
     */
    public function __construct(private readonly array $filters)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function getFilters(): array
    {
        return $this->filters;
    }
}
