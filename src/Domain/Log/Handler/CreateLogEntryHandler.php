<?php
/**
 * Handles persistence of carrier log entries through Doctrine.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Log\Handler;

use Doctrine\ORM\EntityManagerInterface;
use Roanja\Module\RjMulticarrier\Domain\Log\Command\CreateLogEntryCommand;
use Roanja\Module\RjMulticarrier\Entity\LogEntry;

final class CreateLogEntryHandler
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function handle(CreateLogEntryCommand $command): LogEntry
    {
        $logEntry = new LogEntry(
            $command->getName(),
            $command->getOrderId(),
            $command->getRequest(),
            $command->getResponse(),
            $command->getShopId() ?? 0
        );

        $this->entityManager->persist($logEntry);
        $this->entityManager->flush();

        return $logEntry;
    }
}
