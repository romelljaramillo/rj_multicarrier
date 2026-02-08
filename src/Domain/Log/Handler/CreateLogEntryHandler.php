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
        $shopId = $command->getShopId() ?? 0;
        if ($shopId <= 0) {
            $context = \Context::getContext();
            $shopId = (int) ($context->shop->id ?? 0);
        }

        $logEntry = new LogEntry(
            $command->getName(),
            $command->getOrderId(),
            $command->getRequest(),
            $command->getResponse(),
            $shopId
        );

        $this->entityManager->persist($logEntry);
        $this->entityManager->flush();

        return $logEntry;
    }
}
