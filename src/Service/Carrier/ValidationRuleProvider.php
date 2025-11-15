<?php
/**
 * Provides access to configured carrier validation rules.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Service\Carrier;

use Roanja\Module\RjMulticarrier\Entity\ValidationRule;
use Roanja\Module\RjMulticarrier\Repository\ValidationRuleRepository;

final class ValidationRuleProvider
{
    /** @var array<string, array<int, array{priority:int,name:string,conditions:array<string, mixed>,actions:array<string, array<int>>}>> */
    private array $cache = [];

    public function __construct(private readonly ValidationRuleRepository $repository)
    {
    }

    /**
     * @return array<int, array{priority:int,name:string,conditions:array<string, mixed>,actions:array<string, array<int>>}>
     */
    public function getActiveRules(?int $shopId, ?int $shopGroupId): array
    {
        $cacheKey = sprintf('%s|%s', (string) ($shopGroupId ?? 'g0'), (string) ($shopId ?? 's0'));

        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $rules = $this->repository->findActiveRulesForContext($shopId, $shopGroupId);

        if (empty($rules)) {
            return $this->cache[$cacheKey] = [];
        }

        $normalized = array_map([$this, 'normalizeRule'], $rules);

        usort($normalized, static function (array $left, array $right): int {
            return $left['priority'] <=> $right['priority'];
        });

        return $this->cache[$cacheKey] = $normalized;
    }

    private function normalizeRule(ValidationRule $rule): array
    {
        return [
            'name' => $rule->getName(),
            'priority' => $rule->getPriority(),
            'conditions' => [
                'product_ids' => $this->toIntArray($rule->getProductIds()),
                'category_ids' => $this->toIntArray($rule->getCategoryIds()),
                'zone_ids' => $this->toIntArray($rule->getZoneIds()),
                'country_ids' => $this->toIntArray($rule->getCountryIds()),
                'min_weight' => $rule->getMinWeight(),
                'max_weight' => $rule->getMaxWeight(),
            ],
            'actions' => [
                'allow' => $this->toIntArray($rule->getAllowIds()),
                'deny' => $this->toIntArray($rule->getDenyIds()),
                'add' => $this->toIntArray($rule->getAddIds()),
                'prefer' => $this->toIntArray($rule->getPreferIds()),
            ],
        ];
    }

    /**
     * @param array<int|string, mixed> $value
     *
     * @return int[]
     */
    private function toIntArray(array $value): array
    {
        $normalized = [];

        foreach ($value as $item) {
            if (!is_numeric($item)) {
                continue;
            }

            $normalized[] = (int) $item;
        }

        return array_values(array_unique($normalized));
    }
}
