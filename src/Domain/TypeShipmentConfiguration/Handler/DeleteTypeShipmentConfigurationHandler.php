<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\TypeShipmentConfiguration\Handler;

use Roanja\Module\RjMulticarrier\Domain\TypeShipmentConfiguration\Command\DeleteTypeShipmentConfigurationCommand;
use Roanja\Module\RjMulticarrier\Domain\TypeShipmentConfiguration\Exception\TypeShipmentConfigurationNotFoundException;
use Roanja\Module\RjMulticarrier\Repository\CarrierConfigurationRepository;

final class DeleteTypeShipmentConfigurationHandler
{
    public function __construct(private readonly CarrierConfigurationRepository $configurationRepository)
    {
    }

    public function handle(DeleteTypeShipmentConfigurationCommand $command): void
    {
        $configuration = $this->configurationRepository->find($command->getConfigurationId());

        if (null === $configuration) {
            throw TypeShipmentConfigurationNotFoundException::forId($command->getConfigurationId());
        }

        $this->configurationRepository->remove($configuration);
    }
}
