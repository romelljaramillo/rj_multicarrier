<?php
/**
 * Repository for info package entities.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;
use Roanja\Module\RjMulticarrier\Entity\InfoPackage;

class InfoPackageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, InfoPackage::class);
    }

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
        /** @var Connection $connection */
        $connection = $this->getEntityManager()->getConnection();

        $sql = 'SELECT p.*, ps.id_shop FROM ' . _DB_PREFIX_ . 'rj_multicarrier_infopackage p
            LEFT JOIN ' . _DB_PREFIX_ . 'rj_multicarrier_infopackage_shop ps ON p.id_infopackage = ps.id_infopackage
            WHERE p.id_order = :orderId AND ps.id_shop = :shopId LIMIT 1';

        $row = $connection->fetchAssociative($sql, [
            'orderId' => $orderId,
            'shopId' => $shopId,
        ]);

        return $row ?: null;
    }
}
