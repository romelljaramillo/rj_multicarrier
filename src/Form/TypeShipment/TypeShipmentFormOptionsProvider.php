<?php
/**
 * Helper to build form options for shipment types.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Form\TypeShipment;

use Roanja\Module\RjMulticarrier\Entity\Carrier;
use PrestaShop\PrestaShop\Adapter\Feature\MultistoreFeature;
use PrestaShop\PrestaShop\Adapter\Shop\Context as ShopContextAdapter;
use Roanja\Module\RjMulticarrier\Repository\CarrierRepository;
use Roanja\Module\RjMulticarrier\Repository\TypeShipmentRepository;

final class TypeShipmentFormOptionsProvider
{
    public function __construct(
        private readonly CarrierRepository $carrierRepository,
        private readonly TypeShipmentRepository $typeShipmentRepository,
        private readonly MultistoreFeature $multistoreFeature,
        private readonly ShopContextAdapter $shopContext
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

        $carrierReferenceIndex = $this->buildCarrierReferenceIndex($carriers);

        $assignedReferences = $this->typeShipmentRepository->findAllReferenceCarrierIds();
        $normalizedAssigned = $this->normalizeAssignedReferences($assignedReferences, $carrierReferenceIndex);

        if (null !== $currentReferenceCarrierId) {
            $normalizedKey = $carrierReferenceIndex[$currentReferenceCarrierId] ?? $currentReferenceCarrierId;
            unset($normalizedAssigned[$normalizedKey]);
        }

        $seenReferences = [];

        foreach ($carriers as $carrier) {
            if (!isset($carrier['id_reference'])) {
                continue;
            }

            $isActive = (int) ($carrier['active'] ?? 0);
            $isDeleted = (int) ($carrier['deleted'] ?? 0);

            if (1 !== $isActive || 0 !== $isDeleted) {
                continue;
            }

            $referenceId = (int) $carrier['id_reference'];

            if (isset($seenReferences[$referenceId])) {
                continue;
            }
            $seenReferences[$referenceId] = true;

            if (isset($normalizedAssigned[$referenceId])) {
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
     * @param array<int, array<string, mixed>> $carriers
     *
     * @return array<int, int>
     */
    private function buildCarrierReferenceIndex(array $carriers): array
    {
        $index = [];

        foreach ($carriers as $carrier) {
            $idCarrier = isset($carrier['id_carrier']) ? (int) $carrier['id_carrier'] : 0;
            $idReference = isset($carrier['id_reference']) ? (int) $carrier['id_reference'] : 0;

            if ($idCarrier > 0 && $idReference > 0) {
                $index[$idCarrier] = $idReference;
            }
        }

        return $index;
    }

    /**
     * @param int[] $assignedValues
     * @param array<int, int> $carrierReferenceIndex
     *
     * @return array<int, bool>
     */
    private function normalizeAssignedReferences(array $assignedValues, array $carrierReferenceIndex): array
    {
        $normalized = [];

        foreach ($assignedValues as $value) {
            $value = (int) $value;

            if ($value <= 0) {
                continue;
            }

            $referenceId = $carrierReferenceIndex[$value] ?? $value;
            $normalized[$referenceId] = true;
        }

        return $normalized;
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
        if (!$this->multistoreFeature->isUsed()) {
            return $this->normalizeShopIds($this->resolveShopId());
        }

        $shopIds = $this->shopContext->getContextListShopID();

        if (empty($shopIds)) {
            $contextShopId = $this->shopContext->getContextShopID(true);
            if (null !== $contextShopId) {
                $shopIds = [$contextShopId];
            }
        }

        return $this->normalizeShopIds($shopIds);
    }

    private function resolveShopId(): int
    {
        $shopId = $this->shopContext->getContextShopID(true);

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
