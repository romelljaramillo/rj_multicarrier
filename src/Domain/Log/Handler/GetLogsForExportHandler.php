<?php
/**
 * Handler that gathers log entries for export.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Log\Handler;

use Roanja\Module\RjMulticarrier\Domain\Log\Query\GetLogsForExport;
use Roanja\Module\RjMulticarrier\Domain\Log\View\LogEntryView;
use Roanja\Module\RjMulticarrier\Repository\LogEntryRepository;

final class GetLogsForExportHandler
{
    public function __construct(private readonly LogEntryRepository $logRepository)
    {
    }

    /**
     * @return LogEntryView[]
     */
    public function handle(GetLogsForExport $query): array
    {
        $logs = $this->logRepository->findForExport($query->getFilters());

        return array_map(static function ($log) {
            return LogEntryView::fromEntity($log);
        }, $logs);
    }
}
