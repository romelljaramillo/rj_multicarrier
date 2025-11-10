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
use Roanja\Module\RjMulticarrier\Service\Configuration\DefaultCarrierConfigurationSeeder;

final class GetCarrierConfigurationsForCarrierHandler
{
    public function __construct(
        private readonly CarrierRepository $carrierRepository,
        private readonly CarrierConfigurationRepository $configurationRepository,
        private readonly DefaultCarrierConfigurationSeeder $configurationSeeder,
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

        $this->configurationSeeder->seedForCarrier($carrier);

        $definitionsMap = [];
        foreach ($this->configurationSeeder->getDefaultDefinitionsForCarrier($carrier) as $definition) {
            if (!is_array($definition)) {
                continue;
            }

            $name = isset($definition['name']) ? (string) $definition['name'] : '';
            if ('' === $name) {
                continue;
            }

            $definitionsMap[$name] = $definition;
        }

        $entries = $this->configurationRepository->findByCarrier($carrier);
        $views = [];

        foreach ($entries as $entry) {
            $definition = $definitionsMap[$entry->getName()] ?? [];
            $isRequired = $definition['required'] ?? $entry->isRequired();

            $views[] = new CarrierConfigurationView(
                $entry->getId() ?? 0,
                (int) ($carrier->getId() ?? 0),
                $entry->getName(),
                $entry->getValue(),
                (bool) $isRequired,
                $definition['label'] ?? null,
                $definition['description'] ?? null,
                $entry->getCreatedAt()?->format('Y-m-d H:i:s'),
                $entry->getUpdatedAt()?->format('Y-m-d H:i:s')
            );
        }

        return $views;
    }
}
