<?php
/**
 * Handles creation and updates of carrier configuration entries.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\CarrierConfiguration\Handler;

use DateTimeImmutable;
use Roanja\Module\RjMulticarrier\Domain\CarrierConfiguration\Command\UpsertCarrierConfigurationCommand;
use Roanja\Module\RjMulticarrier\Domain\CarrierConfiguration\Exception\CarrierConfigurationException;
use Roanja\Module\RjMulticarrier\Domain\CarrierConfiguration\Exception\CarrierConfigurationNotFoundException;
use Roanja\Module\RjMulticarrier\Entity\CarrierConfiguration;
use Roanja\Module\RjMulticarrier\Repository\CarrierConfigurationRepository;
use Roanja\Module\RjMulticarrier\Repository\CarrierRepository;

final class UpsertCarrierConfigurationHandler
{
    public function __construct(
        private readonly CarrierRepository $carrierRepository,
        private readonly CarrierConfigurationRepository $configurationRepository,
    ) {
    }

    public function handle(UpsertCarrierConfigurationCommand $command): CarrierConfiguration
    {
        $carrier = $this->carrierRepository->find($command->getCarrierId());

        if (null === $carrier) {
            throw new CarrierConfigurationException(sprintf('Carrier with id %d was not found', $command->getCarrierId()));
        }

        $configurationId = $command->getConfigurationId();
        $configuration = null !== $configurationId
            ? $this->configurationRepository->find($configurationId)
            : null;

        if (null !== $configurationId && null === $configuration) {
            throw CarrierConfigurationNotFoundException::forId($configurationId);
        }

        if (null === $configuration) {
            $configuration = new CarrierConfiguration();
            $configuration
                ->setCarrier($carrier)
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
