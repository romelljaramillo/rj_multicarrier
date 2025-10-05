<?php
/**
 * Repository for info shop entity.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Roanja\Module\RjMulticarrier\Entity\InfoShop;

class InfoShopRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function findFirst(): ?InfoShop
    {
        try {
            return $this->entityManager->createQueryBuilder()
                ->select('infoshop')
                ->from(InfoShop::class, 'infoshop')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
        } catch (NonUniqueResultException) {
            return null;
        }
    }

    public function findOneByShop(int $shopId): ?InfoShop
    {
        $connection = $this->entityManager->getConnection();
        $infoShopId = $connection->fetchOne(
            'SELECT id_infoshop FROM ' . _DB_PREFIX_ . 'rj_multicarrier_infoshop_shop WHERE id_shop = :shopId LIMIT 1',
            ['shopId' => $shopId],
            ['shopId' => \PDO::PARAM_INT]
        );

        if (false === $infoShopId) {
            return null;
        }

        return $this->entityManager->find(InfoShop::class, (int) $infoShopId);
    }

    public function find(int $id): ?InfoShop
    {
        return $this->entityManager->find(InfoShop::class, $id);
    }
}
