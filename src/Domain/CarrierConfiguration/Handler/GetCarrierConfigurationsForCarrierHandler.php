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
        $names = [];
        foreach ($entries as $entry) {
            $names[$entry->getName()] = true;
        }

        $hasGenericTrackingUrl = isset($names['TRACKING_URL']);
        $views = [];

        foreach ($entries as $entry) {
            $name = $entry->getName();
            if ($hasGenericTrackingUrl) {
                $suffix = '_TRACKING_URL';
                $isLegacyTracking = strlen($name) >= strlen($suffix)
                    && substr($name, -strlen($suffix)) === $suffix
                    && $name !== 'TRACKING_URL';
                if ($isLegacyTracking) {
                    continue;
                }
            }

            $definition = $definitionsMap[$entry->getName()] ?? [];
            $isRequired = $definition['required'] ?? $entry->isRequired();
            $label = $definition['label'] ?? null;
            $description = $definition['description'] ?? null;
            if (null === $label) {
                $name = $entry->getName();
                $suffix = '_TRACKING_URL';
                $isTrackingUrl = strlen($name) >= strlen($suffix) && substr($name, -strlen($suffix)) === $suffix;
                if ($isTrackingUrl || 'TRACKING_URL' === $name) {
                    $label = 'URL seguimiento de envíos';
                    if (null === $description) {
                        $description = 'Ejemplo: http://ejemplo.com/track.php?num=@';
                    }
                }
            }

            $views[] = new CarrierConfigurationView(
                $entry->getId() ?? 0,
                (int) ($carrier->getId() ?? 0),
                $entry->getName(),
                $entry->getValue(),
                (bool) $isRequired,
                $label,
                $description,
                $entry->getFieldGroup() ?? ($definition['field_group'] ?? null),
                $definition['field_group_label'] ?? null,
                $entry->getFieldType() ?? ($definition['field_type'] ?? null),
                $entry->getPayloadDirection() ?? ($definition['payload_direction'] ?? null),
                $entry->getPayloadKey() ?? ($definition['payload_key'] ?? null),
                $entry->getPayloadSource() ?? ($definition['payload_source'] ?? null),
                $entry->getPosition(),
                $entry->isDynamic(),
                $entry->getCreatedAt()?->format('Y-m-d H:i:s'),
                $entry->getUpdatedAt()?->format('Y-m-d H:i:s')
            );
        }

        return $views;
    }
}
