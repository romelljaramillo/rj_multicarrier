<?php
/**
 * Doctrine query builder for the Configuration grid.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Grid\Configuration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use PrestaShop\PrestaShop\Adapter\Shop\Context as ShopContext;
use PrestaShop\PrestaShop\Core\Grid\Query\DoctrineQueryBuilderInterface;
use PrestaShop\PrestaShop\Core\Grid\Search\SearchCriteriaInterface;

final class ConfigurationQueryBuilder implements DoctrineQueryBuilderInterface
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
        $this->applyFilters($qb, $searchCriteria);
        $this->applySorting($qb, $searchCriteria);

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
            ->select('COUNT(DISTINCT i.id_configuration)')
            ->from($this->dbPrefix . 'rj_multicarrier_configuration', 'i')
            ->leftJoin('i', $this->dbPrefix . 'rj_multicarrier_configuration_shop', 'iss', 'iss.id_configuration = i.id_configuration')
            ->leftJoin('iss', $this->dbPrefix . 'shop', 's', 's.id_shop = iss.id_shop');

        $this->applyShopRestriction($qb);
        $this->applyFilters($qb, $searchCriteria, true);

        return $qb;
    }

    private function getBaseQueryBuilder(): QueryBuilder
    {
        return $this->connection->createQueryBuilder()
            ->select(
                'i.id_configuration',
                'i.firstname',
                'i.lastname',
                'i.company',
                'i.phone',
                'i.email',
                'i.active',
                'i.date_add',
                'i.date_upd',
                'GROUP_CONCAT(DISTINCT s.name ORDER BY s.name SEPARATOR ", ") AS shops',
                'GROUP_CONCAT(DISTINCT iss.id_shop ORDER BY iss.id_shop SEPARATOR ",") AS shop_ids'
            )
            ->from($this->dbPrefix . 'rj_multicarrier_configuration', 'i')
            ->leftJoin('i', $this->dbPrefix . 'rj_multicarrier_configuration_shop', 'iss', 'iss.id_configuration = i.id_configuration')
            ->leftJoin('iss', $this->dbPrefix . 'shop', 's', 's.id_shop = iss.id_shop')
            ->groupBy('i.id_configuration');
    }

    private function applyFilters(QueryBuilder $qb, SearchCriteriaInterface $criteria, bool $isCount = false): void
    {
        $filters = $criteria->getFilters();

        foreach ($filters as $filterName => $filterValue) {
            if ($this->isFilterEmpty($filterValue)) {
                continue;
            }

            switch ($filterName) {
                case 'id_configuration':
                    $value = $this->resolveScalarFilterValue($filterValue);
                    if (null !== $value) {
                        $qb->andWhere('i.id_configuration = :id_configuration')
                            ->setParameter('id_configuration', (int) $value);
                    }
                    break;
                case 'firstname':
                case 'lastname':
                case 'company':
                case 'phone':
                case 'email':
                    $value = $this->resolveScalarFilterValue($filterValue);
                    if (null !== $value) {
                        $qb->andWhere(sprintf('i.%s LIKE :%s', $filterName, $filterName))
                            ->setParameter($filterName, $this->createContainsPattern($value));
                    }
                    break;
                case 'shops':
                    $value = $this->resolveScalarFilterValue($filterValue);
                    if (null !== $value) {
                        $qb->andWhere('s.name LIKE :shops')
                            ->setParameter('shops', $this->createContainsPattern($value));
                    }
                    break;
                case 'active':
                    $value = $this->resolveScalarFilterValue($filterValue);
                    if (null !== $value) {
                        $qb->andWhere('i.active = :active')
                            ->setParameter('active', (int) ((bool) (int) $value));
                    }
                    break;
                case 'date_add':
                    $this->applyDateFilter($qb, $filterValue);
                    break;
                case 'ids':
                    $ids = $this->resolveIntegerArray($filterValue);
                    if (!empty($ids)) {
                        $qb->andWhere('i.id_configuration IN (:bulk_ids)')
                            ->setParameter('bulk_ids', $ids, Connection::PARAM_INT_ARRAY);
                    }
                    break;
                default:
                    break;
            }
        }
    }

    private function applySorting(QueryBuilder $qb, SearchCriteriaInterface $criteria): void
    {
        $allowed = [
            'id_configuration' => 'i.id_configuration',
            'firstname' => 'i.firstname',
            'lastname' => 'i.lastname',
            'company' => 'i.company',
            'phone' => 'i.phone',
            'email' => 'i.email',
            'shops' => 'shops',
            'active' => 'i.active',
            'date_add' => 'i.date_add',
        ];

        $orderBy = $criteria->getOrderBy();
        $orderWay = strtoupper($criteria->getOrderWay() ?? 'DESC');

        if (!in_array($orderWay, ['ASC', 'DESC'], true)) {
            $orderWay = 'DESC';
        }

        if (!isset($allowed[$orderBy])) {
            $orderBy = 'id_configuration';
        }

        $qb->orderBy($allowed[$orderBy], $orderWay);
    }

    private function applyShopRestriction(QueryBuilder $qb): void
    {
        $shopIds = $this->getContextShopIds();

        if (!empty($shopIds)) {
            $qb->andWhere('iss.id_shop IN (:context_shop_ids)')
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
                $shopIds = [$contextShopId];
            }
        }

        return array_values(array_unique(array_map('intval', $shopIds)));
    }

    private function createContainsPattern(string $value): string
    {
        return '%' . str_replace(['%', '_'], ['\\%', '\\_'], $value) . '%';
    }

    /**
     * @param mixed $filterValue
     */
    private function isFilterEmpty($filterValue): bool
    {
        if (null === $filterValue) {
            return true;
        }

        if (is_scalar($filterValue)) {
            return '' === trim((string) $filterValue);
        }

        if (is_array($filterValue)) {
            foreach ($filterValue as $value) {
                if (!$this->isFilterEmpty($value)) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    /**
     * @param mixed $filterValue
     */
    private function resolveScalarFilterValue($filterValue): ?string
    {
        if (is_array($filterValue)) {
            if (array_key_exists('value', $filterValue)) {
                return $this->resolveScalarFilterValue($filterValue['value']);
            }

            foreach ($filterValue as $value) {
                $scalar = $this->resolveScalarFilterValue($value);
                if (null !== $scalar) {
                    return $scalar;
                }
            }

            return null;
        }

        if (null === $filterValue) {
            return null;
        }

        $value = trim((string) $filterValue);

        return '' === $value ? null : $value;
    }

    /**
     * @param mixed $filterValue
     *
     * @return int[]
     */
    private function resolveIntegerArray($filterValue): array
    {
        if (!is_array($filterValue)) {
            $value = $this->resolveScalarFilterValue($filterValue);

            return null === $value ? [] : [(int) $value];
        }

        $values = [];
        foreach ($filterValue as $item) {
            $values = array_merge($values, $this->resolveIntegerArray($item));
        }

        $values = array_map(static fn (int $value): int => $value, $values);
        $values = array_filter($values, static fn (int $value): bool => $value > 0);

        return array_values(array_unique($values));
    }

    private function applyDateFilter(QueryBuilder $qb, $filterValue): void
    {
        $range = $this->resolveDateRangeFilter($filterValue);

        if (isset($range['from'])) {
            $qb->andWhere('i.date_add >= :date_add_from')
                ->setParameter('date_add_from', $range['from'] . ' 00:00:00');
        }

        if (isset($range['to'])) {
            $qb->andWhere('i.date_add <= :date_add_to')
                ->setParameter('date_add_to', $range['to'] . ' 23:59:59');
        }

        if (empty($range)) {
            $value = $this->resolveScalarFilterValue($filterValue);
            if (null !== $value) {
                $qb->andWhere('DATE(i.date_add) = :date_add_exact')
                    ->setParameter('date_add_exact', $value);
            }
        }
    }

    /**
     * @param mixed $filterValue
     *
     * @return array{from?:string,to?:string}
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
}
