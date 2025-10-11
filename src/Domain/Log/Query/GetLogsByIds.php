<?php
/**
 * Query to fetch log entries by their identifiers.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Log\Query;

final class GetLogsByIds
{
    /**
     * @param int[] $ids
     */
    public function __construct(private readonly array $ids)
    {
    }

    /**
     * @return int[]
     */
    public function getIds(): array
    {
        return $this->ids;
    }
}
