<?php
/**
 * Handler to retrieve a single log entry for detailed view.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Log\Handler;

use Roanja\Module\RjMulticarrier\Domain\Log\Query\GetLogEntryForView;
use Roanja\Module\RjMulticarrier\Domain\Log\View\LogEntryView;
use Roanja\Module\RjMulticarrier\Repository\LogEntryRepository;

final class GetLogEntryForViewHandler
{
    public function __construct(private readonly LogEntryRepository $logRepository)
    {
    }

    public function handle(GetLogEntryForView $query): ?LogEntryView
    {
        $log = $this->logRepository->find($query->getLogId());

        return $log ? LogEntryView::fromEntity($log) : null;
    }
}
