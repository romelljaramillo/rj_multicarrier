<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\TypeShipmentConfiguration\Handler;

use DateTimeImmutable;
use Roanja\Module\RjMulticarrier\Domain\TypeShipmentConfiguration\Command\UpsertTypeShipmentConfigurationCommand;
use Roanja\Module\RjMulticarrier\Domain\TypeShipmentConfiguration\Exception\TypeShipmentConfigurationException;
use Roanja\Module\RjMulticarrier\Domain\TypeShipmentConfiguration\Exception\TypeShipmentConfigurationNotFoundException;
use Roanja\Module\RjMulticarrier\Entity\CarrierConfiguration;
use Roanja\Module\RjMulticarrier\Repository\CarrierConfigurationRepository;
use Roanja\Module\RjMulticarrier\Repository\TypeShipmentRepository;

final class UpsertTypeShipmentConfigurationHandler
{
    public function __construct(
        private readonly TypeShipmentRepository $typeShipmentRepository,
        private readonly CarrierConfigurationRepository $configurationRepository,
    ) {
    }

    public function handle(UpsertTypeShipmentConfigurationCommand $command): CarrierConfiguration
    {
        $typeShipment = $this->typeShipmentRepository->find($command->getTypeShipmentId());

        if (null === $typeShipment) {
            throw new TypeShipmentConfigurationException(sprintf('Type shipment with id %d was not found', $command->getTypeShipmentId()));
        }

        $configurationId = $command->getConfigurationId();
        $configuration = null !== $configurationId
            ? $this->configurationRepository->find($configurationId)
            : null;

        if (null !== $configurationId && null === $configuration) {
            throw TypeShipmentConfigurationNotFoundException::forId($configurationId);
        }

        if (null === $configuration) {
            $configuration = new CarrierConfiguration();
            $configuration
                ->setCarrier($typeShipment->getCarrier())
                ->setTypeShipment($typeShipment)
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
