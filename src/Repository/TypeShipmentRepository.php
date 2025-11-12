<?php
/**
 * Repository for type shipment entity.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\QueryBuilder;
use Roanja\Module\RjMulticarrier\Entity\Carrier;
use Roanja\Module\RjMulticarrier\Entity\TypeShipment;

class TypeShipmentRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * @return TypeShipment[]
     */
    public function findByCarrier(Carrier $carrier, bool $onlyActive = false): array
    {
        $qb = $this->createQueryBuilder('type')
            ->andWhere('type.carrier = :carrier')
            ->setParameter('carrier', $carrier)
            ->orderBy('type.name', 'ASC');

        if ($onlyActive) {
            $qb->andWhere('type.active = true');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @return TypeShipment[]
     */
    public function findByCarrierId(int $carrierId, bool $onlyActive = false): array
    {
        $qb = $this->createQueryBuilder('type')
            ->innerJoin('type.carrier', 'carrier')
            ->andWhere('carrier.id = :carrierId')
            ->setParameter('carrierId', $carrierId)
            ->orderBy('type.name', 'ASC');

        if ($onlyActive) {
            $qb->andWhere('type.active = true');
        }

        return $qb->getQuery()->getResult();
    }

    public function findActiveByReferenceCarrier(int $referenceCarrierId): ?TypeShipment
    {
        try {
            return $this->createQueryBuilder('type')
                ->andWhere('type.referenceCarrierId = :reference')
                ->andWhere('type.active = true')
                ->setParameter('reference', $referenceCarrierId)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
        } catch (NonUniqueResultException) {
            return null;
        }
    }

    /**
     * @param bool $onlyActive
     *
     * @return int[]
     */
    public function findAllReferenceCarrierIds(bool $onlyActive = false): array
    {
        $qb = $this->createQueryBuilder('type')
            ->select('DISTINCT type.referenceCarrierId AS referenceCarrierId')
            ->andWhere('type.referenceCarrierId IS NOT NULL');

        if ($onlyActive) {
            $qb->andWhere('type.active = true');
        }

        $results = $qb->getQuery()->getScalarResult();

        return array_map(static fn (array $row): int => (int) $row['referenceCarrierId'], $results);
    }

    public function findOneById(int $typeShipmentId): ?TypeShipment
    {
        return $this->createQueryBuilder('type')
            ->andWhere('type.id = :id')
            ->setParameter('id', $typeShipmentId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneByReferenceCarrierId(int $referenceId): ?TypeShipment
    {
        return $this->createQueryBuilder('type')
            ->andWhere('type.referenceCarrierId = :reference')
            ->setParameter('reference', $referenceId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function find(int $id): ?TypeShipment
    {
        $entity = $this->entityManager->find(TypeShipment::class, $id);

        return $entity instanceof TypeShipment ? $entity : null;
    }

    private function createQueryBuilder(string $alias): QueryBuilder
    {
        return $this->entityManager->createQueryBuilder()
            ->select($alias)
            ->from(TypeShipment::class, $alias);
    }
}
