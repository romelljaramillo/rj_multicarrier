<?php
/**
 * Handles deletion of carrier configuration entries.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\CarrierConfiguration\Handler;

use Roanja\Module\RjMulticarrier\Domain\CarrierConfiguration\Command\DeleteCarrierConfigurationCommand;
use Roanja\Module\RjMulticarrier\Domain\CarrierConfiguration\Exception\CarrierConfigurationNotFoundException;
use Roanja\Module\RjMulticarrier\Repository\CarrierConfigurationRepository;

final class DeleteCarrierConfigurationHandler
{
    public function __construct(private readonly CarrierConfigurationRepository $configurationRepository)
    {
    }

    public function handle(DeleteCarrierConfigurationCommand $command): void
    {
        $configuration = $this->configurationRepository->find($command->getConfigurationId());

        if (null === $configuration) {
            throw CarrierConfigurationNotFoundException::forId($command->getConfigurationId());
        }

        $this->configurationRepository->remove($configuration);
    }
}
