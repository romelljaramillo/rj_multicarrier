<?php
/**
 * Doctrine query builder for the carrier grid.
 */

declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Grid\Carrier;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use PrestaShop\PrestaShop\Core\Grid\Query\DoctrineQueryBuilderInterface;
use PrestaShop\PrestaShop\Core\Grid\Search\SearchCriteriaInterface;

final class CarrierQueryBuilder implements DoctrineQueryBuilderInterface
{
    private Connection $connection;

    private string $dbPrefix;

    public function __construct(Connection $connection, string $dbPrefix)
    {
        $this->connection = $connection;
        $this->dbPrefix = $dbPrefix;
    }

    public function getSearchQueryBuilder(SearchCriteriaInterface $searchCriteria)
    {
        $qb = $this->getBaseQueryBuilder();

        foreach ($searchCriteria->getFilters() as $filterName => $filterValue) {
            if ($this->isFilterEmpty($filterValue)) {
                continue;
            }

            switch ($filterName) {
                case 'id_carrier':
                    $value = $this->resolveScalarFilterValue($filterValue);
                    if (null !== $value) {
                        $qb->andWhere('c.id_carrier = :id_carrier')
                            ->setParameter('id_carrier', (int) $value);
                    }
                    break;
                case 'name':
                    $value = $this->resolveScalarFilterValue($filterValue);
                    if (null !== $value) {
                        $qb->andWhere('c.name LIKE :name')
                            ->setParameter('name', $this->buildContainsPattern($value));
                    }
                    break;
                case 'shortname':
                    $value = $this->resolveScalarFilterValue($filterValue);
                    if (null !== $value) {
                        $qb->andWhere('c.shortname LIKE :shortname')
                            ->setParameter('shortname', $this->buildContainsPattern($value));
                    }
                    break;
            }
        }

        $orderBy = $searchCriteria->getOrderBy() ?: 'id_carrier';
        $orderWay = $searchCriteria->getOrderWay() ?: 'DESC';

        $allowedOrderBy = [
            'id_carrier' => 'c.id_carrier',
            'name' => 'c.name',
            'shortname' => 'c.shortname',
        ];

        if (isset($allowedOrderBy[$orderBy])) {
            $qb->orderBy($allowedOrderBy[$orderBy], $orderWay);
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
        $qb = $this->connection->createQueryBuilder();
        $qb
            ->select('COUNT(*)')
            ->from($this->dbPrefix . 'rj_multicarrier_carrier', 'c')
            ->where('c.`delete` = 0');

        foreach ($searchCriteria->getFilters() as $filterName => $filterValue) {
            if ($this->isFilterEmpty($filterValue)) {
                continue;
            }

            switch ($filterName) {
                case 'id_carrier':
                    $value = $this->resolveScalarFilterValue($filterValue);
                    if (null !== $value) {
                        $qb->andWhere('c.id_carrier = :count_id_carrier')
                            ->setParameter('count_id_carrier', (int) $value);
                    }
                    break;
                case 'name':
                    $value = $this->resolveScalarFilterValue($filterValue);
                    if (null !== $value) {
                        $qb->andWhere('c.name LIKE :count_name')
                            ->setParameter('count_name', $this->buildContainsPattern($value));
                    }
                    break;
                case 'shortname':
                    $value = $this->resolveScalarFilterValue($filterValue);
                    if (null !== $value) {
                        $qb->andWhere('c.shortname LIKE :count_shortname')
                            ->setParameter('count_shortname', $this->buildContainsPattern($value));
                    }
                    break;
            }
        }

        return $qb;
    }

    private function getBaseQueryBuilder(): QueryBuilder
    {
        return $this->connection->createQueryBuilder()
            ->select(
                'c.id_carrier',
                'c.name',
                'c.icon AS icon',
                'c.shortname'
            )
            ->from($this->dbPrefix . 'rj_multicarrier_carrier', 'c')
            ->where('c.`delete` = 0');
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

    private function buildContainsPattern(string $value): string
    {
        $escaped = str_replace('_', '\\_', $this->escapePercent($value));

        return '%' . $escaped . '%';
    }

    private function escapePercent(string $value): string
    {
        return str_replace('%', '\\%', $value);
    }
}
