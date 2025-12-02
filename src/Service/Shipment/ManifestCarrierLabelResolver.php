<?php
/**
 * Resolves display labels for carrier references used in manifests.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Service\Shipment;

use Roanja\Module\RjMulticarrier\Repository\CarrierRepository;

final class ManifestCarrierLabelResolver
{
    public function __construct(private readonly CarrierRepository $carrierRepository)
    {
    }

    /**
     * @param int[] $referenceIds
     *
     * @return array<int, string>
     */
    public function resolveLabels(array $referenceIds): array
    {
        $referenceIds = array_values(array_unique(array_filter(array_map('intval', $referenceIds), static fn (int $value): bool => $value > 0)));

        if (empty($referenceIds)) {
            return [];
        }

        $labels = [];
        $activeCarriers = $this->carrierRepository->getActiveReferenceCarriers();

        foreach ($activeCarriers as $carrier) {
            $referenceId = isset($carrier['id_reference']) ? (int) $carrier['id_reference'] : 0;
            if ($referenceId <= 0 || isset($labels[$referenceId])) {
                continue;
            }

            $name = trim((string) ($carrier['name'] ?? ''));
            $labels[$referenceId] = $name !== '' ? $name : sprintf('Carrier #%d', $referenceId);
        }

        foreach ($referenceIds as $referenceId) {
            if (!isset($labels[$referenceId])) {
                $labels[$referenceId] = sprintf('Carrier #%d', $referenceId);
            }
        }

        return $labels;
    }

    public function resolveLabel(?int $referenceId): ?string
    {
        if (null === $referenceId || $referenceId <= 0) {
            return null;
        }

        $labels = $this->resolveLabels([$referenceId]);

        return $labels[$referenceId] ?? null;
    }
}
