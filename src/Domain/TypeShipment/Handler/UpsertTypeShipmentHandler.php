<?php
/**
 * Handler responsible for creating or updating type shipments.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\TypeShipment\Handler;

use Doctrine\ORM\EntityManagerInterface;
use Roanja\Module\RjMulticarrier\Domain\TypeShipment\Command\UpsertTypeShipmentCommand;
use Roanja\Module\RjMulticarrier\Domain\TypeShipment\Exception\TypeShipmentCarrierConflictException;
use Roanja\Module\RjMulticarrier\Domain\TypeShipment\Exception\TypeShipmentException;
use Roanja\Module\RjMulticarrier\Domain\TypeShipment\Exception\TypeShipmentNotFoundException;
use Roanja\Module\RjMulticarrier\Entity\Carrier;
use Roanja\Module\RjMulticarrier\Entity\TypeShipment;
use Roanja\Module\RjMulticarrier\Repository\CarrierRepository;
use Roanja\Module\RjMulticarrier\Repository\TypeShipmentRepository;

final class UpsertTypeShipmentHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CarrierRepository $carrierRepository,
        private readonly TypeShipmentRepository $typeShipmentRepository
    ) {
    }

    public function handle(UpsertTypeShipmentCommand $command): TypeShipment
    {
        $carrier = $this->resolveCarrier($command->getCarrierId());
        $typeShipment = $this->resolveTypeShipment($command, $carrier);

        $referenceCarrierId = $command->getReferenceCarrierId();
        if (null !== $referenceCarrierId) {
            $this->assertCarrierIsAvailable($referenceCarrierId, $typeShipment->getId());
        }

        $typeShipment
            ->setCarrier($carrier)
            ->setName($command->getName())
            ->setBusinessCode($command->getBusinessCode())
            ->setReferenceCarrierId($referenceCarrierId)
            ->setActive($command->isActive());

        $this->entityManager->flush();

        return $typeShipment;
    }

    private function resolveCarrier(int $carrierId): Carrier
    {
        $carrier = $this->carrierRepository->find($carrierId);

        if (!$carrier instanceof Carrier) {
            throw new TypeShipmentException(sprintf('Carrier with id %d was not found.', $carrierId));
        }

        return $carrier;
    }

    private function resolveTypeShipment(UpsertTypeShipmentCommand $command, Carrier $carrier): TypeShipment
    {
        $typeShipmentId = $command->getTypeShipmentId();

        if (null === $typeShipmentId) {
            $typeShipment = new TypeShipment($carrier, $command->getName(), $command->getBusinessCode());
            $typeShipment->setReferenceCarrierId($command->getReferenceCarrierId());
            $typeShipment->setActive($command->isActive());

            $this->entityManager->persist($typeShipment);

            return $typeShipment;
        }

        $typeShipment = $this->typeShipmentRepository->find($typeShipmentId);

        if (!$typeShipment instanceof TypeShipment) {
            throw TypeShipmentNotFoundException::fromId($typeShipmentId);
        }

        return $typeShipment;
    }

    private function assertCarrierIsAvailable(int $referenceCarrierId, ?int $currentTypeShipmentId): void
    {
        $existing = $this->typeShipmentRepository->findActiveByReferenceCarrier($referenceCarrierId);

        if (null === $existing) {
            return;
        }

        if (null !== $currentTypeShipmentId && $existing->getId() === $currentTypeShipmentId) {
            return;
        }

        throw TypeShipmentCarrierConflictException::fromReference($referenceCarrierId);
    }
}
