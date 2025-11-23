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
        $this->applyContextRestrictions($qb);
        $this->applySorting($searchCriteria, $qb);

        $this->searchCriteriaApplicator->applyPagination($searchCriteria, $qb);

        return $qb;
    }

    public function getCountQueryBuilder(SearchCriteriaInterface $searchCriteria): QueryBuilder
    {
        $qb = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from($this->dbPrefix . 'rj_multicarrier_validation_rule', 'vr')
            ->leftJoin('vr', $this->dbPrefix . 'shop', 's', 'vr.shop_id = s.id_shop');

        $this->applyFilters($qb, $searchCriteria->getFilters(), 'count_');
        $this->applyContextRestrictions($qb);

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
            'shop_label' => 's.name',
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
                'vr.created_at',
                'vr.updated_at',
                's.name AS shop_name'
            )
            ->from($this->dbPrefix . 'rj_multicarrier_validation_rule', 'vr')
            ->leftJoin('vr', $this->dbPrefix . 'shop', 's', 'vr.shop_id = s.id_shop');
    }

    private function applyContextRestrictions(QueryBuilder $qb): void
    {
        if (!class_exists('\Shop')) {
            return;
        }

        $context = (int) \Shop::getContext();

        if ($context === \Shop::CONTEXT_SHOP) {
            $shopId = (int) \Shop::getContextShopID();

            if ($shopId > 0) {
                $qb->andWhere('(vr.shop_id = :context_shop_id OR vr.shop_id IS NULL)')
                    ->setParameter('context_shop_id', $shopId);
            }

            return;
        }

        $qb->andWhere('1 = 0');
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
