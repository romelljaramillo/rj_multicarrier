<?php
/**
 * Handles persistence logic for shop sender information.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Configuration\Handler;

use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Roanja\Module\RjMulticarrier\Domain\Configuration\Command\UpsertConfigurationCommand;
use Roanja\Module\RjMulticarrier\Entity\Configuration;
use Roanja\Module\RjMulticarrier\Entity\ConfigurationShop;
use Roanja\Module\RjMulticarrier\Repository\ConfigurationRepository;
use RuntimeException;

final class UpsertConfigurationHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ConfigurationRepository $ConfigurationRepository
    ) {
    }

    public function handle(UpsertConfigurationCommand $command): Configuration
    {
        $shopIds = $this->normalizeShopIds($command->getShopAssociation());

        if (empty($shopIds)) {
            throw new InvalidArgumentException('At least one shop association is required.');
        }

        $Configuration = $this->resolveConfiguration($command);

        $Configuration
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
            ->setVatNumber($this->sanitize($command->getVatNumber()))
            ->setLabelPrefix($this->sanitize($command->getLabelPrefix()))
            ->setCashOnDeliveryModule($this->sanitize($command->getCashOnDeliveryModule()));

        if (null === $Configuration->getId()) {
            $Configuration->setActive(true);
        }

        $this->syncShopAssociation($Configuration, $shopIds);

        $this->entityManager->flush();

        if (null === $Configuration->getId()) {
            throw new RuntimeException('Missing identifier after persisting Configuration');
        }

        return $Configuration;
    }

    private function resolveConfiguration(UpsertConfigurationCommand $command): Configuration
    {
        $ConfigurationId = $command->getConfigurationId();

        if (null === $ConfigurationId) {
            $Configuration = new Configuration(
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

            $this->entityManager->persist($Configuration);

            return $Configuration;
        }

        $Configuration = $this->ConfigurationRepository->find($ConfigurationId);

        if (!$Configuration instanceof Configuration) {
            throw new InvalidArgumentException(sprintf('Configuration with id %d not found', $ConfigurationId));
        }

        return $Configuration;
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
    private function syncShopAssociation(Configuration $Configuration, array $shopIds): void
    {
        $shopIds = $this->normalizeShopIds($shopIds);

        $existingIds = [];
        $collection = $Configuration->getShops();

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

            $mapping = new ConfigurationShop($Configuration, $shopId);
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
