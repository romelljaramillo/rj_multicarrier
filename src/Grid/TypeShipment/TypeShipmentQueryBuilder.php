<?php
/**
 * Doctrine query builder for the type shipment grid.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Grid\TypeShipment;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use PrestaShop\PrestaShop\Core\Grid\Query\DoctrineQueryBuilderInterface;
use PrestaShop\PrestaShop\Core\Grid\Search\SearchCriteriaInterface;

final class TypeShipmentQueryBuilder implements DoctrineQueryBuilderInterface
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
                case 'id_type_shipment':
                    $qb->andWhere('ts.id_type_shipment = :id_type_shipment')
                        ->setParameter('id_type_shipment', (int) $filterValue);
                    break;
                case 'company_name':
                    $qb->andWhere('c.name LIKE :company_name')
                        ->setParameter('company_name', '%' . $filterValue . '%');
                    break;
                case 'name':
                    $qb->andWhere('ts.name LIKE :name')
                        ->setParameter('name', '%' . $filterValue . '%');
                    break;
                case 'id_bc':
                    $qb->andWhere('ts.id_bc LIKE :id_bc')
                        ->setParameter('id_bc', '%' . $filterValue . '%');
                    break;
                case 'active':
                    $qb->andWhere('ts.active = :active')
                        ->setParameter('active', (int) $filterValue);
                    break;
                case 'company_id':
                    $qb->andWhere('ts.id_carrier_company = :company_id')
                        ->setParameter('company_id', (int) $filterValue);
                    break;
            }
        }

        $orderBy = $searchCriteria->getOrderBy() ?: 'id_type_shipment';
        $orderWay = $searchCriteria->getOrderWay() ?: 'DESC';

        $allowedOrderBy = [
            'id_type_shipment' => 'ts.id_type_shipment',
            'company_name' => 'c.name',
            'name' => 'ts.name',
            'id_bc' => 'ts.id_bc',
            'id_reference_carrier' => 'ts.id_reference_carrier',
            'active' => 'ts.active',
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
            ->from($this->dbPrefix . 'rj_multicarrier_type_shipment', 'ts')
            ->innerJoin('ts', $this->dbPrefix . 'rj_multicarrier_company', 'c', 'ts.id_carrier_company = c.id_carrier_company');

        foreach ($searchCriteria->getFilters() as $filterName => $filterValue) {
            if (null === $filterValue || '' === $filterValue) {
                continue;
            }

            switch ($filterName) {
                case 'id_type_shipment':
                    $qb->andWhere('ts.id_type_shipment = :count_id_type_shipment')
                        ->setParameter('count_id_type_shipment', (int) $filterValue);
                    break;
                case 'company_name':
                    $qb->andWhere('c.name LIKE :count_company_name')
                        ->setParameter('count_company_name', '%' . $filterValue . '%');
                    break;
                case 'name':
                    $qb->andWhere('ts.name LIKE :count_name')
                        ->setParameter('count_name', '%' . $filterValue . '%');
                    break;
                case 'id_bc':
                    $qb->andWhere('ts.id_bc LIKE :count_id_bc')
                        ->setParameter('count_id_bc', '%' . $filterValue . '%');
                    break;
                case 'active':
                    $qb->andWhere('ts.active = :count_active')
                        ->setParameter('count_active', (int) $filterValue);
                    break;
                case 'company_id':
                    $qb->andWhere('ts.id_carrier_company = :count_company_id')
                        ->setParameter('count_company_id', (int) $filterValue);
                    break;
            }
        }

        return $qb;
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
                'ts.id_carrier_company',
                'c.name AS company_name'
            )
            ->from($this->dbPrefix . 'rj_multicarrier_type_shipment', 'ts')
            ->innerJoin('ts', $this->dbPrefix . 'rj_multicarrier_company', 'c', 'ts.id_carrier_company = c.id_carrier_company');
    }
}
