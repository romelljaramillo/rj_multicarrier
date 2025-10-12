<?php
/**
 * Repository for info shop entity.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Roanja\Module\RjMulticarrier\Entity\Configuration;
use Roanja\Module\RjMulticarrier\Entity\ConfigurationShop;

class ConfigurationRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function findFirst(): ?Configuration
    {
        try {
            return $this->entityManager->createQueryBuilder()
                ->select('config')
                ->from(Configuration::class, 'config')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
        } catch (NonUniqueResultException) {
            return null;
        }
    }

    public function findOneByShop(int $shopId): ?Configuration
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('cs', 'config')
            ->from(ConfigurationShop::class, 'cs')
            ->leftJoin('cs.configuration', 'config')
            ->andWhere('cs.shopId = :shopId')
            ->setParameter('shopId', $shopId)
            ->setMaxResults(1);

        $result = $qb->getQuery()->getOneOrNullResult();

        if (is_array($result) && isset($result[1]) && $result[1] instanceof Configuration) {
            return $result[1];
        }

        if ($result instanceof Configuration) {
            return $result;
        }

        if (is_array($result) && isset($result['configuration']) && $result['configuration'] instanceof Configuration) {
            return $result['configuration'];
        }

        return null;
    }

    public function find(int $id): ?Configuration
    {
        return $this->entityManager->find(Configuration::class, $id);
    }

    /**
     * @return Configuration[]
     */
    public function findAllOrdered(): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('config')
            ->from(Configuration::class, 'config')
            ->orderBy('config.firstname', 'ASC')
            ->addOrderBy('config.lastname', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
