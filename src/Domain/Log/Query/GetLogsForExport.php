<?php
/**
 * Query to collect logs matching filters for export.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Log\Query;

final class GetLogsForExport
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
