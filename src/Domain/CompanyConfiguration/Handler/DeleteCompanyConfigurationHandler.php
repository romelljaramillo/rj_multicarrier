<?php
/**
 * Handles deletion of company configuration entries.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\CompanyConfiguration\Handler;

use Roanja\Module\RjMulticarrier\Domain\CompanyConfiguration\Command\DeleteCompanyConfigurationCommand;
use Roanja\Module\RjMulticarrier\Domain\CompanyConfiguration\Exception\CompanyConfigurationNotFoundException;
use Roanja\Module\RjMulticarrier\Repository\CarrierConfigurationRepository;

final class DeleteCompanyConfigurationHandler
{
    public function __construct(private readonly CarrierConfigurationRepository $configurationRepository)
    {
    }

    public function handle(DeleteCompanyConfigurationCommand $command): void
    {
        $configuration = $this->configurationRepository->find($command->getConfigurationId());

        if (null === $configuration) {
            throw CompanyConfigurationNotFoundException::forId($command->getConfigurationId());
        }

        $this->configurationRepository->remove($configuration);
    }
}
