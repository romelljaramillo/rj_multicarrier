<?php
/**
 * Repository for shipment entity operations.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Roanja\Module\RjMulticarrier\Entity\Shipment;

class ShipmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Shipment::class);
    }

    public function findOneByOrderId(int $orderId): ?Shipment
    {
        return $this->createQueryBuilder('shipment')
            ->andWhere('shipment.orderId = :orderId')
            ->andWhere('shipment.deleted = false')
            ->setParameter('orderId', $orderId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function getShipmentNumberByOrderId(int $orderId): ?string
    {
        $result = $this->createQueryBuilder('shipment')
            ->select('shipment.shipmentNumber')
            ->andWhere('shipment.orderId = :orderId')
            ->andWhere('shipment.deleted = false')
            ->setParameter('orderId', $orderId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result['shipmentNumber'] ?? null;
    }

    public function shipmentExistsByInfoPackage(int $infoPackageId): ?int
    {
        $result = $this->createQueryBuilder('shipment')
            ->select('shipment.id')
            ->andWhere('IDENTITY(shipment.infoPackage) = :infoPackageId')
            ->andWhere('shipment.deleted = false')
            ->setParameter('infoPackageId', $infoPackageId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result['id'] ?? null;
    }

    public function getInfoPackageIdByOrderId(int $orderId): ?int
    {
        $result = $this->createQueryBuilder('shipment')
            ->select('IDENTITY(shipment.infoPackage) AS infoPackageId')
            ->andWhere('shipment.orderId = :orderId')
            ->andWhere('shipment.deleted = false')
            ->setParameter('orderId', $orderId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result['infoPackageId'] ?? null;
    }

    public function softDelete(Shipment $shipment): void
    {
        $shipment->markDeleted();
        $this->_em->persist($shipment);
        $this->_em->flush();
    }
}
