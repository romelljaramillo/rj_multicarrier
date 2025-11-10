<?php
/**
 * Repository for carrier configuration values.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Roanja\Module\RjMulticarrier\Entity\CarrierConfiguration;
use Roanja\Module\RjMulticarrier\Entity\Carrier;
use Roanja\Module\RjMulticarrier\Entity\TypeShipment;

final class CarrierConfigurationRepository extends ServiceEntityRepository
{
    private EntityManagerInterface $entityManager;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CarrierConfiguration::class);
        $this->entityManager = $this->getEntityManager();
    }

    /**
     * @return CarrierConfiguration[]
     */
    public function findByCarrier(Carrier $carrier): array
    {
        return $this->findBy(
            ['carrier' => $carrier, 'typeShipment' => null],
            ['name' => 'ASC']
        );
    }

    /**
     * @return CarrierConfiguration[]
     */
    public function findByTypeShipment(TypeShipment $typeShipment): array
    {
        return $this->findBy(
            ['typeShipment' => $typeShipment],
            ['name' => 'ASC']
        );
    }

    public function findOneForCarrierByName(Carrier $carrier, string $name): ?CarrierConfiguration
    {
        return $this->findOneBy([
            'carrier' => $carrier,
            'typeShipment' => null,
            'name' => $name,
        ]);
    }

    public function findOneForTypeShipmentByName(TypeShipment $typeShipment, string $name): ?CarrierConfiguration
    {
        return $this->findOneBy([
            'typeShipment' => $typeShipment,
            'name' => $name,
        ]);
    }

    public function save(CarrierConfiguration $configuration): void
    {
        $this->entityManager->persist($configuration);
        $this->entityManager->flush();
    }

    /**
     * @param mixed $id
     * @param int|null $lockMode
     * @param int|null $lockVersion
     */
    public function find($id, $lockMode = null, $lockVersion = null): ?CarrierConfiguration
    {
        $entity = parent::find($id, $lockMode, $lockVersion);

        return $entity instanceof CarrierConfiguration ? $entity : null;
    }

    public function remove(CarrierConfiguration $configuration): void
    {
        $this->entityManager->remove($configuration);
        $this->entityManager->flush();
    }

    /**
     * @return array<string, string|null>
     */
    public function getKeyValueForCarrier(Carrier $carrier): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('config.name, config.value')
            ->from(CarrierConfiguration::class, 'config')
            ->where('config.carrier = :carrier')
            ->andWhere('config.typeShipment IS NULL')
            ->setParameter('carrier', $carrier);

        $results = $qb->getQuery()->getArrayResult();

        $map = [];

        foreach ($results as $row) {
            $map[$row['name']] = $row['value'];
        }

        return $map;
    }

    /**
     * @return array<string, string|null>
     */
    public function getKeyValueForTypeShipment(TypeShipment $typeShipment): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('config.name, config.value')
            ->from(CarrierConfiguration::class, 'config')
            ->where('config.typeShipment = :typeShipment')
            ->setParameter('typeShipment', $typeShipment);

        $results = $qb->getQuery()->getArrayResult();

        $map = [];

        foreach ($results as $row) {
            $map[$row['name']] = $row['value'];
        }

        return $map;
    }
}
