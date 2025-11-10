<?php
/**
 * Handles persistence for package information associated with orders.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\InfoShipment\Handler;

use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Roanja\Module\RjMulticarrier\Domain\InfoShipment\Command\UpsertInfoShipmentCommand;
use Roanja\Module\RjMulticarrier\Entity\InfoShipment;
use Roanja\Module\RjMulticarrier\Entity\InfoShipmentShop;
use Roanja\Module\RjMulticarrier\Entity\TypeShipment;
use Roanja\Module\RjMulticarrier\Repository\InfoShipmentRepository;
use Roanja\Module\RjMulticarrier\Repository\TypeShipmentRepository;
use RuntimeException;

final class UpsertInfoShipmentHandler
{
    public function __construct(
    private readonly EntityManagerInterface $entityManager,
    private readonly InfoShipmentRepository $infoShipmentRepository,
        private readonly TypeShipmentRepository $typeShipmentRepository,
        private readonly Connection $connection
    ) {
    }

    public function handle(UpsertInfoShipmentCommand $command): InfoShipment
    {
        $infoShipment = $this->resolveInfoShipment($command);

        $typeShipment = $this->getTypeShipment($command->getTypeShipmentId());

        $infoShipment
            ->setOrderId($command->getOrderId())
            ->setReferenceCarrierId($command->getReferenceCarrierId())
            ->setTypeShipment($typeShipment)
            ->setQuantity(max(1, $command->getQuantity()))
            ->setWeight($command->getWeight())
            ->setLength($command->getLength())
            ->setWidth($command->getWidth())
            ->setHeight($command->getHeight())
            ->setCashOnDelivery($command->getCashOnDelivery())
            ->setMessage($command->getMessage())
            ->setHourFrom($this->toTime($command->getHourFrom()))
            ->setHourUntil($this->toTime($command->getHourUntil()))
            ->setRetorno($command->getRetorno())
            ->setRcsEnabled($command->isRcsEnabled())
            ->setVsec($command->getVsec())
            ->setDorig($command->getDorig())
            ->touch(); // Update timestamp

        $this->entityManager->flush();

        $infoShipmentId = $infoShipment->getId();
        if (null === $infoShipmentId) {
            throw new RuntimeException('Missing identifier after persisting InfoShipment');
        }

        // Persist ORM mapping for multi-shop
        $this->syncShopAssociation($infoShipment, $command->getShopId());

        return $infoShipment;
    }

    private function resolveInfoShipment(UpsertInfoShipmentCommand $command): InfoShipment
    {
        $infoShipmentId = $command->getInfoShipmentId();

        if (null === $infoShipmentId) {
            $typeShipment = $this->getTypeShipment($command->getTypeShipmentId());
            $infoShipment = new InfoShipment(
                $command->getOrderId(),
                $command->getReferenceCarrierId(),
                $typeShipment,
                max(1, $command->getQuantity()),
                $command->getWeight()
            );

            $this->entityManager->persist($infoShipment);

            return $infoShipment;
        }

        $infoShipment = $this->infoShipmentRepository->find($infoShipmentId);

        if (!$infoShipment instanceof InfoShipment) {
            throw new InvalidArgumentException(sprintf('InfoShipment with id %d not found', $infoShipmentId));
        }

        return $infoShipment;
    }

    private function getTypeShipment(int $typeShipmentId): TypeShipment
    {
        $typeShipment = $this->entityManager->find(TypeShipment::class, $typeShipmentId);

        if (!$typeShipment instanceof TypeShipment) {
            throw new InvalidArgumentException(sprintf('TypeShipment with id %d not found', $typeShipmentId));
        }

        return $typeShipment;
    }

    private function toTime(?string $time): ?DateTimeInterface
    {
        if (null === $time || '' === $time) {
            return null;
        }

        $dateTime = DateTimeImmutable::createFromFormat('!H:i:s', $time);

        return $dateTime ?: null;
    }

    private function syncShopAssociation(InfoShipment $infoShipment, int $shopId): void
    {
        // Create mapping entity and persist if not already present
        foreach ($infoShipment->getShops() as $mapping) {
            if ($mapping->getShopId() === $shopId) {
                return; // already mapped
            }
        }

    $mapping = new InfoShipmentShop($infoShipment, $shopId);
        $this->entityManager->persist($mapping);
        $this->entityManager->flush();
    }
}
