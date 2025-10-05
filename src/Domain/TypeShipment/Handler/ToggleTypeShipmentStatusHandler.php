<?php
/**
 * Handler responsible for toggling type shipment active status.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\TypeShipment\Handler;

use Doctrine\ORM\EntityManagerInterface;
use Roanja\Module\RjMulticarrier\Domain\TypeShipment\Command\ToggleTypeShipmentStatusCommand;
use Roanja\Module\RjMulticarrier\Domain\TypeShipment\Exception\TypeShipmentNotFoundException;
use Roanja\Module\RjMulticarrier\Entity\TypeShipment;
use Roanja\Module\RjMulticarrier\Repository\TypeShipmentRepository;

final class ToggleTypeShipmentStatusHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TypeShipmentRepository $typeShipmentRepository
    ) {
    }

    public function handle(ToggleTypeShipmentStatusCommand $command): TypeShipment
    {
        $typeShipment = $this->typeShipmentRepository->find($command->getTypeShipmentId());

        if (!$typeShipment instanceof TypeShipment) {
            throw TypeShipmentNotFoundException::fromId($command->getTypeShipmentId());
        }

        $typeShipment->setActive(!$typeShipment->isActive());
        $this->entityManager->flush();

        return $typeShipment;
    }
}
