<?php
/**
 * Doctrine query builder for the shipment grid.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Grid\Shipment;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use PrestaShop\PrestaShop\Adapter\Shop\Context as ShopContext;
use PrestaShop\PrestaShop\Core\Grid\Query\AbstractDoctrineQueryBuilder;
use PrestaShop\PrestaShop\Core\Grid\Query\DoctrineSearchCriteriaApplicator;
use PrestaShop\PrestaShop\Core\Grid\Search\SearchCriteriaInterface;

final class ShipmentQueryBuilder extends AbstractDoctrineQueryBuilder
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
            ->from($this->dbPrefix . 'rj_multicarrier_shipment', 'shipment')
            ->leftJoin('shipment', $this->dbPrefix . 'rj_multicarrier_shipment_shop', 'shipment_shop', 'shipment_shop.id_shipment = shipment.id_shipment')
            ->innerJoin('shipment', $this->dbPrefix . 'rj_multicarrier_info_shipment', 'info', 'shipment.id_info_shipment = info.id_info_shipment')
            ->innerJoin('shipment', $this->dbPrefix . 'rj_multicarrier_carrier', 'company', 'shipment.id_carrier = company.id_carrier')
            ->leftJoin('shipment', $this->dbPrefix . 'orders', 'order_table', 'shipment.id_order = order_table.id_order')
            ->leftJoin('order_table', $this->dbPrefix . 'shop', 'shop', 'order_table.id_shop = shop.id_shop')
            ->andWhere('shipment.`delete` = 0');

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
     * @param array<string, mixed> $filters
     */
    private function applyFilters(QueryBuilder $qb, array $filters, string $parameterPrefix = ''): void
    {
        foreach ($filters as $filterName => $filterValue) {
            if ($this->isFilterEmpty($filterValue)) {
                continue;
            }

            switch ($filterName) {
                case 'id_order':
                    $value = $this->resolveScalarFilterValue($filterValue);
                    if (null !== $value) {
                        $parameter = $parameterPrefix . 'id_order';
                        $qb->andWhere(sprintf('shipment.id_order = :%s', $parameter))
                            ->setParameter($parameter, (int) $value);
                    }
                    break;
                case 'company_shortname':
                    $value = $this->resolveScalarFilterValue($filterValue);
                    if (null !== $value) {
                        $parameter = $parameterPrefix . 'shortname';
                        $qb->andWhere(sprintf('company.shortname LIKE :%s', $parameter))
                            ->setParameter($parameter, $this->buildContainsPattern($value));
                    }
                    break;
                case 'reference_order':
                    $value = $this->resolveScalarFilterValue($filterValue);
                    if (null !== $value) {
                        $parameter = $parameterPrefix . 'reference_order';
                        $qb->andWhere(sprintf('shipment.reference_order LIKE :%s', $parameter))
                            ->setParameter($parameter, $this->buildContainsPattern($value));
                    }
                    break;
                case 'num_shipment':
                    $value = $this->resolveScalarFilterValue($filterValue);
                    if (null !== $value) {
                        $parameter = $parameterPrefix . 'num_shipment';
                        $qb->andWhere(sprintf('shipment.num_shipment LIKE :%s', $parameter))
                            ->setParameter($parameter, $this->buildContainsPattern($value));
                    }
                    break;
                case 'product':
                    $value = $this->resolveScalarFilterValue($filterValue);
                    if (null !== $value) {
                        $parameter = $parameterPrefix . 'product';
                        $qb->andWhere(sprintf('shipment.product LIKE :%s', $parameter))
                            ->setParameter($parameter, $this->buildContainsPattern($value));
                    }
                    break;
                case 'cash_ondelivery':
                    $value = $this->resolveScalarFilterValue($filterValue);
                    if (null !== $value) {
                        $parameter = $parameterPrefix . 'cash_ondelivery';
                        $qb->andWhere(sprintf('info.cash_ondelivery = :%s', $parameter))
                            ->setParameter($parameter, $value);
                    }
                    break;
                case 'printed':
                    $value = $this->resolveScalarFilterValue($filterValue);
                    if (null !== $value) {
                        $parameter = $parameterPrefix . 'printed';
                        $subQuery = sprintf('(SELECT MAX(label.print) FROM %srj_multicarrier_label label WHERE label.id_shipment = shipment.id_shipment) = :%s', $this->dbPrefix, $parameter);
                        $qb->andWhere($subQuery)
                            ->setParameter($parameter, (int) $value);
                    }
                    break;
                case 'id_shop':
                    $shopIds = $this->resolveShopFilterValues($filterValue);
                    if (!empty($shopIds)) {
                        $parameter = $parameterPrefix . 'filter_shop_ids';
                        $qb->andWhere(sprintf('shipment_shop.id_shop IN (:%s)', $parameter))
                            ->setParameter($parameter, $shopIds, Connection::PARAM_INT_ARRAY);
                    }
                    break;
                case 'date_add':
                    $this->applyDateFilter($qb, $filterValue, $parameterPrefix);
                    break;
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
            'id_shipment' => 'shipment.id_shipment',
            'id_order' => 'shipment.id_order',
            'shop_name' => 'shop.name',
            'company_shortname' => 'company.shortname',
            'reference_order' => 'shipment.reference_order',
            'num_shipment' => 'shipment.num_shipment',
            'product' => 'shipment.product',
            'cash_ondelivery' => 'info.cash_ondelivery',
            'quantity' => 'info.quantity',
            'weight' => 'info.weight',
            'date_add' => 'shipment.date_add',
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
        $printedSubquery = '(SELECT MAX(label.print) FROM ' . $this->dbPrefix . 'rj_multicarrier_label label WHERE label.id_shipment = shipment.id_shipment)';

        return $this->connection->createQueryBuilder()
            ->select(
                'shipment.id_shipment',
                'shipment.id_order',
                'shipment.reference_order',
                'shipment.num_shipment',
                'shipment.product',
                'shipment.date_add',
                'company.name AS company_name',
                'company.shortname AS company_shortname',
                'info.cash_ondelivery',
                'info.quantity',
                'info.weight',
                'shop.name AS shop_name',
                $printedSubquery . ' AS printed'
            )
            ->from($this->dbPrefix . 'rj_multicarrier_shipment', 'shipment')
            ->leftJoin('shipment', $this->dbPrefix . 'rj_multicarrier_shipment_shop', 'shipment_shop', 'shipment_shop.id_shipment = shipment.id_shipment')
            ->innerJoin('shipment', $this->dbPrefix . 'rj_multicarrier_info_shipment', 'info', 'shipment.id_info_shipment = info.id_info_shipment')
            ->innerJoin('shipment', $this->dbPrefix . 'rj_multicarrier_carrier', 'company', 'shipment.id_carrier = company.id_carrier')
            ->leftJoin('shipment', $this->dbPrefix . 'orders', 'order_table', 'shipment.id_order = order_table.id_order')
            ->leftJoin('order_table', $this->dbPrefix . 'shop', 'shop', 'order_table.id_shop = shop.id_shop')
            ->andWhere('shipment.`delete` = 0');
    }

    private function applyShopRestriction(QueryBuilder $qb): void
    {
        $shopIds = $this->getContextShopIds();

        if (!empty($shopIds)) {
            $qb->andWhere('shipment_shop.id_shop IN (:context_shop_ids)')
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

    private function applyDateFilter(QueryBuilder $qb, $filterValue, string $parameterPrefix): void
    {
        $range = $this->resolveDateRangeFilter($filterValue);

        if (isset($range['from'])) {
            $parameter = $parameterPrefix . 'date_add_from';
            $qb->andWhere(sprintf('shipment.date_add >= :%s', $parameter))
                ->setParameter($parameter, $range['from'] . ' 00:00:00');
        }

        if (isset($range['to'])) {
            $parameter = $parameterPrefix . 'date_add_to';
            $qb->andWhere(sprintf('shipment.date_add <= :%s', $parameter))
                ->setParameter($parameter, $range['to'] . ' 23:59:59');
        }

        if (empty($range)) {
            $value = $this->resolveScalarFilterValue($filterValue);
            if (null !== $value) {
                $parameter = $parameterPrefix . 'date_add';
                $qb->andWhere(sprintf('DATE(shipment.date_add) = :%s', $parameter))
                    ->setParameter($parameter, $value);
            }
        }
    }
}
