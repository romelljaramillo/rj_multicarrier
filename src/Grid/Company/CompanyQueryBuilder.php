<?php
/**
 * Doctrine query builder for the company grid.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Grid\Company;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use PrestaShop\PrestaShop\Core\Grid\Query\DoctrineQueryBuilderInterface;
use PrestaShop\PrestaShop\Core\Grid\Search\SearchCriteriaInterface;

final class CompanyQueryBuilder implements DoctrineQueryBuilderInterface
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
            if (null === $filterValue || '' === $filterValue) {
                continue;
            }

            switch ($filterName) {
                case 'id_carrier_company':
                    $qb->andWhere('c.id_carrier_company = :id_carrier_company')
                        ->setParameter('id_carrier_company', (int) $filterValue);
                    break;
                case 'name':
                    $qb->andWhere('c.name LIKE :name')
                        ->setParameter('name', '%' . $filterValue . '%');
                    break;
                case 'shortname':
                    $qb->andWhere('c.shortname LIKE :shortname')
                        ->setParameter('shortname', '%' . $filterValue . '%');
                    break;
            }
        }

        $orderBy = $searchCriteria->getOrderBy() ?: 'id_carrier_company';
        $orderWay = $searchCriteria->getOrderWay() ?: 'DESC';

        $allowedOrderBy = [
            'id_carrier_company' => 'c.id_carrier_company',
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
            ->from($this->dbPrefix . 'rj_multicarrier_company', 'c');

        foreach ($searchCriteria->getFilters() as $filterName => $filterValue) {
            if (null === $filterValue || '' === $filterValue) {
                continue;
            }

            switch ($filterName) {
                case 'id_carrier_company':
                    $qb->andWhere('c.id_carrier_company = :count_id_carrier_company')
                        ->setParameter('count_id_carrier_company', (int) $filterValue);
                    break;
                case 'name':
                    $qb->andWhere('c.name LIKE :count_name')
                        ->setParameter('count_name', '%' . $filterValue . '%');
                    break;
                case 'shortname':
                    $qb->andWhere('c.shortname LIKE :count_shortname')
                        ->setParameter('count_shortname', '%' . $filterValue . '%');
                    break;
            }
        }

        return $qb;
    }

    private function getBaseQueryBuilder(): QueryBuilder
    {
        return $this->connection->createQueryBuilder()
            ->select(
                'c.id_carrier_company',
                'c.name',
                'c.icon AS icon',
                'c.shortname'
            )
            ->from($this->dbPrefix . 'rj_multicarrier_company', 'c');
    }
}
