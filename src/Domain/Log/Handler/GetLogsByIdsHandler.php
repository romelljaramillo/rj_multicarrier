<?php
/**
 * Handler fetching log entries by identifiers.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Log\Handler;

use Roanja\Module\RjMulticarrier\Domain\Log\Query\GetLogsByIds;
use Roanja\Module\RjMulticarrier\Domain\Log\View\LogEntryView;
use Roanja\Module\RjMulticarrier\Repository\LogEntryRepository;

final class GetLogsByIdsHandler
{
    public function __construct(private readonly LogEntryRepository $logRepository)
    {
    }

    /**
     * @return LogEntryView[]
     */
    public function handle(GetLogsByIds $query): array
    {
        $logs = $this->logRepository->findByIds($query->getIds());

        return array_map(static function ($log) {
            return LogEntryView::fromEntity($log);
        }, $logs);
    }
}
