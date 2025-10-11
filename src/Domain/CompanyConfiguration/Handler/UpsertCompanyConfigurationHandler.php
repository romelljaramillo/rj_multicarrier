<?php
/**
 * Handles creation and updates of company configuration entries.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\CompanyConfiguration\Handler;

use DateTimeImmutable;
use Roanja\Module\RjMulticarrier\Domain\CompanyConfiguration\Command\UpsertCompanyConfigurationCommand;
use Roanja\Module\RjMulticarrier\Domain\CompanyConfiguration\Exception\CompanyConfigurationException;
use Roanja\Module\RjMulticarrier\Domain\CompanyConfiguration\Exception\CompanyConfigurationNotFoundException;
use Roanja\Module\RjMulticarrier\Entity\CarrierConfiguration;
use Roanja\Module\RjMulticarrier\Repository\CarrierConfigurationRepository;
use Roanja\Module\RjMulticarrier\Repository\CompanyRepository;

final class UpsertCompanyConfigurationHandler
{
    public function __construct(
        private readonly CompanyRepository $companyRepository,
        private readonly CarrierConfigurationRepository $configurationRepository,
    ) {
    }

    public function handle(UpsertCompanyConfigurationCommand $command): CarrierConfiguration
    {
        $company = $this->companyRepository->find($command->getCompanyId());

        if (null === $company) {
            throw new CompanyConfigurationException(sprintf('Company with id %d was not found', $command->getCompanyId()));
        }

        $configurationId = $command->getConfigurationId();
        $configuration = null !== $configurationId
            ? $this->configurationRepository->find($configurationId)
            : null;

        if (null !== $configurationId && null === $configuration) {
            throw CompanyConfigurationNotFoundException::forId($configurationId);
        }

        if (null === $configuration) {
            $configuration = new CarrierConfiguration();
            $configuration
                ->setCompany($company)
                ->setTypeShipment(null)
                ->setName($command->getName())
                ->setCreatedAt(new DateTimeImmutable());
        }

        $configuration
            ->setValue($command->getValue())
            ->setUpdatedAt(new DateTimeImmutable());

        $this->configurationRepository->save($configuration);

        return $configuration;
    }
}
