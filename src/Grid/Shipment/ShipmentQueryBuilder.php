<?php
/**
 * Doctrine query builder for the shipment grid.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Grid\Shipment;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use PrestaShop\PrestaShop\Core\Grid\Query\DoctrineQueryBuilderInterface;
use PrestaShop\PrestaShop\Core\Grid\Search\SearchCriteriaInterface;

final class ShipmentQueryBuilder implements DoctrineQueryBuilderInterface
{
    public function __construct(
        private readonly Connection $connection,
        private readonly string $dbPrefix
    ) {
    }

    public function getSearchQueryBuilder(SearchCriteriaInterface $searchCriteria)
    {
        $qb = $this->getBaseQueryBuilder();

        foreach ($searchCriteria->getFilters() as $filterName => $filterValue) {
            if (null === $filterValue || '' === $filterValue) {
                continue;
            }

            switch ($filterName) {
                case 'id_order':
                    $qb->andWhere('shipment.id_order = :id_order')
                        ->setParameter('id_order', (int) $filterValue);
                    break;
                case 'company_shortname':
                    $qb->andWhere('company.shortname LIKE :shortname')
                        ->setParameter('shortname', '%' . $filterValue . '%');
                    break;
                case 'reference_order':
                    $qb->andWhere('shipment.reference_order LIKE :reference_order')
                        ->setParameter('reference_order', '%' . $filterValue . '%');
                    break;
                case 'num_shipment':
                    $qb->andWhere('shipment.num_shipment LIKE :num_shipment')
                        ->setParameter('num_shipment', '%' . $filterValue . '%');
                    break;
                case 'product':
                    $qb->andWhere('shipment.product LIKE :product')
                        ->setParameter('product', '%' . $filterValue . '%');
                    break;
                case 'cash_ondelivery':
                    $qb->andWhere('info.cash_ondelivery = :cash_ondelivery')
                        ->setParameter('cash_ondelivery', $filterValue);
                    break;
                case 'date_add':
                    $qb->andWhere('DATE(shipment.date_add) = :date_add')
                        ->setParameter('date_add', $filterValue);
                    break;
                case 'printed':
                    $qb->andWhere('(SELECT MAX(label.print) FROM ' . $this->dbPrefix . 'rj_multicarrier_label label WHERE label.id_shipment = shipment.id_shipment) = :printed')
                        ->setParameter('printed', (int) $filterValue);
                    break;
            }
        }

        $orderBy = $searchCriteria->getOrderBy() ?: 'date_add';
        $orderWay = $searchCriteria->getOrderWay() ?: 'DESC';

        $allowedOrderBy = [
            'id_order' => 'shipment.id_order',
            'company_shortname' => 'company.shortname',
            'reference_order' => 'shipment.reference_order',
            'num_shipment' => 'shipment.num_shipment',
            'product' => 'shipment.product',
            'cash_ondelivery' => 'info.cash_ondelivery',
            'quantity' => 'info.quantity',
            'weight' => 'info.weight',
            'date_add' => 'shipment.date_add',
        ];

        if (isset($allowedOrderBy[$orderBy])) {
            $qb->orderBy($allowedOrderBy[$orderBy], $orderWay);
        } else {
            $qb->orderBy('shipment.date_add', 'DESC');
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
            ->from($this->dbPrefix . 'rj_multicarrier_shipment', 'shipment')
            ->innerJoin('shipment', $this->dbPrefix . 'rj_multicarrier_infopackage', 'info', 'shipment.id_infopackage = info.id_infopackage')
            ->innerJoin('shipment', $this->dbPrefix . 'rj_multicarrier_company', 'company', 'shipment.id_carrier_company = company.id_carrier_company')
            ->andWhere('shipment.`delete` = 0');

        foreach ($searchCriteria->getFilters() as $filterName => $filterValue) {
            if (null === $filterValue || '' === $filterValue) {
                continue;
            }

            switch ($filterName) {
                case 'id_order':
                    $qb->andWhere('shipment.id_order = :count_id_order')
                        ->setParameter('count_id_order', (int) $filterValue);
                    break;
                case 'company_shortname':
                    $qb->andWhere('company.shortname LIKE :count_shortname')
                        ->setParameter('count_shortname', '%' . $filterValue . '%');
                    break;
                case 'reference_order':
                    $qb->andWhere('shipment.reference_order LIKE :count_reference_order')
                        ->setParameter('count_reference_order', '%' . $filterValue . '%');
                    break;
                case 'num_shipment':
                    $qb->andWhere('shipment.num_shipment LIKE :count_num_shipment')
                        ->setParameter('count_num_shipment', '%' . $filterValue . '%');
                    break;
                case 'product':
                    $qb->andWhere('shipment.product LIKE :count_product')
                        ->setParameter('count_product', '%' . $filterValue . '%');
                    break;
                case 'cash_ondelivery':
                    $qb->andWhere('info.cash_ondelivery = :count_cash_ondelivery')
                        ->setParameter('count_cash_ondelivery', $filterValue);
                    break;
                case 'date_add':
                    $qb->andWhere('DATE(shipment.date_add) = :count_date_add')
                        ->setParameter('count_date_add', $filterValue);
                    break;
                case 'printed':
                    $subQuery = '(SELECT MAX(label.print) FROM ' . $this->dbPrefix . 'rj_multicarrier_label label WHERE label.id_shipment = shipment.id_shipment) = :count_printed';
                    $qb->andWhere($subQuery)
                        ->setParameter('count_printed', (int) $filterValue);
                    break;
            }
        }

        return $qb;
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
                $printedSubquery . ' AS printed'
            )
            ->from($this->dbPrefix . 'rj_multicarrier_shipment', 'shipment')
            ->innerJoin('shipment', $this->dbPrefix . 'rj_multicarrier_infopackage', 'info', 'shipment.id_infopackage = info.id_infopackage')
            ->innerJoin('shipment', $this->dbPrefix . 'rj_multicarrier_company', 'company', 'shipment.id_carrier_company = company.id_carrier_company')
            ->andWhere('shipment.`delete` = 0');
    }
}
