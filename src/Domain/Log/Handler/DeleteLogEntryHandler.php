<?php
/**
 * Handles deletion of carrier log entries via Doctrine.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Log\Handler;

use Doctrine\ORM\EntityManagerInterface;
use Roanja\Module\RjMulticarrier\Domain\Log\Command\DeleteLogEntryCommand;
use Roanja\Module\RjMulticarrier\Domain\Log\Exception\LogEntryNotFoundException;
use Roanja\Module\RjMulticarrier\Repository\LogEntryRepository;

final class DeleteLogEntryHandler
{
    public function __construct(
        private readonly LogEntryRepository $logEntryRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * @throws LogEntryNotFoundException
     */
    public function handle(DeleteLogEntryCommand $command): void
    {
        $logEntry = $this->logEntryRepository->find($command->getLogEntryId());

        if (null === $logEntry) {
            throw LogEntryNotFoundException::withId($command->getLogEntryId());
        }

        $this->entityManager->remove($logEntry);
        $this->entityManager->flush();
    }
}
