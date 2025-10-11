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
        $qb = $this->entityManager->createQueryBuilder()
            ->select('s', 'infoshop')
            ->from(\Roanja\Module\RjMulticarrier\Entity\InfoShopShop::class, 's')
            ->leftJoin('s.infoShop', 'infoshop')
            ->andWhere('s.shopId = :shopId')
            ->setParameter('shopId', $shopId)
            ->setMaxResults(1);

        $result = $qb->getQuery()->getOneOrNullResult();

        if (is_array($result) && isset($result[1]) && $result[1] instanceof InfoShop) {
            return $result[1];
        }

        if ($result instanceof InfoShop) {
            return $result;
        }

        if (is_array($result) && isset($result['infoShop']) && $result['infoShop'] instanceof InfoShop) {
            return $result['infoShop'];
        }

        return null;
    }

    public function find(int $id): ?InfoShop
    {
        return $this->entityManager->find(InfoShop::class, $id);
    }

    /**
     * @return InfoShop[]
     */
    public function findAllOrdered(): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('infoshop')
            ->from(InfoShop::class, 'infoshop')
            ->orderBy('infoshop.firstname', 'ASC')
            ->addOrderBy('infoshop.lastname', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
