<?php
/**
 * Runtime provider that loads validation rules directly from the database
 * when the module service container is not available.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Service\Carrier;

final class RuntimeValidationRuleProvider implements ValidationRuleProviderInterface
{
    /**
     * @var array<string, array<int, array{priority:int,name:string,conditions:array<string, mixed>,actions:array<string, array<int>>}>>
     */
    private array $cache = [];

    public function __construct(private readonly CarrierReferenceResolver $referenceResolver)
    {
    }

    public function getActiveRules(?int $shopId): array
    {
        if (null === $shopId) {
            return [];
        }

        $cacheKey = sprintf('shop-%s', (string) $shopId);

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        if (!class_exists('\\Db')) {
            return $this->cache[$cacheKey] = [];
        }

        $rows = $this->fetchRuleRows($shopId);

        if (empty($rows)) {
            return $this->cache[$cacheKey] = [];
        }

        $rules = [];

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $rules[] = [
                'name' => (string) ($row['name'] ?? ''),
                'priority' => (int) ($row['priority'] ?? 0),
                'conditions' => [
                    'product_ids' => $this->decodeIntList($row['product_ids'] ?? null),
                    'category_ids' => $this->decodeIntList($row['category_ids'] ?? null),
                    'zone_ids' => $this->decodeIntList($row['zone_ids'] ?? null),
                    'country_ids' => $this->decodeIntList($row['country_ids'] ?? null),
                    'min_weight' => $this->toNullableFloat($row['min_weight'] ?? null),
                    'max_weight' => $this->toNullableFloat($row['max_weight'] ?? null),
                ],
                'actions' => [
                    'allow' => $this->normalizeReferences($row['allow_ids'] ?? null),
                    'deny' => $this->normalizeReferences($row['deny_ids'] ?? null),
                    'add' => $this->normalizeReferences($row['add_ids'] ?? null),
                ],
            ];
        }

        usort($rules, static fn (array $left, array $right) => $left['priority'] <=> $right['priority']);

        return $this->cache[$cacheKey] = $rules;
    }

    private function fetchRuleRows(int $shopId): array
    {
        $clauses = ['active = 1', sprintf('shop_id = %d', $shopId)];

        $sql = sprintf(
            'SELECT name, priority, product_ids, category_ids, zone_ids, country_ids, '
            . 'min_weight, max_weight, allow_ids, deny_ids, add_ids '
            . 'FROM `%1$srj_multicarrier_validation_rule` '
            . 'WHERE %2$s '
            . 'ORDER BY priority ASC',
            _DB_PREFIX_,
            implode(' AND ', $clauses)
        );

        return (array) \Db::getInstance()->executeS($sql);
    }

    private function decodeIntList(?string $value): array
    {
        if (null === $value || '' === $value) {
            return [];
        }

        $decoded = json_decode($value, true);

        if (!is_array($decoded)) {
            return [];
        }

        $normalized = [];

        foreach ($decoded as $item) {
            if (!is_numeric($item)) {
                continue;
            }

            $intValue = (int) $item;

            if ($intValue > 0) {
                $normalized[$intValue] = $intValue;
            }
        }

        return array_values($normalized);
    }

    private function normalizeReferences(?string $value): array
    {
        $ids = $this->decodeIntList($value);

        return empty($ids) ? [] : $this->referenceResolver->ensureReferences($ids);
    }

    private function toNullableFloat($value): ?float
    {
        if (!is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }
}
