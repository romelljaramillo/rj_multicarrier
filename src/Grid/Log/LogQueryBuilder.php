<?php
/**
 * Doctrine query builder for the carrier log grid.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Grid\Log;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use PrestaShop\PrestaShop\Adapter\Shop\Context as ShopContext;
use PrestaShop\PrestaShop\Core\Grid\Query\AbstractDoctrineQueryBuilder;
use PrestaShop\PrestaShop\Core\Grid\Query\DoctrineSearchCriteriaApplicator;
use PrestaShop\PrestaShop\Core\Grid\Search\SearchCriteriaInterface;

final class LogQueryBuilder extends AbstractDoctrineQueryBuilder
{
    public function __construct(
        Connection $connection,
        string $dbPrefix,
        private readonly ShopContext $shopContext,
        private readonly DoctrineSearchCriteriaApplicator $searchCriteriaApplicator
    ) {
        parent::__construct($connection, $dbPrefix);

    }

    public function getSearchQueryBuilder(SearchCriteriaInterface $searchCriteria)
    {
        $qb = $this->getBaseQueryBuilder();
        $this->applyShopRestriction($qb);
        $this->applyFilters($qb, $searchCriteria->getFilters());
        $this->applySorting($searchCriteria, $qb);
        $this->searchCriteriaApplicator->applyPagination($searchCriteria, $qb);

        return $qb;
    }

    public function getCountQueryBuilder(SearchCriteriaInterface $searchCriteria)
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from($this->dbPrefix . 'rj_multicarrier_log', 'log');

        $this->applyShopRestriction($qb);
        $this->applyFilters($qb, $searchCriteria->getFilters(), 'count_');

        return $qb;
    }

    /**
     * @param mixed $filterValue
     */
    private function isFilterEmpty($filterValue): bool
    {
        return [] === $this->collectScalarValues($filterValue);
    }

    private function resolveScalarFilterValue($filterValue): ?string
    {
        $values = $this->collectScalarValues($filterValue);

        return $values[0] ?? null;
    }

    /**
     * @param mixed $filterValue
     *
     * @return int[]
     */
    private function resolveShopFilterValues($filterValue): array
    {
        $values = $this->collectScalarValues($filterValue);
        $values = array_map('intval', $values);
        $values = array_filter($values, static fn (int $value): bool => $value > 0);

        return array_values(array_unique($values));
    }

    /**
     * @param mixed $filterValue
     *
     * @return array<string, string>
     */
    private function resolveDateRangeFilter($filterValue): array
    {
        if (is_array($filterValue) && array_key_exists('value', $filterValue)) {
            $filterValue = $filterValue['value'];
        }

        if (!is_array($filterValue)) {
            return [];
        }

        $range = [];

        if (array_key_exists('from', $filterValue)) {
            $from = $this->resolveScalarFilterValue($filterValue['from']);
            if (null !== $from) {
                $range['from'] = $from;
            }
        }

        if (array_key_exists('to', $filterValue)) {
            $to = $this->resolveScalarFilterValue($filterValue['to']);
            if (null !== $to) {
                $range['to'] = $to;
            }
        }

        return $range;
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
                case 'id_carrier_log':
                    $value = $this->resolveScalarFilterValue($filterValue);
                    if (null !== $value) {
                        $parameter = $parameterPrefix . 'id_carrier_log';
                        $qb->andWhere(sprintf('log.id_carrier_log = :%s', $parameter))
                            ->setParameter($parameter, (int) $value);
                    }
                    break;
                case 'name':
                    $value = $this->resolveScalarFilterValue($filterValue);
                    if (null !== $value) {
                        $parameter = $parameterPrefix . 'log_name';
                        $qb->andWhere(sprintf('log.name LIKE :%s', $parameter))
                            ->setParameter($parameter, $this->buildContainsPattern($value));
                    }
                    break;
                case 'id_order':
                    $value = $this->resolveScalarFilterValue($filterValue);
                    if (null !== $value) {
                        $parameter = $parameterPrefix . 'id_order';
                        $qb->andWhere(sprintf('log.id_order = :%s', $parameter))
                            ->setParameter($parameter, (int) $value);
                    }
                    break;
                case 'id_shop':
                    $shopIds = $this->resolveShopFilterValues($filterValue);
                    if (!empty($shopIds)) {
                        $parameter = $parameterPrefix . 'filter_shop_ids';
                        $qb->andWhere(sprintf('log.id_shop IN (:%s)', $parameter))
                            ->setParameter($parameter, $shopIds, Connection::PARAM_INT_ARRAY);
                    }
                    break;
                case 'date_add':
                    $this->applyDateFilter($qb, $filterValue, $parameterPrefix);
                    break;
            }
        }
    }

    private function applyDateFilter(QueryBuilder $qb, $filterValue, string $parameterPrefix): void
    {
        $range = $this->resolveDateRangeFilter($filterValue);

        if (isset($range['from'])) {
            $parameter = $parameterPrefix . 'date_add_from';
            $qb->andWhere(sprintf('log.date_add >= :%s', $parameter))
                ->setParameter($parameter, $range['from'] . ' 00:00:00');
        }

        if (isset($range['to'])) {
            $parameter = $parameterPrefix . 'date_add_to';
            $qb->andWhere(sprintf('log.date_add <= :%s', $parameter))
                ->setParameter($parameter, $range['to'] . ' 23:59:59');
        }

        if (empty($range)) {
            $value = $this->resolveScalarFilterValue($filterValue);
            if (null !== $value) {
                $parameter = $parameterPrefix . 'date_add';
                $qb->andWhere(sprintf('DATE(log.date_add) = :%s', $parameter))
                    ->setParameter($parameter, $value);
            }
        }
    }

    private function normalizeOrderWay(?string $orderWay): string
    {
        $orderWay = strtoupper($orderWay ?? 'DESC');

        return in_array($orderWay, ['ASC', 'DESC'], true) ? $orderWay : 'DESC';
    }

    private function applySorting(SearchCriteriaInterface $searchCriteria, QueryBuilder $qb): void
    {
        $allowedOrderBy = [
            'id_carrier_log' => 'log.id_carrier_log',
            'name' => 'log.name',
            'id_order' => 'log.id_order',
            'date_add' => 'log.date_add',
            'id_shop' => 'log.id_shop',
        ];

        $orderBy = $searchCriteria->getOrderBy();
        if (!isset($allowedOrderBy[$orderBy])) {
            $orderBy = 'date_add';
        }

        $qb->orderBy($allowedOrderBy[$orderBy], $this->normalizeOrderWay($searchCriteria->getOrderWay()));
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
        $escaped = str_replace('_', '\\_', $this->escapePercent($value));

        return '%' . $escaped . '%';
    }

    private function getBaseQueryBuilder(): QueryBuilder
    {
        $requestPreview = "IF(log.request IS NULL OR log.request = '', '', CONCAT(SUBSTRING(log.request, 1, 120), IF(CHAR_LENGTH(log.request) > 120, '...', '')))";
        $responsePreview = "IF(log.response IS NULL OR log.response = '', '', CONCAT(SUBSTRING(log.response, 1, 120), IF(CHAR_LENGTH(log.response) > 120, '...', '')))";

        return $this->connection->createQueryBuilder()
            ->select(
                'log.id_carrier_log',
                'log.name',
                'log.id_order',
                'log.id_shop',
                'shop.name AS shop_name',
                'log.date_add',
                'log.date_upd',
                $requestPreview . ' AS request_preview',
                $responsePreview . ' AS response_preview'
            )
            ->from($this->dbPrefix . 'rj_multicarrier_log', 'log')
            ->innerJoin('log', $this->dbPrefix . 'shop', 'shop', 'shop.id_shop = log.id_shop');
    }

    private function applyShopRestriction(QueryBuilder $qb): void
    {
        $shopIds = $this->getContextShopIds();

        if (!empty($shopIds)) {
            $qb->andWhere('log.id_shop IN (:context_shop_ids)')
                ->setParameter('context_shop_ids', $shopIds, Connection::PARAM_INT_ARRAY);
        }
    }

    /**
     * @return int[]
     */
    private function getContextShopIds(): array
    {
        $shopIds = $this->shopContext->getContextListShopID();

        if (empty($shopIds)) {
            $contextShopId = $this->shopContext->getContextShopID(true);

            if (null !== $contextShopId) {
                $shopIds = [(int) $contextShopId];
            }
        }

        return array_map('intval', $shopIds);
    }
}
