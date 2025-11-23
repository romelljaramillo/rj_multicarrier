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
    public function findActiveRulesForShop(?int $shopId): array
    {
        if (null === $shopId) {
            return [];
        }

        $qb = $this->createQueryBuilder('r')
            ->where('r.active = :active')
            ->andWhere('r.shopId = :shopId')
            ->setParameter('active', true)
            ->setParameter('shopId', $shopId)
            ->orderBy('r.priority', 'ASC');

        return $qb->getQuery()->getResult();
    }
}
