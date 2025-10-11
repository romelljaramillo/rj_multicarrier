<?php
/**
 * Doctrine repository for company entities.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\QueryBuilder;
use Roanja\Module\RjMulticarrier\Entity\Company;

class CompanyRepository
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function findOneByShortName(string $shortName): ?Company
    {
        try {
            return $this->createQueryBuilder('company')
                ->andWhere('company.shortName = :shortName')
                ->setParameter('shortName', $shortName)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();
        } catch (NonUniqueResultException) {
            return null;
        }
    }

    public function find(int $id): ?Company
    {
        $entity = $this->entityManager->find(Company::class, $id);

        return $entity instanceof Company ? $entity : null;
    }

    public function getIconByShortName(string $shortName): ?string
    {
        $company = $this->findOneByShortName($shortName);

        return $company?->getIcon();
    }

    /**
     * @return Company[]
     */
    public function findAllOrdered(?string $shortName = null): array
    {
        $qb = $this->createQueryBuilder('company')
            ->orderBy('company.name', 'ASC');

        if ($shortName) {
            $qb->andWhere('company.shortName = :shortName')
                ->setParameter('shortName', $shortName);
        }

        return $qb->getQuery()->getResult();
    }

    private function createQueryBuilder(string $alias): QueryBuilder
    {
        return $this->entityManager->createQueryBuilder()
            ->select($alias)
            ->from(Company::class, $alias);
    }
}
