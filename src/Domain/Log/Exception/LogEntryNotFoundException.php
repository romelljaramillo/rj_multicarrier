<?php
/**
 * Thrown when a log entry cannot be found.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Log\Exception;

final class LogEntryNotFoundException extends LogEntryException
{
    public static function withId(int $logEntryId): self
    {
        return new self(sprintf('Log entry with id %d could not be found.', $logEntryId));
    }
}
