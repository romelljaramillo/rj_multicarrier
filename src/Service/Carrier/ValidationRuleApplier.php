<?php
/**
 * Applies configured carrier validation rules to cart packages.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Service\Carrier;

if (!\class_exists('\\Address') && \class_exists('\\AddressCore')) {
    \class_alias('\\AddressCore', '\\Address');
}

final class ValidationRuleApplier
{
    /**
     * @var array<int|string, array<int, array{rule_names: string[], dimensions: string[]}>>
     */
    private array $lastBlockedSummaries = [];

    public function __construct(
        private readonly ValidationRuleProviderInterface $ruleProvider,
        private readonly CarrierReferenceResolver $referenceResolver
    )
    {
    }

    /**
     * @return array<int|string, array<int, array{rule_names: string[], dimensions: string[]}>>
     */
    public function getLastBlockedSummaries(): array
    {
        return $this->lastBlockedSummaries;
    }

    /**
     * @param array<int|string, array<int, array<string, mixed>>> $packagesByAddress
     *
     * @return array<int|string, array<int, array<string, mixed>>>
     */
    public function apply(array $packagesByAddress, \Cart $cart): array
    {
        $shopId = $this->toPositiveIntOrNull($cart->id_shop ?? null);
        $rules = $this->ruleProvider->getActiveRules($shopId);

        if (empty($rules)) {
            return $packagesByAddress;
        }

        $this->lastBlockedSummaries = [];

        $packageSnapshots = [];
        $ruleSetsByAddress = [];

        foreach ($packagesByAddress as $addressId => $packages) {
            $zoneId = null;
            $countryId = null;

            if ($addressId) {
                $zoneId = (int) \Address::getZoneById((int) $addressId) ?: null;

                $countryData = \Address::getCountryAndState((int) $addressId);
                if (is_array($countryData) && isset($countryData['id_country'])) {
                    $countryId = (int) $countryData['id_country'];
                }
            }

            foreach ($packages as $index => $package) {
                if (!is_array($package)) {
                    continue;
                }

                $context = $this->buildContext($package, $zoneId, $countryId);

                if (!isset($packageSnapshots[$addressId])) {
                    $packageSnapshots[$addressId] = [];
                }

                $packageSnapshots[$addressId][$index] = [
                    'package' => $package,
                    'context' => $context,
                ];

                $selection = $this->selectRuleSetForContext($rules, $context);

                if (empty($selection['rules'])) {
                    continue;
                }

                if (!isset($ruleSetsByAddress[$addressId])) {
                    $ruleSetsByAddress[$addressId] = [
                        'priority' => null,
                        'rules' => [],
                    ];
                }

                $currentPriority = $ruleSetsByAddress[$addressId]['priority'];

                if (null === $currentPriority || $selection['priority'] > $currentPriority) {
                    $ruleSetsByAddress[$addressId]['priority'] = $selection['priority'];
                    $ruleSetsByAddress[$addressId]['rules'] = $selection['rules'];
                } elseif ($selection['priority'] === $currentPriority) {
                    $ruleSetsByAddress[$addressId]['rules'] = $this->mergeRuleSets(
                        $ruleSetsByAddress[$addressId]['rules'],
                        $selection['rules']
                    );
                }
            }
        }

        foreach ($packageSnapshots as $addressId => $snapshots) {
            $forcedRules = $ruleSetsByAddress[$addressId]['rules'] ?? [];
            $forcedRules = empty($forcedRules) ? null : $forcedRules;

            foreach ($snapshots as $index => $snapshot) {
                $matchedRules = [];
                $hadInitialCarriers = false;

                $packagesByAddress[$addressId][$index]['carrier_list'] = $this->applyRulesToCarriers(
                    $snapshot['package']['carrier_list'] ?? [],
                    $rules,
                    $snapshot['context'],
                    $matchedRules,
                    $hadInitialCarriers,
                    $forcedRules
                );

                if ($hadInitialCarriers
                    && $this->isEmptyCarrierList($packagesByAddress[$addressId][$index]['carrier_list'] ?? [])
                    && !empty($matchedRules)
                ) {
                    $this->registerBlockedSummary((string) $addressId, $matchedRules);
                }
            }
        }

        return $packagesByAddress;
    }

    /**
     * @param array<string, mixed> $package
     *
     * @return array<string, mixed>
     */
    private function buildContext(array $package, ?int $zoneId, ?int $countryId): array
    {
        $productIds = [];
        $categoryIds = [];
        $weight = 0.0;

        foreach ($package['product_list'] ?? [] as $product) {
            if (!is_array($product)) {
                continue;
            }

            if (isset($product['id_product']) && is_numeric($product['id_product'])) {
                $productIds[] = (int) $product['id_product'];
            }

            if (isset($product['id_category_default']) && is_numeric($product['id_category_default'])) {
                $categoryIds[] = (int) $product['id_category_default'];
            }

            if (isset($product['weight']) && is_numeric($product['weight']) && isset($product['cart_quantity']) && is_numeric($product['cart_quantity'])) {
                $weight += (float) $product['weight'] * (int) $product['cart_quantity'];
            }
        }

        return [
            'product_ids' => array_values(array_unique($productIds)),
            'category_ids' => array_values(array_unique($categoryIds)),
            'zone_id' => $zoneId,
            'country_id' => $countryId,
            'weight' => $weight,
        ];
    }

    /**
     * @param array<int, array{name:string,priority:int,conditions:array<string, mixed>,actions:array<string, array<int>>}> $rules
     * @param array<string, mixed> $context
     *
     * @return array<int, int>
     */
    private function applyRulesToCarriers(
        array $carrierList,
        array $rules,
        array $context,
        ?array &$matchedRules = null,
        ?bool &$hadInitialCarriers = null,
        ?array $forcedRuleSet = null
    ): array
    {
        $matchedRules = [];
        $hadInitialCarriers = false;

        [$normalizedCarriers, $referenceToCarrier] = $this->normalizeCarrierList($carrierList);
        $hadInitialCarriers = !empty($normalizedCarriers);
        $selection = null !== $forcedRuleSet
            ? ['rules' => $forcedRuleSet]
            : $this->selectRuleSetForContext($rules, $context);

        $selectedRules = $selection['rules'] ?? [];

        $filteredCarriers = $normalizedCarriers;

        if (!empty($selectedRules)) {
            $matchedRules = array_map(static fn (array $item): array => $item['summary'], $selectedRules);

            foreach ($selectedRules as $item) {
                $filteredCarriers = $this->applyActions($filteredCarriers, $item['rule']['actions']);
            }
        }

        $resolvedCarriers = $this->denormalizeCarrierList($filteredCarriers, $referenceToCarrier);

        if (empty($resolvedCarriers) && !empty($normalizedCarriers)) {
            $resolvedCarriers = $this->denormalizeCarrierList($normalizedCarriers, $referenceToCarrier);
        }

        // PrestaShop core expects [0 => 0] as the sentinel when no carrier is available.
        return empty($resolvedCarriers) ? [0 => 0] : $resolvedCarriers;
    }

    /**
     * @param array<string, mixed> $conditions
     * @param array<string, mixed> $context
     */
    private function matchesRule(array $conditions, array $context): bool
    {
        $hasProductCondition = !empty($conditions['product_ids']);
        $hasCategoryCondition = !empty($conditions['category_ids']);
        $matchesProduct = $hasProductCondition
            && $this->hasIntersection($conditions['product_ids'], $context['product_ids'] ?? []);
        $matchesCategory = $hasCategoryCondition
            && $this->hasIntersection($conditions['category_ids'], $context['category_ids'] ?? []);

        if ($hasProductCondition && !$hasCategoryCondition && !$matchesProduct) {
            return false;
        }

        if ($hasCategoryCondition && !$hasProductCondition && !$matchesCategory) {
            return false;
        }

        if ($hasProductCondition && $hasCategoryCondition && !$matchesProduct && !$matchesCategory) {
            return false;
        }

        $zoneId = $context['zone_id'] ?? null;
        $countryId = $context['country_id'] ?? null;
        $hasZoneCondition = !empty($conditions['zone_ids']);
        $hasCountryCondition = !empty($conditions['country_ids']);

        $matchesZone = $hasZoneCondition && null !== $zoneId && in_array($zoneId, $conditions['zone_ids'], true);
        $matchesCountry = $hasCountryCondition && null !== $countryId && in_array($countryId, $conditions['country_ids'], true);

        if ($hasZoneCondition && !$hasCountryCondition && !$matchesZone) {
            return false;
        }

        if ($hasCountryCondition && !$hasZoneCondition && !$matchesCountry) {
            return false;
        }

        if ($hasZoneCondition && $hasCountryCondition && !$matchesZone && !$matchesCountry) {
            return false;
        }

        if (null !== ($conditions['min_weight'] ?? null)) {
            $weight = $context['weight'] ?? 0.0;
            if ($weight < (float) $conditions['min_weight']) {
                return false;
            }
        }

        if (null !== ($conditions['max_weight'] ?? null)) {
            $weight = $context['weight'] ?? 0.0;
            if ($weight > (float) $conditions['max_weight']) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<int> $left
     * @param array<int> $right
     */
    private function hasIntersection(array $left, array $right): bool
    {
        return !empty(array_intersect($left, $right));
    }

    /**
    * @param array<int> $carrierIds Carrier references.
    * @param array<string, array<int>> $actions Carrier references grouped by action key.
     *
     * @return array<int>
     */
    private function applyActions(array $carrierIds, array $actions): array
    {
        $result = $carrierIds;

        if (!empty($actions['allow'])) {
            $result = array_values(array_unique($actions['allow']));
        }

        if (!empty($actions['add'])) {
            $result = array_values(array_unique(array_merge($result, $actions['add'])));
        }

        if (!empty($actions['deny'])) {
            $result = array_values(array_diff($result, $actions['deny']));
        }

        return $result;
    }

    /**
     * @param array<int, mixed> $carrierList
     *
     * @return array{0: int[], 1: array<int, int>}
     */
    private function normalizeCarrierList(array $carrierList): array
    {
        $carrierIds = [];

        foreach ($carrierList as $key => $value) {
            if (is_array($value)) {
                if (isset($value['id_carrier']) && is_numeric($value['id_carrier'])) {
                    $carrierIds[] = (int) $value['id_carrier'];
                } elseif (is_numeric($key)) {
                    $carrierIds[] = (int) $key;
                }

                continue;
            }

            if (is_numeric($value)) {
                $carrierIds[] = (int) $value;
                continue;
            }

            if (is_numeric($key)) {
                $carrierIds[] = (int) $key;
            }
        }

        $carrierIds = array_values(array_unique(array_filter($carrierIds, static fn (int $id): bool => $id > 0)));

        if (empty($carrierIds)) {
            return [[], []];
        }

        $mapping = $this->referenceResolver->mapCarrierIdsToReferences($carrierIds);

        return [$mapping['references'], $mapping['map']];
    }

    /**
     * @param array<int> $referenceIds
     * @param array<int, int> $knownReferences
     *
     * @return array<int, int>
     */
    private function denormalizeCarrierList(array $referenceIds, array $knownReferences): array
    {
        $referenceIds = array_values(array_unique($referenceIds));

        if (empty($referenceIds)) {
            return [];
        }

        $resolved = [];
        $resolvedMap = $this->referenceResolver->mapReferencesToCarrierIds($referenceIds);

        foreach ($referenceIds as $referenceId) {
            if (isset($resolvedMap[$referenceId])) {
                $resolved[] = $resolvedMap[$referenceId];
                continue;
            }

            if (isset($knownReferences[$referenceId])) {
                $resolved[] = $knownReferences[$referenceId];
            }
        }

        $resolved = array_values(array_unique(array_filter($resolved, static fn (int $id): bool => $id > 0)));

        return empty($resolved) ? [] : array_combine($resolved, $resolved);
    }

    /**
     * @param array<int, array{name:string,priority:int,conditions:array<string, mixed>,actions:array<string, array<int>>}> $rules
     * @param array<string, mixed> $context
     *
     * @return array{rules: array<int, array{rule: array, summary: array}>, priority: ?int}
     */
    private function selectRuleSetForContext(array $rules, array $context): array
    {
        $bestPriority = null;
        $selectedRules = [];

        foreach ($rules as $rule) {
            if (!$this->matchesRule($rule['conditions'], $context)) {
                continue;
            }

            $priority = (int) ($rule['priority'] ?? 0);
            $summary = [
                'name' => (string) ($rule['name'] ?? ''),
                'dimensions' => $this->extractConditionDimensions($rule['conditions'] ?? []),
            ];

            if (null === $bestPriority || $priority > $bestPriority) {
                $bestPriority = $priority;
                $selectedRules = [[
                    'rule' => $rule,
                    'summary' => $summary,
                ]];
                continue;
            }

            if ($priority === $bestPriority) {
                $selectedRules[] = [
                    'rule' => $rule,
                    'summary' => $summary,
                ];
            }
        }

        return [
            'rules' => $selectedRules,
            'priority' => $bestPriority,
        ];
    }

    /**
     * @param array<int, array{rule: array, summary: array}> $base
     * @param array<int, array{rule: array, summary: array}> $extra
     *
     * @return array<int, array{rule: array, summary: array}>
     */
    private function mergeRuleSets(array $base, array $extra): array
    {
        if (empty($base)) {
            return $extra;
        }

        if (empty($extra)) {
            return $base;
        }

        $merged = $base;
        $hashes = [];

        foreach ($base as $item) {
            $hashes[$this->hashRule($item['rule'] ?? [])] = true;
        }

        foreach ($extra as $item) {
            $hash = $this->hashRule($item['rule'] ?? []);

            if (isset($hashes[$hash])) {
                continue;
            }

            $hashes[$hash] = true;
            $merged[] = $item;
        }

        return $merged;
    }

    private function hashRule(array $rule): string
    {
        return md5(serialize($rule));
    }

    private function registerBlockedSummary(string $addressId, array $matchedRules): void
    {
        $ruleNames = [];
        $dimensions = [];

        foreach ($matchedRules as $rule) {
            if (!empty($rule['name'])) {
                $ruleNames[] = (string) $rule['name'];
            }

            foreach ($rule['dimensions'] ?? [] as $dimension) {
                if (is_string($dimension) && $dimension !== '') {
                    $dimensions[] = $dimension;
                }
            }
        }

        $dimensions = array_values(array_unique($dimensions));

        if (empty($dimensions)) {
            return;
        }

        $ruleNames = array_values(array_unique(array_filter($ruleNames)));

        if (!isset($this->lastBlockedSummaries[$addressId])) {
            $this->lastBlockedSummaries[$addressId] = [];
        }

        $this->lastBlockedSummaries[$addressId][] = [
            'rule_names' => $ruleNames,
            'dimensions' => $dimensions,
        ];
    }

    private function extractConditionDimensions(array $conditions): array
    {
        $dimensions = [];

        if (!empty($conditions['product_ids'])) {
            $dimensions[] = 'product';
        }

        if (!empty($conditions['category_ids'])) {
            $dimensions[] = 'category';
        }

        if (!empty($conditions['zone_ids'])) {
            $dimensions[] = 'zone';
        }

        if (!empty($conditions['country_ids'])) {
            $dimensions[] = 'country';
        }

        if (null !== ($conditions['min_weight'] ?? null) || null !== ($conditions['max_weight'] ?? null)) {
            $dimensions[] = 'weight';
        }

        return $dimensions;
    }

    private function isEmptyCarrierList(array $carrierList): bool
    {
        return count($carrierList) === 1
            && isset($carrierList[0])
            && (int) $carrierList[0] === 0;
    }

    private function toPositiveIntOrNull($value): ?int
    {
        if (!is_numeric($value)) {
            return null;
        }

        $intValue = (int) $value;

        return $intValue > 0 ? $intValue : null;
    }
}
