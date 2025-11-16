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

        $scopeConditions = [$qb->expr()->andX('r.shopId IS NULL', 'r.shopGroupId IS NULL')];

        if (null !== $shopGroupId) {
            $scopeConditions[] = $qb->expr()->andX('r.shopId IS NULL', 'r.shopGroupId = :shopGroupId');
            $qb->setParameter('shopGroupId', $shopGroupId);
        }

        if (null !== $shopId) {
            $scopeConditions[] = 'r.shopId = :shopId';
            $qb->setParameter('shopId', $shopId);
        }

        if (!empty($scopeConditions)) {
            $qb->andWhere($qb->expr()->orX(...$scopeConditions));
        }

        return $qb->getQuery()->getResult();
    }
}
