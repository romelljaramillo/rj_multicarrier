<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\TypeShipmentConfiguration\Handler;

use Roanja\Module\RjMulticarrier\Domain\TypeShipmentConfiguration\Exception\TypeShipmentConfigurationNotFoundException;
use Roanja\Module\RjMulticarrier\Domain\TypeShipmentConfiguration\Query\GetTypeShipmentConfigurationForView;
use Roanja\Module\RjMulticarrier\Domain\TypeShipmentConfiguration\View\TypeShipmentConfigurationView;
use Roanja\Module\RjMulticarrier\Repository\CarrierConfigurationRepository;

final class GetTypeShipmentConfigurationForViewHandler
{
    public function __construct(private readonly CarrierConfigurationRepository $configurationRepository)
    {
    }

    public function handle(GetTypeShipmentConfigurationForView $query): TypeShipmentConfigurationView
    {
        $configuration = $this->configurationRepository->find($query->getConfigurationId());

        if (null === $configuration || null === $configuration->getTypeShipment()) {
            throw TypeShipmentConfigurationNotFoundException::forId($query->getConfigurationId());
        }

        $typeShipment = $configuration->getTypeShipment();
        $company = $configuration->getCompany();

        return new TypeShipmentConfigurationView(
            $configuration->getId(),
            $typeShipment->getId() ?? 0,
            $company?->getId() ?? 0,
            $configuration->getName(),
            $configuration->getValue(),
            $configuration->getCreatedAt()?->format('Y-m-d H:i:s'),
            $configuration->getUpdatedAt()?->format('Y-m-d H:i:s')
        );
    }
}
