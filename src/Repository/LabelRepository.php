<?php
/**
 * Repository covering legacy label operations.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Roanja\Module\RjMulticarrier\Entity\Label;
use Roanja\Module\RjMulticarrier\Entity\Shipment;

class LabelRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Label::class);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findDetailedByShipmentId(int $shipmentId): array
    {
        return $this->createQueryBuilder('label')
            ->select('label', 'shipment')
            ->leftJoin('label.shipment', 'shipment')
            ->andWhere('shipment.id = :shipmentId')
            ->setParameter('shipmentId', $shipmentId)
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * @return array<int, array{ id: int, package_id: ?string, pdf: ?string }>
     */
    public function findPdfSummariesByShipmentId(int $shipmentId): array
    {
        $rows = $this->createQueryBuilder('label')
            ->select('label.id AS id', 'label.packageId AS package_id', 'label.pdf AS pdf')
            ->leftJoin('label.shipment', 'shipment')
            ->andWhere('shipment.id = :shipmentId')
            ->setParameter('shipmentId', $shipmentId)
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'package_id' => $row['package_id'] !== null ? (string) $row['package_id'] : null,
            'pdf' => $row['pdf'] !== null ? (string) $row['pdf'] : null,
        ], $rows);
    }

    /**
     * @return int[]
     */
    public function findIdsByShipmentId(int $shipmentId): array
    {
        $rows = $this->createQueryBuilder('label')
            ->select('label.id AS id')
            ->leftJoin('label.shipment', 'shipment')
            ->andWhere('shipment.id = :shipmentId')
            ->setParameter('shipmentId', $shipmentId)
            ->getQuery()
            ->getScalarResult();

        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }

    public function isPrinted(int $shipmentId): bool
    {
        $result = $this->createQueryBuilder('label')
            ->select('label.printed')
            ->leftJoin('label.shipment', 'shipment')
            ->andWhere('shipment.id = :shipmentId')
            ->setParameter('shipmentId', $shipmentId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return isset($result['printed']) ? (bool) $result['printed'] : false;
    }

    public function deleteByShipment(Shipment $shipment): void
    {
        $labels = $this->findBy(['shipment' => $shipment]);

        foreach ($labels as $label) {
            $this->_em->remove($label);
        }

        $this->_em->flush();
    }
}
