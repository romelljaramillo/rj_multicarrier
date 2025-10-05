<?php
/**
 * Handler responsible for deleting type shipments.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\TypeShipment\Handler;

use Doctrine\ORM\EntityManagerInterface;
use Roanja\Module\RjMulticarrier\Domain\TypeShipment\Command\DeleteTypeShipmentCommand;
use Roanja\Module\RjMulticarrier\Domain\TypeShipment\Exception\TypeShipmentNotFoundException;
use Roanja\Module\RjMulticarrier\Entity\TypeShipment;
use Roanja\Module\RjMulticarrier\Repository\TypeShipmentRepository;

final class DeleteTypeShipmentHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TypeShipmentRepository $typeShipmentRepository
    ) {
    }

    public function handle(DeleteTypeShipmentCommand $command): void
    {
        $typeShipment = $this->typeShipmentRepository->find($command->getTypeShipmentId());

        if (!$typeShipment instanceof TypeShipment) {
            throw TypeShipmentNotFoundException::fromId($command->getTypeShipmentId());
        }

        $this->entityManager->remove($typeShipment);
        $this->entityManager->flush();
    }
}
