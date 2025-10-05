<?php
/**
 * Repository for type shipment entity.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\QueryBuilder;
use Roanja\Module\RjMulticarrier\Entity\Company;
use Roanja\Module\RjMulticarrier\Entity\TypeShipment;

class TypeShipmentRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * @return TypeShipment[]
     */
    public function findByCompany(Company $company, bool $onlyActive = false): array
    {
        $qb = $this->createQueryBuilder('type')
            ->andWhere('type.company = :company')
            ->setParameter('company', $company)
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
     * @return int[]
     */
    public function findAllActiveReferenceCarrierIds(): array
    {
        $results = $this->createQueryBuilder('type')
            ->select('DISTINCT type.referenceCarrierId AS referenceCarrierId')
            ->andWhere('type.active = true')
            ->andWhere('type.referenceCarrierId IS NOT NULL')
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row): int => (int) $row['referenceCarrierId'], $results);
    }

    private function createQueryBuilder(string $alias): QueryBuilder
    {
        return $this->entityManager->createQueryBuilder()
            ->select($alias)
            ->from(TypeShipment::class, $alias);
    }
}
