<?php
/**
 * Helper to build form options for shipment types.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Form\TypeShipment;

use Roanja\Module\RjMulticarrier\Entity\Carrier;
use Roanja\Module\RjMulticarrier\Repository\CarrierRepository;
use Roanja\Module\RjMulticarrier\Repository\TypeShipmentRepository;
use Shop;

final class TypeShipmentFormOptionsProvider
{
    public function __construct(
        private readonly CarrierRepository $carrierRepository,
        private readonly TypeShipmentRepository $typeShipmentRepository
    ) {
    }

    /**
     * @return Carrier[]
     */
    public function getCompaniesForContext(): array
    {
        $companies = $this->carrierRepository->findAllOrdered();

        return $this->filterCarriersByContext($companies);
    }

    /**
     * @param Carrier[] $companies
     *
     * @return array<string, int>
     */
    public function buildCompanyChoices(array $companies, ?int $currentCompanyId, bool $restrictToCurrent = true): array
    {
        $choices = [];
        foreach ($companies as $company) {
            if (!$company instanceof Carrier || null === $company->getId()) {
                continue;
            }

            $choices[$company->getName()] = $company->getId();
        }

        if ($restrictToCurrent && null !== $currentCompanyId) {
            $filtered = array_filter($choices, static fn (int $id): bool => $id === $currentCompanyId);
            if (!empty($filtered)) {
                return $filtered;
            }
        }

        ksort($choices, SORT_NATURAL | SORT_FLAG_CASE);

        return $choices;
    }

    /**
     * @return array<string, int>
     */
    public function buildReferenceCarrierChoices(?int $currentReferenceCarrierId): array
    {
        $languageId = 1;
        if (class_exists('\\Context')) {
            $contextClass = '\\Context';
            $context = $contextClass::getContext();
            $language = $context->language ?? null;
            if ($language && isset($language->id)) {
                $languageId = (int) $language->id;
            }
        }

        $carriers = (array) \Carrier::getCarriers($languageId, true);
        $choices = [];

        $assignedReferences = $this->typeShipmentRepository->findAllReferenceCarrierIds();
        if (null !== $currentReferenceCarrierId) {
            $assignedReferences = array_filter(
                $assignedReferences,
                static fn (int $referenceId): bool => $referenceId !== $currentReferenceCarrierId
            );
        }
        $assignedReferences = array_flip($assignedReferences);

        $seenReferences = [];

        foreach ($carriers as $carrier) {
            if (!isset($carrier['id_reference'])) {
                continue;
            }

            $referenceId = (int) $carrier['id_reference'];

            if (isset($seenReferences[$referenceId])) {
                continue;
            }
            $seenReferences[$referenceId] = true;

            if (isset($assignedReferences[$referenceId])) {
                continue;
            }

            $carrierName = isset($carrier['name']) ? (string) $carrier['name'] : sprintf('Carrier #%d', $referenceId);
            $choices[$carrierName] = $referenceId;
        }

        if (null !== $currentReferenceCarrierId) {
            $currentName = $this->resolveCarrierNameByReference($currentReferenceCarrierId);
            if (null !== $currentName) {
                $choices[$currentName] = $currentReferenceCarrierId;
            }
        }

        ksort($choices, SORT_NATURAL | SORT_FLAG_CASE);

        return $choices;
    }

    /**
     * @param Carrier[] $carriers
     *
     * @return Carrier[]
     */
    private function filterCarriersByContext(array $carriers): array
    {
        $shopIds = $this->getContextShopIds();

        if (empty($shopIds)) {
            return array_values($carriers);
        }

        $filtered = array_filter($carriers, static function (Carrier $carrier) use ($shopIds): bool {
            if (!$carrier instanceof Carrier || null === $carrier->getId()) {
                return false;
            }

            $carrierShopIds = $carrier->getShopIds();
            if (empty($carrierShopIds)) {
                return true;
            }

            return count(array_intersect($shopIds, $carrierShopIds)) > 0;
        });

        return array_values($filtered);
    }

    /**
     * @return int[]
     */
    private function getContextShopIds(): array
    {
        if (!Shop::isFeatureActive()) {
            return $this->normalizeShopIds($this->resolveShopId());
        }

        $shopIds = Shop::getContextListShopID();

        if (empty($shopIds)) {
            $contextShopId = Shop::getContextShopID(true);
            if (null !== $contextShopId) {
                $shopIds = [$contextShopId];
            }
        }

        return $this->normalizeShopIds($shopIds);
    }

    private function resolveShopId(): int
    {
        $shopId = Shop::getContextShopID(true);

        return null !== $shopId ? (int) $shopId : 0;
    }

    /**
     * @param mixed $value
     *
     * @return int[]
     */
    private function normalizeShopIds($value): array
    {
        if (null === $value) {
            return [];
        }

        if (!is_array($value)) {
            $value = [$value];
        }

        $ids = array_map('intval', $value);
        $ids = array_filter($ids, static fn (int $id): bool => $id > 0);

        return array_values(array_unique($ids));
    }

    private function resolveCarrierNameByReference(int $referenceId): ?string
    {
        $carrier = call_user_func(['Carrier', 'getCarrierByReference'], $referenceId);

        if (is_array($carrier) && isset($carrier['name'])) {
            return (string) $carrier['name'];
        }

        if ($carrier instanceof \Carrier && property_exists($carrier, 'name')) {
            return (string) $carrier->name;
        }

        return sprintf('Carrier #%d', $referenceId);
    }
}
