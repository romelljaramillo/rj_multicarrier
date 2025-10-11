<?php
/**
 * Handles persistence logic for shop sender information.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\InfoShop\Handler;

use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Roanja\Module\RjMulticarrier\Domain\InfoShop\Command\UpsertInfoShopCommand;
use Roanja\Module\RjMulticarrier\Entity\InfoShop;
use Roanja\Module\RjMulticarrier\Entity\InfoShopShop;
use Roanja\Module\RjMulticarrier\Repository\InfoShopRepository;
use RuntimeException;

final class UpsertInfoShopHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly InfoShopRepository $infoShopRepository
    ) {
    }

    public function handle(UpsertInfoShopCommand $command): InfoShop
    {
        $shopIds = $this->normalizeShopIds($command->getShopAssociation());

        if (empty($shopIds)) {
            throw new InvalidArgumentException('At least one shop association is required.');
        }

        $infoShop = $this->resolveInfoShop($command);

        $infoShop
            ->setFirstName($command->getFirstName())
            ->setLastName($command->getLastName())
            ->setCompany($this->sanitize($command->getCompany()))
            ->setAdditionalName($this->sanitize($command->getAdditionalName()))
            ->setCountryId($command->getCountryId())
            ->setState($command->getState())
            ->setCity($command->getCity())
            ->setStreet($command->getStreet())
            ->setStreetNumber($command->getStreetNumber())
            ->setPostcode($command->getPostcode())
            ->setAdditionalAddress($this->sanitize($command->getAdditionalAddress()))
            ->setIsBusinessFlag($this->normalizeBoolean($command->isBusiness()))
            ->setEmail($this->sanitize($command->getEmail()))
            ->setPhone($command->getPhone())
            ->setVatNumber($this->sanitize($command->getVatNumber()));

        if (null === $infoShop->getId()) {
            $infoShop->setActive(true);
        }

        $this->syncShopAssociation($infoShop, $shopIds);

        $this->entityManager->flush();

        if (null === $infoShop->getId()) {
            throw new RuntimeException('Missing identifier after persisting InfoShop');
        }

        return $infoShop;
    }

    private function resolveInfoShop(UpsertInfoShopCommand $command): InfoShop
    {
        $infoShopId = $command->getInfoShopId();

        if (null === $infoShopId) {
            $infoShop = new InfoShop(
                $command->getFirstName(),
                $command->getLastName(),
                $command->getCountryId(),
                $command->getState(),
                $command->getCity(),
                $command->getStreet(),
                $command->getStreetNumber(),
                $command->getPostcode(),
                $command->getPhone()
            );

            $this->entityManager->persist($infoShop);

            return $infoShop;
        }

        $infoShop = $this->infoShopRepository->find($infoShopId);

        if (!$infoShop instanceof InfoShop) {
            throw new InvalidArgumentException(sprintf('InfoShop with id %d not found', $infoShopId));
        }

        return $infoShop;
    }

    private function sanitize(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function normalizeBoolean(?bool $value): ?string
    {
        if (null === $value) {
            return null;
        }

        return $value ? '1' : '0';
    }

    /**
     * @param int[] $shopIds
     */
    private function syncShopAssociation(InfoShop $infoShop, array $shopIds): void
    {
        $shopIds = $this->normalizeShopIds($shopIds);

        $existingIds = [];
        $collection = $infoShop->getShops();

        foreach ($collection as $mapping) {
            $currentShopId = (int) $mapping->getShopId();
            if (in_array($currentShopId, $shopIds, true)) {
                $existingIds[] = $currentShopId;
                continue;
            }

            $collection->removeElement($mapping);
            $this->entityManager->remove($mapping);
        }

        foreach ($shopIds as $shopId) {
            if (in_array($shopId, $existingIds, true)) {
                continue;
            }

            $mapping = new InfoShopShop($infoShop, $shopId);
            $this->entityManager->persist($mapping);
            if (method_exists($collection, 'add')) {
                $collection->add($mapping);
            }
        }
    }

    /**
     * @param array<int, int|string> $shopIds
     *
     * @return int[]
     */
    private function normalizeShopIds(array $shopIds): array
    {
        $ids = array_map('intval', $shopIds);
        $ids = array_filter($ids, static fn (int $id): bool => $id > 0);

        return array_values(array_unique($ids));
    }
}
