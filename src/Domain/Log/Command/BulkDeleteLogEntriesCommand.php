<?php
/**
 * Command to delete multiple carrier log entries at once.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Log\Command;

/**
 * @psalm-immutable
 */
final class BulkDeleteLogEntriesCommand
{
    /**
     * @param int[] $logEntryIds
     */
    public function __construct(private readonly array $logEntryIds)
    {
    }

    /**
     * @return int[]
     */
    public function getLogEntryIds(): array
    {
        return $this->logEntryIds;
    }
}
