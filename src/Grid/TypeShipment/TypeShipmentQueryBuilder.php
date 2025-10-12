<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Grid\TypeShipment;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use PrestaShop\PrestaShop\Core\Grid\Query\AbstractDoctrineQueryBuilder;
use PrestaShop\PrestaShop\Core\Grid\Query\DoctrineSearchCriteriaApplicator;
use PrestaShop\PrestaShop\Core\Grid\Search\SearchCriteriaInterface;

final class TypeShipmentQueryBuilder extends AbstractDoctrineQueryBuilder
{
    public function __construct(
        Connection $connection,
        string $dbPrefix,
        private readonly DoctrineSearchCriteriaApplicator $searchCriteriaApplicator
    ) {
        parent::__construct($connection, $dbPrefix);
    }

    public function getSearchQueryBuilder(SearchCriteriaInterface $searchCriteria)
    {
        $qb = $this->getBaseQueryBuilder();
        $this->applyFilters($qb, $searchCriteria->getFilters());

        $this->applySorting($searchCriteria, $qb);
        $this->searchCriteriaApplicator->applyPagination($searchCriteria, $qb);

        return $qb;
    }

    public function getCountQueryBuilder(SearchCriteriaInterface $searchCriteria)
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from($this->dbPrefix . 'rj_multicarrier_type_shipment', 'ts')
            ->innerJoin('ts', $this->dbPrefix . 'rj_multicarrier_carrier', 'c', 'ts.id_carrier = c.id_carrier');

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
                case 'id_type_shipment':
                    $value = $this->resolveScalarFilterValue($filterValue);
                    if (null !== $value) {
                        $parameter = $parameterPrefix . 'id_type_shipment';
                        $qb->andWhere(sprintf('ts.id_type_shipment = :%s', $parameter))
                            ->setParameter($parameter, (int) $value);
                    }
                    break;
                case 'company_name':
                    $value = $this->resolveScalarFilterValue($filterValue);
                    if (null !== $value) {
                        $parameter = $parameterPrefix . 'company_name';
                        $qb->andWhere(sprintf('c.name LIKE :%s', $parameter))
                            ->setParameter($parameter, $this->buildContainsPattern($value));
                    }
                    break;
                case 'name':
                    $value = $this->resolveScalarFilterValue($filterValue);
                    if (null !== $value) {
                        $parameter = $parameterPrefix . 'name';
                        $qb->andWhere(sprintf('ts.name LIKE :%s', $parameter))
                            ->setParameter($parameter, $this->buildContainsPattern($value));
                    }
                    break;
                case 'id_bc':
                    $value = $this->resolveScalarFilterValue($filterValue);
                    if (null !== $value) {
                        $parameter = $parameterPrefix . 'id_bc';
                        $qb->andWhere(sprintf('ts.id_bc LIKE :%s', $parameter))
                            ->setParameter($parameter, $this->buildContainsPattern($value));
                    }
                    break;
                case 'active':
                    $value = $this->resolveScalarFilterValue($filterValue);
                    if (null !== $value) {
                        $parameter = $parameterPrefix . 'active';
                        $qb->andWhere(sprintf('ts.active = :%s', $parameter))
                            ->setParameter($parameter, (int) $value);
                    }
                    break;
                case 'carrier_id':
                    $value = $this->resolveScalarFilterValue($filterValue);
                    if (null !== $value) {
                        $parameter = $parameterPrefix . 'carrier_id';
                        $qb->andWhere(sprintf('ts.id_carrier = :%s', $parameter))
                            ->setParameter($parameter, (int) $value);
                    }
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
            'id_type_shipment' => 'ts.id_type_shipment',
            'company_name' => 'c.name',
            'name' => 'ts.name',
            'id_bc' => 'ts.id_bc',
            'id_reference_carrier' => 'ts.id_reference_carrier',
            'active' => 'ts.active',
        ];

        $orderBy = $searchCriteria->getOrderBy();
        if (!isset($allowedOrderBy[$orderBy])) {
            $orderBy = 'id_type_shipment';
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

    protected function escapePercent(string $value): string
    {
        return str_replace('%', '\\%', $value);
    }

    private function getBaseQueryBuilder(): QueryBuilder
    {
        return $this->connection->createQueryBuilder()
            ->select(
                'ts.id_type_shipment',
                'ts.name',
                'ts.id_bc',
                'ts.id_reference_carrier',
                'ts.active',
                'ts.id_carrier',
                'c.name AS company_name'
            )
            ->from($this->dbPrefix . 'rj_multicarrier_type_shipment', 'ts')
            ->innerJoin('ts', $this->dbPrefix . 'rj_multicarrier_carrier', 'c', 'ts.id_carrier = c.id_carrier');
    }
}
