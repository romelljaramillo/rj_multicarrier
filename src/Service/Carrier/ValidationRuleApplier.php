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
    public function __construct(private readonly ValidationRuleProvider $ruleProvider)
    {
    }

    /**
     * @param array<int|string, array<int, array<string, mixed>>> $packagesByAddress
     *
     * @return array<int|string, array<int, array<string, mixed>>>
     */
    public function apply(array $packagesByAddress, \Cart $cart): array
    {
        $rules = $this->ruleProvider->getActiveRules($cart->id_shop ?? null, $cart->id_shop_group ?? null);

        if (empty($rules)) {
            return $packagesByAddress;
        }

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
                $packagesByAddress[$addressId][$index]['carrier_list'] = $this->applyRulesToCarriers(
                    $package['carrier_list'] ?? [],
                    $rules,
                    $context
                );
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
    private function applyRulesToCarriers(array $carrierList, array $rules, array $context): array
    {
        $normalizedCarriers = $this->normalizeCarrierList($carrierList);
        $originalCarriers = $normalizedCarriers;

        foreach ($rules as $rule) {
            if (!$this->matchesRule($rule['conditions'], $context)) {
                continue;
            }

            $normalizedCarriers = $this->applyActions($normalizedCarriers, $rule['actions']);
        }

        if (empty($normalizedCarriers)) {
            $normalizedCarriers = $originalCarriers;
        }

        return $this->denormalizeCarrierList($normalizedCarriers);
    }

    /**
     * @param array<string, mixed> $conditions
     * @param array<string, mixed> $context
     */
    private function matchesRule(array $conditions, array $context): bool
    {
        if (!empty($conditions['product_ids'])) {
            if (!$this->hasIntersection($conditions['product_ids'], $context['product_ids'] ?? [])) {
                return false;
            }
        }

        if (!empty($conditions['category_ids'])) {
            if (!$this->hasIntersection($conditions['category_ids'], $context['category_ids'] ?? [])) {
                return false;
            }
        }

        if (!empty($conditions['zone_ids'])) {
            $zoneId = $context['zone_id'] ?? null;
            if (null === $zoneId || !in_array($zoneId, $conditions['zone_ids'], true)) {
                return false;
            }
        }

        if (!empty($conditions['country_ids'])) {
            $countryId = $context['country_id'] ?? null;
            if (null === $countryId || !in_array($countryId, $conditions['country_ids'], true)) {
                return false;
            }
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
     * @param array<int> $carrierIds
     * @param array<string, array<int>> $actions
     *
     * @return array<int>
     */
    private function applyActions(array $carrierIds, array $actions): array
    {
        $result = $carrierIds;

        if (!empty($actions['allow'])) {
            $result = array_values(array_intersect($result, $actions['allow']));
        }

        if (!empty($actions['add'])) {
            $result = array_values(array_unique(array_merge($result, $actions['add'])));
        }

        if (!empty($actions['deny'])) {
            $result = array_values(array_diff($result, $actions['deny']));
        }

        if (!empty($actions['prefer'])) {
            $preferred = array_values(array_intersect($actions['prefer'], $result));
            $others = array_values(array_diff($result, $preferred));
            $result = array_merge($preferred, $others);
        }

        return $result;
    }

    /**
     * @param array<int, mixed> $carrierList
     *
     * @return array<int>
     */
    private function normalizeCarrierList(array $carrierList): array
    {
        $normalized = [];

        foreach ($carrierList as $value) {
            if (is_array($value)) {
                continue;
            }

            if (!is_numeric($value)) {
                continue;
            }

            $normalized[] = (int) $value;
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param array<int> $carrierIds
     *
     * @return array<int, int>
     */
    private function denormalizeCarrierList(array $carrierIds): array
    {
        $carrierIds = array_values(array_unique($carrierIds));

        if (empty($carrierIds)) {
            return [];
        }

        return array_combine($carrierIds, $carrierIds);
    }
}
