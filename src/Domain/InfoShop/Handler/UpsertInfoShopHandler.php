<?php
/**
 * Handles persistence logic for shop sender information.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\InfoShop\Handler;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Roanja\Module\RjMulticarrier\Domain\InfoShop\Command\UpsertInfoShopCommand;
use Roanja\Module\RjMulticarrier\Entity\InfoShop;
use Roanja\Module\RjMulticarrier\Repository\InfoShopRepository;
use RuntimeException;

final class UpsertInfoShopHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly InfoShopRepository $infoShopRepository,
        private readonly Connection $connection
    ) {
    }

    public function handle(UpsertInfoShopCommand $command): InfoShop
    {
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

        $this->entityManager->flush();

        $infoShopId = $infoShop->getId();
        if (null === $infoShopId) {
            throw new RuntimeException('Missing identifier after persisting InfoShop');
        }

        $this->syncShopAssociation($infoShopId, $command->getShopId());

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

    private function syncShopAssociation(int $infoShopId, int $shopId): void
    {
        $this->connection->executeStatement(
            'INSERT INTO ' . _DB_PREFIX_ . 'rj_multicarrier_infoshop_shop (id_infoshop, id_shop)
                VALUES (:infoShopId, :shopId)
                ON DUPLICATE KEY UPDATE id_shop = id_shop',
            [
                'infoShopId' => $infoShopId,
                'shopId' => $shopId,
            ],
            [
                'infoShopId' => \PDO::PARAM_INT,
                'shopId' => \PDO::PARAM_INT,
            ]
        );
    }
}
