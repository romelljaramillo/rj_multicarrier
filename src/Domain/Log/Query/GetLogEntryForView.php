<?php
/**
 * Query to retrieve a single log entry for detail view.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Log\Query;

final class GetLogEntryForView
{
    public function __construct(private readonly int $logId)
    {
    }

    public function getLogId(): int
    {
        return $this->logId;
    }
}
