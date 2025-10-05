<?php
/**
 * Doctrine query builder for the carrier log grid.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Grid\Log;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use PrestaShop\PrestaShop\Adapter\Shop\Context as ShopContext;
use PrestaShop\PrestaShop\Core\Grid\Query\DoctrineQueryBuilderInterface;
use PrestaShop\PrestaShop\Core\Grid\Search\SearchCriteriaInterface;

final class LogQueryBuilder implements DoctrineQueryBuilderInterface
{
    public function __construct(
        private readonly Connection $connection,
        private readonly string $dbPrefix,
        private readonly ShopContext $shopContext
    ) {
    }

    public function getSearchQueryBuilder(SearchCriteriaInterface $searchCriteria)
    {
        $qb = $this->getBaseQueryBuilder();
        $this->applyShopRestriction($qb);

        foreach ($searchCriteria->getFilters() as $filterName => $filterValue) {
            if ($this->isFilterEmpty($filterValue)) {
                continue;
            }

            switch ($filterName) {
                case 'id_carrier_log':
                    $value = $this->resolveScalarFilterValue($filterValue);
                    if (null === $value) {
                        break;
                    }

                    $qb->andWhere('log.id_carrier_log = :id_carrier_log')
                        ->setParameter('id_carrier_log', (int) $value);
                    break;
                case 'name':
                    $value = $this->resolveScalarFilterValue($filterValue);
                    if (null === $value) {
                        break;
                    }

                    $qb->andWhere('log.name LIKE :log_name')
                        ->setParameter('log_name', '%' . $value . '%');
                    break;
                case 'id_order':
                    $value = $this->resolveScalarFilterValue($filterValue);
                    if (null === $value) {
                        break;
                    }

                    $qb->andWhere('log.id_order = :id_order')
                        ->setParameter('id_order', (int) $value);
                    break;
                case 'id_shop':
                    $shopIds = $this->resolveShopFilterValues($filterValue);

                    if (!empty($shopIds)) {
                        $qb->andWhere('log.id_shop IN (:filter_shop_ids)')
                            ->setParameter('filter_shop_ids', $shopIds, Connection::PARAM_INT_ARRAY);
                    }
                    break;
                case 'date_add':
                    $range = $this->resolveDateRangeFilter($filterValue);

                    if (isset($range['from'])) {
                        $qb->andWhere('log.date_add >= :date_add_from')
                            ->setParameter('date_add_from', $range['from'] . ' 00:00:00');
                    }

                    if (isset($range['to'])) {
                        $qb->andWhere('log.date_add <= :date_add_to')
                            ->setParameter('date_add_to', $range['to'] . ' 23:59:59');
                    }

                    if (empty($range) && null !== ($value = $this->resolveScalarFilterValue($filterValue))) {
                        $qb->andWhere('DATE(log.date_add) = :date_add')
                            ->setParameter('date_add', $value);
                    }
                    break;
            }
        }

        $orderBy = $searchCriteria->getOrderBy() ?: 'date_add';
        $orderWay = $searchCriteria->getOrderWay() ?: 'DESC';

        $allowedOrderBy = [
            'id_carrier_log' => 'log.id_carrier_log',
            'name' => 'log.name',
            'id_order' => 'log.id_order',
            'date_add' => 'log.date_add',
            'id_shop' => 'log.id_shop',
        ];

        if (isset($allowedOrderBy[$orderBy])) {
            $qb->orderBy($allowedOrderBy[$orderBy], $orderWay);
        } else {
            $qb->orderBy('log.date_add', 'DESC');
        }

        if (null !== $searchCriteria->getOffset()) {
            $qb->setFirstResult($searchCriteria->getOffset());
        }

        if (null !== $searchCriteria->getLimit()) {
            $qb->setMaxResults($searchCriteria->getLimit());
        }

        return $qb;
    }

    public function getCountQueryBuilder(SearchCriteriaInterface $searchCriteria)
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from($this->dbPrefix . 'rj_multicarrier_log', 'log');

        $this->applyShopRestriction($qb);

        foreach ($searchCriteria->getFilters() as $filterName => $filterValue) {
            if ($this->isFilterEmpty($filterValue)) {
                continue;
            }

            switch ($filterName) {
                case 'id_carrier_log':
                    $value = $this->resolveScalarFilterValue($filterValue);
                    if (null === $value) {
                        break;
                    }

                    $qb->andWhere('log.id_carrier_log = :count_id_carrier_log')
                        ->setParameter('count_id_carrier_log', (int) $value);
                    break;
                case 'name':
                    $value = $this->resolveScalarFilterValue($filterValue);
                    if (null === $value) {
                        break;
                    }

                    $qb->andWhere('log.name LIKE :count_log_name')
                        ->setParameter('count_log_name', '%' . $value . '%');
                    break;
                case 'id_order':
                    $value = $this->resolveScalarFilterValue($filterValue);
                    if (null === $value) {
                        break;
                    }

                    $qb->andWhere('log.id_order = :count_id_order')
                        ->setParameter('count_id_order', (int) $value);
                    break;
                case 'id_shop':
                    $shopIds = $this->resolveShopFilterValues($filterValue);

                    if (!empty($shopIds)) {
                        $qb->andWhere('log.id_shop IN (:count_filter_shop_ids)')
                            ->setParameter('count_filter_shop_ids', $shopIds, Connection::PARAM_INT_ARRAY);
                    }
                    break;
                case 'date_add':
                    $range = $this->resolveDateRangeFilter($filterValue);

                    if (isset($range['from'])) {
                        $qb->andWhere('log.date_add >= :count_date_add_from')
                            ->setParameter('count_date_add_from', $range['from'] . ' 00:00:00');
                    }

                    if (isset($range['to'])) {
                        $qb->andWhere('log.date_add <= :count_date_add_to')
                            ->setParameter('count_date_add_to', $range['to'] . ' 23:59:59');
                    }

                    if (empty($range) && null !== ($value = $this->resolveScalarFilterValue($filterValue))) {
                        $qb->andWhere('DATE(log.date_add) = :count_date_add')
                            ->setParameter('count_date_add', $value);
                    }
                    break;
            }
        }

        return $qb;
    }

    /**
     * @param mixed $filterValue
     */
    private function isFilterEmpty($filterValue): bool
    {
        if (null === $filterValue) {
            return true;
        }

        if (is_array($filterValue)) {
            $filtered = array_filter($filterValue, static fn ($value) => '' !== $value && null !== $value && [] !== $value);

            return empty($filtered);
        }

        return '' === trim((string) $filterValue);
    }

    /**
     * @param mixed $filterValue
     */
    private function resolveScalarFilterValue($filterValue): ?string
    {
        if (is_array($filterValue)) {
            if (array_key_exists('value', $filterValue)) {
                $filterValue = $filterValue['value'];
            } elseif (!empty($filterValue)) {
                $first = reset($filterValue);
                $filterValue = is_array($first) && array_key_exists('value', $first) ? $first['value'] : $first;
            }
        }

        if (null === $filterValue) {
            return null;
        }

        $stringValue = is_scalar($filterValue) ? (string) $filterValue : null;

        if (null === $stringValue) {
            return null;
        }

        $stringValue = trim($stringValue);

        return '' === $stringValue ? null : $stringValue;
    }

    /**
     * @param mixed $filterValue
     *
     * @return int[]
     */
    private function resolveShopFilterValues($filterValue): array
    {
        if (is_array($filterValue)) {
            if (array_key_exists('value', $filterValue)) {
                $filterValue = $filterValue['value'];
            }
        }

        $values = is_array($filterValue) ? $filterValue : (null !== $filterValue && '' !== $filterValue ? [$filterValue] : []);

        $values = array_map('intval', $values);
        $values = array_filter($values, static fn (int $value): bool => $value > 0);

        return array_values($values);
    }

    /**
     * @param mixed $filterValue
     *
     * @return array<string, string>
     */
    private function resolveDateRangeFilter($filterValue): array
    {
        if (!is_array($filterValue)) {
            return [];
        }

        if (array_key_exists('value', $filterValue) && is_array($filterValue['value'])) {
            $filterValue = $filterValue['value'];
        }

        $range = [];

        if (!empty($filterValue['from'])) {
            $range['from'] = $filterValue['from'];
        }

        if (!empty($filterValue['to'])) {
            $range['to'] = $filterValue['to'];
        }

        return $range;
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
