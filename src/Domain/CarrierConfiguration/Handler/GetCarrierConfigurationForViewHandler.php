<?php
/**
 * Handles retrieving a single carrier configuration for view/edit.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\CarrierConfiguration\Handler;

use Roanja\Module\RjMulticarrier\Domain\CarrierConfiguration\Exception\CarrierConfigurationNotFoundException;
use Roanja\Module\RjMulticarrier\Domain\CarrierConfiguration\Query\GetCarrierConfigurationForView;
use Roanja\Module\RjMulticarrier\Domain\CarrierConfiguration\View\CarrierConfigurationView;
use Roanja\Module\RjMulticarrier\Repository\CarrierConfigurationRepository;

final class GetCarrierConfigurationForViewHandler
{
    public function __construct(private readonly CarrierConfigurationRepository $configurationRepository)
    {
    }

    public function handle(GetCarrierConfigurationForView $query): CarrierConfigurationView
    {
        $configuration = $this->configurationRepository->find($query->getConfigurationId());

        if (null === $configuration || null === $configuration->getCarrier()) {
            throw CarrierConfigurationNotFoundException::forId($query->getConfigurationId());
        }

        return new CarrierConfigurationView(
            $configuration->getId(),
            $configuration->getCarrier()->getId() ?? 0,
            $configuration->getName(),
            $configuration->getValue(),
            $configuration->getCreatedAt()?->format('Y-m-d H:i:s'),
            $configuration->getUpdatedAt()?->format('Y-m-d H:i:s')
        );
    }
}
