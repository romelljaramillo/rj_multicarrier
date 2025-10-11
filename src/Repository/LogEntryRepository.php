<?php
/**
 * Repository for legacy log entries.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Repository;

use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use PrestaShop\PrestaShop\Adapter\Shop\Context as ShopContext;
use Roanja\Module\RjMulticarrier\Entity\LogEntry;

class LogEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly ShopContext $shopContext)
    {
        parent::__construct($registry, LogEntry::class);
    }

    /**
     * @return LogEntry[]
     */
    public function findByOrder(int $orderId): array
    {
        $qb = $this->createQueryBuilder('log')
            ->andWhere('log.orderId = :orderId')
            ->setParameter('orderId', $orderId)
            ->orderBy('log.createdAt', 'DESC');

        $this->applyShopRestriction($qb);

        return $qb->getQuery()->getResult();
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return LogEntry[]
     */
    public function findForExport(array $filters): array
    {
        $qb = $this->createQueryBuilder('log')
            ->orderBy('log.createdAt', 'DESC');

        $this->applyShopRestriction($qb);

        if (!empty($filters['id_carrier_log'])) {
            $qb->andWhere('log.id = :id')
                ->setParameter('id', (int) $filters['id_carrier_log']);
        }

        if (!empty($filters['name'])) {
            $qb->andWhere('log.name LIKE :name')
                ->setParameter('name', '%' . $filters['name'] . '%');
        }

        if (!empty($filters['id_order'])) {
            $qb->andWhere('log.orderId = :orderId')
                ->setParameter('orderId', (int) $filters['id_order']);
        }

        if (!empty($filters['date_add'])) {
            $dateFilter = $filters['date_add'];

            if (is_array($dateFilter)) {
                if (!empty($dateFilter['from'])) {
                    $qb->andWhere('log.createdAt >= :fromDate')
                        ->setParameter('fromDate', new DateTimeImmutable($dateFilter['from'] . ' 00:00:00'));
                }

                if (!empty($dateFilter['to'])) {
                    $qb->andWhere('log.createdAt <= :toDate')
                        ->setParameter('toDate', new DateTimeImmutable($dateFilter['to'] . ' 23:59:59'));
                }
            } elseif (is_string($dateFilter) && '' !== $dateFilter) {
                $fromDate = new DateTimeImmutable($dateFilter . ' 00:00:00');
                $toDate = new DateTimeImmutable($dateFilter . ' 23:59:59');

                $qb->andWhere('log.createdAt BETWEEN :fromExactDate AND :toExactDate')
                    ->setParameter('fromExactDate', $fromDate)
                    ->setParameter('toExactDate', $toDate);
            }
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @param int[] $ids
     *
     * @return LogEntry[]
     */
    public function findByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $qb = $this->createQueryBuilder('log')
            ->andWhere('log.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->orderBy('log.createdAt', 'DESC');

        $this->applyShopRestriction($qb);

        return $qb->getQuery()->getResult();
    }

    private function applyShopRestriction(\Doctrine\ORM\QueryBuilder $qb): void
    {
        $shopIds = $this->getContextShopIds();

        if (!empty($shopIds)) {
            $qb->andWhere($qb->expr()->in('log.shopId', ':shopIds'))
                ->setParameter('shopIds', $shopIds);
        }
    }

    /**
     * @return int[]
     */
    private function getContextShopIds(): array
    {
        $shopIds = $this->shopContext->getContextListShopID();

        if (empty($shopIds)) {
            $contextShopId = $this->shopContext->getContextShopID(true);

            if (null !== $contextShopId) {
                $shopIds = [(int) $contextShopId];
            }
        }

        return array_map('intval', $shopIds);
    }
}
