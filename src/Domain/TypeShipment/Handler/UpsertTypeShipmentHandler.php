<?php
/**
 * Handler responsible for creating or updating type shipments.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\TypeShipment\Handler;

use Doctrine\ORM\EntityManagerInterface;
use Roanja\Module\RjMulticarrier\Domain\TypeShipment\Command\UpsertTypeShipmentCommand;
use Roanja\Module\RjMulticarrier\Domain\TypeShipment\Exception\TypeShipmentCarrierConflictException;
use Roanja\Module\RjMulticarrier\Domain\TypeShipment\Exception\TypeShipmentException;
use Roanja\Module\RjMulticarrier\Domain\TypeShipment\Exception\TypeShipmentNotFoundException;
use Roanja\Module\RjMulticarrier\Entity\Company;
use Roanja\Module\RjMulticarrier\Entity\TypeShipment;
use Roanja\Module\RjMulticarrier\Repository\CompanyRepository;
use Roanja\Module\RjMulticarrier\Repository\TypeShipmentRepository;

final class UpsertTypeShipmentHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CompanyRepository $companyRepository,
        private readonly TypeShipmentRepository $typeShipmentRepository
    ) {
    }

    public function handle(UpsertTypeShipmentCommand $command): TypeShipment
    {
        $company = $this->resolveCompany($command->getCompanyId());
        $typeShipment = $this->resolveTypeShipment($command, $company);

        $referenceCarrierId = $command->getReferenceCarrierId();
        if (null !== $referenceCarrierId) {
            $this->assertCarrierIsAvailable($referenceCarrierId, $typeShipment->getId());
        }

        $typeShipment
            ->setCompany($company)
            ->setName($command->getName())
            ->setBusinessCode($command->getBusinessCode())
            ->setReferenceCarrierId($referenceCarrierId)
            ->setActive($command->isActive());

        $this->entityManager->flush();

        return $typeShipment;
    }

    private function resolveCompany(int $companyId): Company
    {
        $company = $this->companyRepository->find($companyId);

        if (!$company instanceof Company) {
            throw new TypeShipmentException(sprintf('Company with id %d was not found.', $companyId));
        }

        return $company;
    }

    private function resolveTypeShipment(UpsertTypeShipmentCommand $command, Company $company): TypeShipment
    {
        $typeShipmentId = $command->getTypeShipmentId();

        if (null === $typeShipmentId) {
            $typeShipment = new TypeShipment($company, $command->getName(), $command->getBusinessCode());
            $typeShipment->setReferenceCarrierId($command->getReferenceCarrierId());
            $typeShipment->setActive($command->isActive());

            $this->entityManager->persist($typeShipment);

            return $typeShipment;
        }

        $typeShipment = $this->typeShipmentRepository->find($typeShipmentId);

        if (!$typeShipment instanceof TypeShipment) {
            throw TypeShipmentNotFoundException::fromId($typeShipmentId);
        }

        return $typeShipment;
    }

    private function assertCarrierIsAvailable(int $referenceCarrierId, ?int $currentTypeShipmentId): void
    {
        $existing = $this->typeShipmentRepository->findActiveByReferenceCarrier($referenceCarrierId);

        if (null === $existing) {
            return;
        }

        if (null !== $currentTypeShipmentId && $existing->getId() === $currentTypeShipmentId) {
            return;
        }

        throw TypeShipmentCarrierConflictException::fromReference($referenceCarrierId);
    }
}
