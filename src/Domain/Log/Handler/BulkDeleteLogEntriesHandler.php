<?php
/**
 * Handles bulk deletion of carrier log entries.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Log\Handler;

use Doctrine\ORM\EntityManagerInterface;
use Roanja\Module\RjMulticarrier\Domain\Log\Command\BulkDeleteLogEntriesCommand;
use Roanja\Module\RjMulticarrier\Repository\LogEntryRepository;

final class BulkDeleteLogEntriesHandler
{
    public function __construct(
        private readonly LogEntryRepository $logEntryRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function handle(BulkDeleteLogEntriesCommand $command): void
    {
        $logEntries = $this->logEntryRepository->findBy(['id' => $command->getLogEntryIds()]);

        if (empty($logEntries)) {
            return;
        }

        foreach ($logEntries as $logEntry) {
            $this->entityManager->remove($logEntry);
        }

        $this->entityManager->flush();
    }
}
