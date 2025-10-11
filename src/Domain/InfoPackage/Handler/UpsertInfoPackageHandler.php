<?php
/**
 * Handles persistence for package information associated with orders.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\InfoPackage\Handler;

use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Roanja\Module\RjMulticarrier\Domain\InfoPackage\Command\UpsertInfoPackageCommand;
use Roanja\Module\RjMulticarrier\Entity\InfoPackage;
use Roanja\Module\RjMulticarrier\Entity\TypeShipment;
use Roanja\Module\RjMulticarrier\Repository\InfoPackageRepository;
use Roanja\Module\RjMulticarrier\Repository\TypeShipmentRepository;
use RuntimeException;

final class UpsertInfoPackageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly InfoPackageRepository $infoPackageRepository,
        private readonly TypeShipmentRepository $typeShipmentRepository,
        private readonly Connection $connection
    ) {
    }

    public function handle(UpsertInfoPackageCommand $command): InfoPackage
    {
        $infoPackage = $this->resolveInfoPackage($command);

        $typeShipment = $this->getTypeShipment($command->getTypeShipmentId());

        $infoPackage
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
            ->setDorig($command->getDorig());

        $this->entityManager->flush();

        $infoPackageId = $infoPackage->getId();
        if (null === $infoPackageId) {
            throw new RuntimeException('Missing identifier after persisting InfoPackage');
        }

    // Persist ORM mapping for multi-shop
    $this->syncShopAssociation($infoPackage, $command->getShopId());

        return $infoPackage;
    }

    private function resolveInfoPackage(UpsertInfoPackageCommand $command): InfoPackage
    {
        $infoPackageId = $command->getInfoPackageId();

        if (null === $infoPackageId) {
            $typeShipment = $this->getTypeShipment($command->getTypeShipmentId());
            $infoPackage = new InfoPackage(
                $command->getOrderId(),
                $command->getReferenceCarrierId(),
                $typeShipment,
                max(1, $command->getQuantity()),
                $command->getWeight()
            );

            $this->entityManager->persist($infoPackage);

            return $infoPackage;
        }

        $infoPackage = $this->infoPackageRepository->find($infoPackageId);

        if (!$infoPackage instanceof InfoPackage) {
            throw new InvalidArgumentException(sprintf('InfoPackage with id %d not found', $infoPackageId));
        }

        return $infoPackage;
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

    private function syncShopAssociation(InfoPackage $infoPackage, int $shopId): void
    {
        // Create mapping entity and persist if not already present
        foreach ($infoPackage->getShops() as $mapping) {
            if ($mapping->getShopId() === $shopId) {
                return; // already mapped
            }
        }

        $mapping = new \Roanja\Module\RjMulticarrier\Entity\InfoPackageShop($infoPackage, $shopId);
        $this->entityManager->persist($mapping);
        $this->entityManager->flush();
    }
}
