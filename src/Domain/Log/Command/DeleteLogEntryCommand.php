<?php
/**
 * Command to delete a carrier log entry.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Log\Command;

/**
 * @psalm-immutable
 */
final class DeleteLogEntryCommand
{
    public function __construct(private readonly int $logEntryId)
    {
    }

    public function getLogEntryId(): int
    {
        return $this->logEntryId;
    }
}
