<?php
/**
 * Repository for carrier configuration values.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Roanja\Module\RjMulticarrier\Entity\CarrierConfiguration;
use Roanja\Module\RjMulticarrier\Entity\Company;
use Roanja\Module\RjMulticarrier\Entity\TypeShipment;

final class CarrierConfigurationRepository
{
    private EntityManagerInterface $entityManager;

    /**
     * @var EntityRepository<CarrierConfiguration>
     */
    private EntityRepository $repository;

    public function __construct(ManagerRegistry $registry)
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = $registry->getManager();
        $this->entityManager = $entityManager;
        $this->repository = $entityManager->getRepository(CarrierConfiguration::class);
    }

    /**
     * @return CarrierConfiguration[]
     */
    public function findByCompany(Company $company): array
    {
        return $this->repository->findBy(
            ['company' => $company, 'typeShipment' => null],
            ['name' => 'ASC']
        );
    }

    /**
     * @return CarrierConfiguration[]
     */
    public function findByTypeShipment(TypeShipment $typeShipment): array
    {
        return $this->repository->findBy(
            ['typeShipment' => $typeShipment],
            ['name' => 'ASC']
        );
    }

    public function findOneForCompanyByName(Company $company, string $name): ?CarrierConfiguration
    {
        return $this->repository->findOneBy([
            'company' => $company,
            'typeShipment' => null,
            'name' => $name,
        ]);
    }

    public function findOneForTypeShipmentByName(TypeShipment $typeShipment, string $name): ?CarrierConfiguration
    {
        return $this->repository->findOneBy([
            'typeShipment' => $typeShipment,
            'name' => $name,
        ]);
    }

    public function save(CarrierConfiguration $configuration): void
    {
        $this->entityManager->persist($configuration);
        $this->entityManager->flush();
    }

    public function find(int $id): ?CarrierConfiguration
    {
        return $this->repository->find($id);
    }

    public function remove(CarrierConfiguration $configuration): void
    {
        $this->entityManager->remove($configuration);
        $this->entityManager->flush();
    }

    /**
     * @return array<string, string|null>
     */
    public function getKeyValueForCompany(Company $company): array
    {
        $qb = $this->entityManager->createQueryBuilder()
            ->select('config.name, config.value')
            ->from(CarrierConfiguration::class, 'config')
            ->where('config.company = :company')
            ->andWhere('config.typeShipment IS NULL')
            ->setParameter('company', $company);

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
