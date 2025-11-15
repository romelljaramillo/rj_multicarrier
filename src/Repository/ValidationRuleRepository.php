<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Roanja\Module\RjMulticarrier\Entity\ValidationRule;

class ValidationRuleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ValidationRule::class);
    }

    public function findOneById(int $id): ?ValidationRule
    {
        /** @var ValidationRule|null $rule */
        $rule = $this->find($id);

        return $rule instanceof ValidationRule ? $rule : null;
    }

    /**
     * Find active rules for the given shop context. Returns an array of ValidationRule.
     *
     * @return ValidationRule[]
     */
    public function findActiveRulesForContext(?int $shopId, ?int $shopGroupId): array
    {
        $qb = $this->createQueryBuilder('r')
            ->where('r.active = :active')
            ->setParameter('active', true)
            ->orderBy('r.priority', 'ASC');

        if (null !== $shopGroupId) {
            $qb->andWhere('r.shopGroupId = :shopGroupId')
                ->setParameter('shopGroupId', $shopGroupId);
        }

        if (null !== $shopId) {
            $qb->andWhere('r.shopId = :shopId')
                ->setParameter('shopId', $shopId);
        }

        return $qb->getQuery()->getResult();
    }
}
