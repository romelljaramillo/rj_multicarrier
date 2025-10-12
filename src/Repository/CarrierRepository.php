<?php
/**
 * Doctrine repository for carrier entities.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\QueryBuilder;
use Roanja\Module\RjMulticarrier\Entity\Carrier;

class CarrierRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function findOneByShortName(string $shortName): ?Carrier
    {
        try {
            return $this->createQueryBuilder('carrier')
                ->andWhere('carrier.shortName = :shortName')
                ->andWhere('carrier.deleted = false')
                ->setParameter('shortName', $shortName)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
        } catch (NonUniqueResultException) {
            return null;
        }
    }

    public function find(int $id): ?Carrier
    {
        $entity = $this->entityManager->find(Carrier::class, $id);

        if ($entity instanceof Carrier && $entity->isDeleted()) {
            return null;
        }

        return $entity instanceof Carrier ? $entity : null;
    }

    public function getIconByShortName(string $shortName): ?string
    {
        $carrier = $this->findOneByShortName($shortName);

        return $carrier?->getIcon();
    }

    /**
     * @return Carrier[]
    */
    public function findAllOrdered(?string $shortName = null): array
    {
        $qb = $this->createQueryBuilder('carrier')
            ->andWhere('carrier.deleted = false')
            ->orderBy('carrier.name', 'ASC');

        if ($shortName) {
            $qb->andWhere('carrier.shortName = :shortName')
                ->setParameter('shortName', $shortName);
        }

        return $qb->getQuery()->getResult();
    }

    private function createQueryBuilder(string $alias): QueryBuilder
    {
        return $this->entityManager->createQueryBuilder()
            ->select($alias)
            ->from(Carrier::class, $alias);
    }
}
