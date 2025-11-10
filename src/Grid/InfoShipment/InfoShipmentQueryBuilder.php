<?php
/**
 * Doctrine query builder for the info shipment grid. Includes orders without
 * an associated info shipment so operators can create one before generating
 * shipments.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Grid\InfoShipment;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use PrestaShop\PrestaShop\Adapter\Shop\Context as ShopContext;
use PrestaShop\PrestaShop\Core\Grid\Query\AbstractDoctrineQueryBuilder;
use PrestaShop\PrestaShop\Core\Grid\Query\DoctrineSearchCriteriaApplicator;
use PrestaShop\PrestaShop\Core\Grid\Search\SearchCriteriaInterface;

final class InfoShipmentQueryBuilder extends AbstractDoctrineQueryBuilder
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
            ->from($this->dbPrefix . 'orders', 'orders')
            ->leftJoin('orders', $this->dbPrefix . 'rj_multicarrier_info_shipment', 'info', 'info.id_order = orders.id_order')
            ->leftJoin('orders', $this->dbPrefix . 'rj_multicarrier_shipment', 'shipment', 'shipment.id_order = orders.id_order AND shipment.`delete` = 0')
            ->leftJoin('info', $this->dbPrefix . 'rj_multicarrier_type_shipment', 'type_shipment', 'type_shipment.id_type_shipment = info.id_type_shipment')
            ->leftJoin('info', $this->dbPrefix . 'rj_multicarrier_carrier', 'company', 'company.id_carrier = type_shipment.id_carrier')
            ->leftJoin('info', $this->dbPrefix . 'carrier', 'carrier', 'carrier.id_reference = info.id_reference_carrier AND carrier.deleted = 0');

        $this->applyShopRestriction($qb);
    $qb->andWhere('shipment.id_shipment IS NULL');
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
                case 'id_info_shipment':
                    $value = $this->resolveScalarFilterValue($filterValue);
                    if (null !== $value) {
                        $parameter = $parameterPrefix . 'id_info_shipment';
                        $qb->andWhere(sprintf('info.id_info_shipment = :%s', $parameter))
                            ->setParameter($parameter, (int) $value);
                    }
                    break;
                case 'id_order':
                    $value = $this->resolveScalarFilterValue($filterValue);
                    if (null !== $value) {
                        $parameter = $parameterPrefix . 'id_order';
                        $qb->andWhere(sprintf('orders.id_order = :%s', $parameter))
                            ->setParameter($parameter, (int) $value);
                    }
                    break;
                case 'reference_order':
                    $value = $this->resolveScalarFilterValue($filterValue);
                    if (null !== $value) {
                        $parameter = $parameterPrefix . 'reference_order';
                        $qb->andWhere(sprintf('orders.reference LIKE :%s', $parameter))
                            ->setParameter($parameter, $this->buildContainsPattern($value));
                    }
                    break;
                case 'carrier_name':
                    $value = $this->resolveScalarFilterValue($filterValue);
                    if (null !== $value) {
                        $parameter = $parameterPrefix . 'carrier_name';
                        $qb->andWhere(sprintf('carrier.name LIKE :%s', $parameter))
                            ->setParameter($parameter, $this->buildContainsPattern($value));
                    }
                    break;
                case 'company_shortname':
                    $value = $this->resolveScalarFilterValue($filterValue);
                    if (null !== $value) {
                        $parameter = $parameterPrefix . 'company_shortname';
                        $qb->andWhere(sprintf('company.shortname LIKE :%s', $parameter))
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
                case 'date_add':
                    $this->applyDateFilter($qb, $filterValue, $parameterPrefix);
                    break;
            }
        }
    }

    private function applyDateFilter(QueryBuilder $qb, $filterValue, string $parameterPrefix): void
    {
        $range = $this->resolveDateRangeFilter($filterValue);

        $dateExpression = 'COALESCE(info.date_add, orders.date_add)';

        if (isset($range['from'])) {
            $parameter = $parameterPrefix . 'date_add_from';
            $qb->andWhere(sprintf('%s >= :%s', $dateExpression, $parameter))
                ->setParameter($parameter, $range['from'] . ' 00:00:00');
        }

        if (isset($range['to'])) {
            $parameter = $parameterPrefix . 'date_add_to';
            $qb->andWhere(sprintf('%s <= :%s', $dateExpression, $parameter))
                ->setParameter($parameter, $range['to'] . ' 23:59:59');
        }

        if (empty($range)) {
            $value = $this->resolveScalarFilterValue($filterValue);
            if (null !== $value) {
                $parameter = $parameterPrefix . 'date_add';
                $qb->andWhere(sprintf('DATE(%s) = :%s', $dateExpression, $parameter))
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
            'id_info_shipment' => 'COALESCE(info.id_info_shipment, 0)',
            'id_order' => 'orders.id_order',
            'reference_order' => 'orders.reference',
            'carrier_name' => 'carrier.name',
            'company_shortname' => 'company.shortname',
            'cash_ondelivery' => 'info.cash_ondelivery',
            'quantity' => 'info.quantity',
            'weight' => 'info.weight',
            'date_add' => 'COALESCE(info.date_add, orders.date_add)',
        ];

        $orderBy = $searchCriteria->getOrderBy();
        if (!isset($allowedOrderBy[$orderBy])) {
            $orderBy = 'id_order';
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
        return $this->connection->createQueryBuilder()
            ->select(
                'COALESCE(info.id_info_shipment, 0) AS id_info_shipment',
                'orders.id_order',
                'orders.reference AS reference_order',
                'info.cash_ondelivery',
                'info.quantity',
                'info.weight',
                'COALESCE(info.date_add, orders.date_add) AS date_add',
                'carrier.name AS carrier_name',
                'company.shortname AS company_shortname'
            )
            ->from($this->dbPrefix . 'orders', 'orders')
            ->leftJoin('orders', $this->dbPrefix . 'rj_multicarrier_info_shipment', 'info', 'info.id_order = orders.id_order')
            ->leftJoin('orders', $this->dbPrefix . 'rj_multicarrier_shipment', 'shipment', 'shipment.id_order = orders.id_order AND shipment.`delete` = 0')
            ->leftJoin('info', $this->dbPrefix . 'rj_multicarrier_type_shipment', 'type_shipment', 'type_shipment.id_type_shipment = info.id_type_shipment')
            ->leftJoin('info', $this->dbPrefix . 'rj_multicarrier_carrier', 'company', 'company.id_carrier = type_shipment.id_carrier')
            ->leftJoin('info', $this->dbPrefix . 'carrier', 'carrier', 'carrier.id_reference = info.id_reference_carrier AND carrier.deleted = 0')
            ->andWhere('shipment.id_shipment IS NULL');
    }

    private function applyShopRestriction(QueryBuilder $qb): void
    {
        $shopIds = $this->getContextShopIds();

        if (!empty($shopIds)) {
            $qb->andWhere('orders.id_shop IN (:context_shop_ids)')
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
