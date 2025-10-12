<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Carrier\Query;

final class GetCarriersForExport
{
    public function __construct(private array $filters = [])
    {
    }

    public function getFilters(): array
    {
        return $this->filters;
    }
}
