<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Grid\ValidationRule;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use PrestaShop\PrestaShop\Core\Grid\Query\AbstractDoctrineQueryBuilder;
use PrestaShop\PrestaShop\Core\Grid\Query\DoctrineSearchCriteriaApplicator;
use PrestaShop\PrestaShop\Core\Grid\Search\SearchCriteriaInterface;

final class ValidationRuleQueryBuilder extends AbstractDoctrineQueryBuilder
{
    public function __construct(
        Connection $connection,
        string $dbPrefix,
        private readonly DoctrineSearchCriteriaApplicator $searchCriteriaApplicator
    ) {
        parent::__construct($connection, $dbPrefix);
    }

    public function getSearchQueryBuilder(SearchCriteriaInterface $searchCriteria): QueryBuilder
    {
        $qb = $this->getBaseQueryBuilder();
        $this->applyFilters($qb, $searchCriteria->getFilters());
        $this->applySorting($searchCriteria, $qb);

        $this->searchCriteriaApplicator->applyPagination($searchCriteria, $qb);

        return $qb;
    }

    public function getCountQueryBuilder(SearchCriteriaInterface $searchCriteria): QueryBuilder
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from($this->dbPrefix . 'rj_multicarrier_validation_rule', 'vr')
            ->leftJoin('vr', $this->dbPrefix . 'shop', 's', 'vr.shop_id = s.id_shop')
            ->leftJoin('vr', $this->dbPrefix . 'shop_group', 'sg', 'vr.shop_group_id = sg.id_shop_group');

        $this->applyFilters($qb, $searchCriteria->getFilters(), 'count_');

        return $qb;
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function applyFilters(QueryBuilder $qb, array $filters, string $parameterPrefix = ''): void
    {
        foreach ($filters as $filterName => $filterValue) {
            if ($this->isFilterEmpty($filterValue)) {
                continue;
            }

            switch ($filterName) {
                case 'id_validation_rule':
                    $value = $this->resolveScalarFilterValue($filterValue);
                    if (null !== $value) {
                        $parameter = $parameterPrefix . 'id_validation_rule';
                        $qb->andWhere(sprintf('vr.id_validation_rule = :%s', $parameter))
                            ->setParameter($parameter, (int) $value);
                    }
                    break;
                case 'name':
                    $value = $this->resolveScalarFilterValue($filterValue);
                    if (null !== $value) {
                        $parameter = $parameterPrefix . 'name';
                        $qb->andWhere(sprintf('vr.name LIKE :%s', $parameter))
                            ->setParameter($parameter, $this->buildContainsPattern($value));
                    }
                    break;
                case 'priority':
                    $value = $this->resolveScalarFilterValue($filterValue);
                    if (null !== $value) {
                        $parameter = $parameterPrefix . 'priority';
                        $qb->andWhere(sprintf('vr.priority = :%s', $parameter))
                            ->setParameter($parameter, (int) $value);
                    }
                    break;
                case 'active':
                    $value = $this->resolveScalarFilterValue($filterValue);
                    if (null !== $value) {
                        $parameter = $parameterPrefix . 'active';
                        $qb->andWhere(sprintf('vr.active = :%s', $parameter))
                            ->setParameter($parameter, (int) $value);
                    }
                    break;
                case 'scope':
                    $value = $this->resolveScalarFilterValue($filterValue);
                    if (null !== $value) {
                        [$shopGroupId, $shopId] = $this->parseScopeValue($value);
                        if (null !== $shopGroupId) {
                            $parameter = $parameterPrefix . 'shop_group_id';
                            $qb->andWhere(sprintf('vr.shop_group_id = :%s', $parameter))
                                ->setParameter($parameter, $shopGroupId);
                        }

                        if (null !== $shopId) {
                            $parameter = $parameterPrefix . 'shop_id';
                            $qb->andWhere(sprintf('vr.shop_id = :%s', $parameter))
                                ->setParameter($parameter, $shopId);
                        }
                    }
                    break;
            }
        }
    }

    private function applySorting(SearchCriteriaInterface $searchCriteria, QueryBuilder $qb): void
    {
        $allowed = [
            'id_validation_rule' => 'vr.id_validation_rule',
            'name' => 'vr.name',
            'priority' => 'vr.priority',
            'active' => 'vr.active',
            'scope' => 'vr.shop_id',
            'updated_at' => 'vr.updated_at',
        ];

        $orderBy = $searchCriteria->getOrderBy();

        if (!isset($allowed[$orderBy])) {
            $orderBy = 'priority';
        }

        $qb->orderBy($allowed[$orderBy], $this->normalizeOrderWay($searchCriteria->getOrderWay()));
    }

    private function normalizeOrderWay(?string $orderWay): string
    {
        $normalized = strtoupper($orderWay ?? 'ASC');

        return in_array($normalized, ['ASC', 'DESC'], true) ? $normalized : 'ASC';
    }

    private function getBaseQueryBuilder(): QueryBuilder
    {
        return $this->connection->createQueryBuilder()
            ->select(
                'vr.id_validation_rule',
                'vr.name',
                'vr.priority',
                'vr.active',
                'vr.shop_id',
                'vr.shop_group_id',
                'vr.created_at',
                'vr.updated_at',
                's.name AS shop_name',
                'sg.name AS shop_group_name'
            )
            ->from($this->dbPrefix . 'rj_multicarrier_validation_rule', 'vr')
            ->leftJoin('vr', $this->dbPrefix . 'shop', 's', 'vr.shop_id = s.id_shop')
            ->leftJoin('vr', $this->dbPrefix . 'shop_group', 'sg', 'vr.shop_group_id = sg.id_shop_group');
    }

    private function parseScopeValue(string $value): array
    {
        if (str_starts_with($value, 'group-')) {
            $id = (int) substr($value, 6);

            return [$id > 0 ? $id : null, null];
        }

        if (str_starts_with($value, 'shop-')) {
            $id = (int) substr($value, 5);

            return [null, $id > 0 ? $id : null];
        }

        return [null, null];
    }

    /**
     * @param mixed $filterValue
     */
    private function isFilterEmpty($filterValue): bool
    {
        return [] === $this->collectScalarValues($filterValue);
    }

    /**
     * @param mixed $filterValue
     */
    private function resolveScalarFilterValue($filterValue): ?string
    {
        $values = $this->collectScalarValues($filterValue);

        return $values[0] ?? null;
    }

    /**
     * @param mixed $value
     *
     * @return string[]
     */
    private function collectScalarValues($value): array
    {
        if (null === $value) {
            return [];
        }

        if (is_scalar($value)) {
            $stringValue = trim((string) $value);

            return '' === $stringValue ? [] : [$stringValue];
        }

        if (!is_array($value)) {
            return [];
        }

        $collected = [];

        foreach ($value as $item) {
            $collected = array_merge($collected, $this->collectScalarValues($item));
        }

        return $collected;
    }

    private function buildContainsPattern(string $value): string
    {
        $escaped = str_replace(['%', '_'], ['\\%', '\\_'], $value);

        return '%' . $escaped . '%';
    }
}
