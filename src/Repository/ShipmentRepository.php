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

    public function shipmentExistsByInfoShipment(int $infoPackageId): ?int
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

    public function getInfoShipmentIdByOrderId(int $orderId): ?int
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

    public function delete(int $shipmentId): void
    {
        $shipment = $this->find($shipmentId);
        if ($shipment === null) {
            throw new \Exception(sprintf('Shipment with ID %d not found', $shipmentId));
        }

        $shipment->markDeleted();
        $this->_em->persist($shipment);
        $this->_em->flush();
    }

    public function softDelete(Shipment $shipment): void
    {
        $shipment->markDeleted();
        $this->_em->persist($shipment);
        $this->_em->flush();
    }

    /**
     * @return Shipment[]
     */
    public function findForManifest(\DateTimeImmutable $dateFrom, \DateTimeImmutable $dateTo, ?int $referenceCarrierId = null): array
    {
        $qb = $this->createQueryBuilder('shipment')
            ->addSelect('info', 'type', 'carrier')
            ->innerJoin('shipment.infoPackage', 'info')
            ->innerJoin('info.typeShipment', 'type')
            ->leftJoin('shipment.carrier', 'carrier')
            ->andWhere('shipment.deleted = false')
            ->andWhere('info.referenceCarrierId IS NOT NULL')
            ->andWhere('shipment.createdAt BETWEEN :dateFrom AND :dateTo')
            ->setParameter('dateFrom', $dateFrom)
            ->setParameter('dateTo', $dateTo)
            ->orderBy('shipment.createdAt', 'ASC');

        if (null !== $referenceCarrierId && $referenceCarrierId > 0) {
            $qb->andWhere('info.referenceCarrierId = :referenceCarrierId')
                ->setParameter('referenceCarrierId', $referenceCarrierId);
        }

        return $qb->getQuery()->getResult();
    }
}
