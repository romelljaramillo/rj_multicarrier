<?php
/**
 * Handles listing configurations for a carrier.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\CarrierConfiguration\Handler;

use Roanja\Module\RjMulticarrier\Domain\CarrierConfiguration\Query\GetCarrierConfigurationsForCarrier;
use Roanja\Module\RjMulticarrier\Domain\CarrierConfiguration\View\CarrierConfigurationView;
use Roanja\Module\RjMulticarrier\Repository\CarrierConfigurationRepository;
use Roanja\Module\RjMulticarrier\Repository\CarrierRepository;

final class GetCarrierConfigurationsForCarrierHandler
{
    public function __construct(
        private readonly CarrierRepository $carrierRepository,
        private readonly CarrierConfigurationRepository $configurationRepository,
    ) {
    }

    /**
     * @return CarrierConfigurationView[]
     */
    public function handle(GetCarrierConfigurationsForCarrier $query): array
    {
        $carrier = $this->carrierRepository->find($query->getCarrierId());

        if (null === $carrier) {
            return [];
        }

        $entries = $this->configurationRepository->findByCarrier($carrier);
        $views = [];

        foreach ($entries as $entry) {
            $views[] = new CarrierConfigurationView(
                $entry->getId(),
                $carrier->getId() ?? 0,
                $entry->getName(),
                $entry->getValue(),
                $entry->getCreatedAt()?->format('Y-m-d H:i:s'),
                $entry->getUpdatedAt()?->format('Y-m-d H:i:s')
            );
        }

        return $views;
    }
}
