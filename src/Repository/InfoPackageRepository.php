<?php
/**
 * Repository for info package entities.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Repository;

use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use PrestaShop\PrestaShop\Adapter\Shop\Context as ShopContext;

/**
 * Note: this repository expects the service 'prestashop.adapter.shop.context' to be injected.
 */
use Roanja\Module\RjMulticarrier\Entity\InfoPackage;

class InfoPackageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, private readonly ShopContext $shopContext)
    {
        parent::__construct($registry, InfoPackage::class);
    }

    /**
     * Expose the inherited find method on this repository class so PHPUnit can mock/configure it in tests.
     *
     * @param mixed $id
     * @param int|null $lockMode
     * @param int|null $lockVersion
     * @return object|null
     */
    public function find($id, $lockMode = null, $lockVersion = null)
    {
        return parent::find($id, $lockMode, $lockVersion);
    }

    /**
     * @param int[] $ids
     * @return InfoPackage[]
     */
    public function findByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $qb = $this->createQueryBuilder('package')
            ->andWhere('package.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->orderBy('package.createdAt', 'DESC');

        // Join type shipment and company to include their names for exports
        $qb->leftJoin('package.typeShipment', 'ts')
            ->leftJoin('ts.company', 'company')
            ->addSelect([
                'ts.name AS type_shipment_name',
                'company.id AS company_id',
                'company.name AS company_name',
                'company.shortName AS company_short_name',
            ]);

        $this->applyShopRestriction($qb);

        $rows = $qb->getQuery()->getArrayResult();

        // Normalize DateTime fields to string representations for exporter
        foreach ($rows as &$row) {
            if (isset($row['hour_from']) && $row['hour_from'] instanceof \DateTimeInterface) {
                $row['hour_from'] = $row['hour_from']->format('H:i:s');
            }

            if (isset($row['hour_until']) && $row['hour_until'] instanceof \DateTimeInterface) {
                $row['hour_until'] = $row['hour_until']->format('H:i:s');
            }

            if (isset($row['date_add']) && $row['date_add'] instanceof \DateTimeInterface) {
                $row['date_add'] = $row['date_add']->format('Y-m-d H:i:s');
            }

            if (isset($row['date_upd']) && $row['date_upd'] instanceof \DateTimeInterface) {
                $row['date_upd'] = $row['date_upd']->format('Y-m-d H:i:s');
            }

            // Ensure numeric/nullable fields have expected types
            if (isset($row['rcs'])) {
                $row['rcs'] = (bool)$row['rcs'];
            }
        }

        unset($row);

        return $rows;
    }

    /**
     * @return InfoPackage[]
     */
    public function findByOrder(int $orderId): array
    {
        $qb = $this->createQueryBuilder('package')
            ->andWhere('package.orderId = :orderId')
            ->setParameter('orderId', $orderId)
            ->orderBy('package.createdAt', 'DESC');

        $this->applyShopRestriction($qb);

        return $qb->getQuery()->getResult();
    }

    /**
     * @param array<string, mixed> $filters
     *
     * @return InfoPackage[]
     */
    public function findForExport(array $filters): array
    {
        // Select explicit fields (array result) so the handler/exporter can write CSV rows
        $qb = $this->createQueryBuilder('package')
            ->select([
                'package.id AS id_infopackage',
                'package.orderId AS id_order',
                'IDENTITY(package.referenceCarrier) AS id_reference_carrier',
                'IDENTITY(package.typeShipment) AS id_type_shipment',
                'package.quantity AS quantity',
                'package.weight AS weight',
                'package.length AS length',
                'package.width AS width',
                'package.height AS height',
                'package.cashOnDelivery AS cash_ondelivery',
                'package.message AS message',
                'package.hourFrom AS hour_from',
                'package.hourUntil AS hour_until',
                'package.hourUntil AS hour_until',
                'package.retorno AS retorno',
                'package.rcs AS rcs',
                'package.vsec AS vsec',
                'package.dorig AS dorig',
                'package.createdAt AS date_add',
                'package.updatedAt AS date_upd',
            ])
            ->orderBy('package.createdAt', 'DESC');

        $this->applyShopRestriction($qb);

        if (!empty($filters['id_infopackage'])) {
            $qb->andWhere('package.id = :id')
                ->setParameter('id', (int) $filters['id_infopackage']);
        }

        if (!empty($filters['id_order'])) {
            $qb->andWhere('package.orderId = :orderId')
                ->setParameter('orderId', (int) $filters['id_order']);
        }

        if (!empty($filters['date_add'])) {
            $dateFilter = $filters['date_add'];

            if (is_array($dateFilter)) {
                if (!empty($dateFilter['from'])) {
                    $qb->andWhere('package.createdAt >= :fromDate')
                        ->setParameter('fromDate', new DateTimeImmutable($dateFilter['from'] . ' 00:00:00'));
                }

                if (!empty($dateFilter['to'])) {
                    $qb->andWhere('package.createdAt <= :toDate')
                        ->setParameter('toDate', new DateTimeImmutable($dateFilter['to'] . ' 23:59:59'));
                }
            } elseif (is_string($dateFilter) && '' !== $dateFilter) {
                $fromDate = new DateTimeImmutable($dateFilter . ' 00:00:00');
                $toDate = new DateTimeImmutable($dateFilter . ' 23:59:59');

                $qb->andWhere('package.createdAt BETWEEN :fromExactDate AND :toExactDate')
                    ->setParameter('fromExactDate', $fromDate)
                    ->setParameter('toExactDate', $toDate);
            }
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @param int $id
     *
     * @return int|null
     */
    public function getQuantityById(int $id): ?int
    {
        $result = $this->createQueryBuilder('package')
            ->select('package.quantity AS quantity')
            ->andWhere('package.id = :id')
            ->setParameter('id', $id)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return isset($result['quantity']) ? (int) $result['quantity'] : null;
    }

    public function getPackageByOrder(int $orderId, int $shopId): ?array
    {
        $packages = $this->findByOrder($orderId);
        $package = $packages[0] ?? null;

        if (!$package instanceof InfoPackage) {
            return null;
        }

        // Verify package belongs to the provided shop using ORM relation (InfoPackage->shops)
        $belongsToShop = false;
        foreach ($package->getShops() as $shopMapping) {
            if ($shopMapping->getShopId() === $shopId) {
                $belongsToShop = true;
                break;
            }
        }

        if (!$belongsToShop) {
            return null;
        }

        return [
            'id_infopackage' => $package->getId(),
            'id_order' => $package->getOrderId(),
            'id_reference_carrier' => $package->getReferenceCarrierId(),
            'id_type_shipment' => $package->getTypeShipment()?->getId(),
            'quantity' => $package->getQuantity(),
            'weight' => $package->getWeight(),
            'length' => $package->getLength(),
            'width' => $package->getWidth(),
            'height' => $package->getHeight(),
            'cash_ondelivery' => $package->getCashOnDelivery(),
            'message' => $package->getMessage(),
            'hour_from' => $package->getHourFrom()?->format('H:i:s'),
            'hour_until' => $package->getHourUntil()?->format('H:i:s'),
            'retorno' => $package->getRetorno(),
            'rcs' => $package->isRcsEnabled() ? 1 : 0,
            'vsec' => $package->getVsec(),
            'dorig' => $package->getDorig(),
            'date_add' => $package->getCreatedAt()?->format('Y-m-d H:i:s'),
            'date_upd' => $package->getUpdatedAt()?->format('Y-m-d H:i:s'),
            'id_shop' => $shopId,
        ];
    }

    private function applyShopRestriction(\Doctrine\ORM\QueryBuilder $qb): void
    {
        $shopIds = $this->getContextShopIds();

        if (empty($shopIds)) {
            return;
        }

        // Use ORM join against the InfoPackage->shops collection (InfoPackageShop entity)
        $rootAliases = $qb->getRootAliases();
        $alias = $rootAliases[0] ?? 'package';

        $qb->join(sprintf('%s.shops', $alias), 'ps')
            ->andWhere('ps.shopId IN (:shopIds)')
            ->setParameter('shopIds', $shopIds);
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

    // DBAL connection accessor removed; repository uses ORM relations for shop mapping.
}
