<?php
/**
 * Doctrine query builder for the info package grid.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Grid\InfoPackage;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use PrestaShop\PrestaShop\Adapter\LegacyContext;
use PrestaShop\PrestaShop\Core\Grid\Query\DoctrineQueryBuilderInterface;
use PrestaShop\PrestaShop\Core\Grid\Search\SearchCriteriaInterface;

final class InfoPackageQueryBuilder implements DoctrineQueryBuilderInterface
{
    public function __construct(
    private readonly Connection $connection,
    private readonly LegacyContext $legacyContext,
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
                case 'id_infopackage':
                    $qb->andWhere('info.id_infopackage = :id_infopackage')
                        ->setParameter('id_infopackage', (int) $filterValue);
                    break;
                case 'id_order':
                    $qb->andWhere('info.id_order = :id_order')
                        ->setParameter('id_order', (int) $filterValue);
                    break;
                case 'reference_order':
                    $qb->andWhere('orders.reference LIKE :reference')
                        ->setParameter('reference', '%' . $filterValue . '%');
                    break;
                case 'carrier_name':
                    $qb->andWhere('carrier.name LIKE :carrier_name')
                        ->setParameter('carrier_name', '%' . $filterValue . '%');
                    break;
                case 'company_shortname':
                    $qb->andWhere('company.shortname LIKE :company_shortname')
                        ->setParameter('company_shortname', '%' . $filterValue . '%');
                    break;
                case 'cash_ondelivery':
                    $qb->andWhere('info.cash_ondelivery = :cod')
                        ->setParameter('cod', $filterValue);
                    break;
                case 'date_add':
                    $qb->andWhere('DATE(info.date_add) = :date_add')
                        ->setParameter('date_add', $filterValue);
                    break;
            }
        }

        $orderBy = $searchCriteria->getOrderBy() ?: 'date_add';
        $orderWay = $searchCriteria->getOrderWay() ?: 'DESC';

        $allowedOrderBy = [
            'id_infopackage' => 'info.id_infopackage',
            'id_order' => 'info.id_order',
            'reference_order' => 'orders.reference',
            'carrier_name' => 'carrier.name',
            'company_shortname' => 'company.shortname',
            'cash_ondelivery' => 'info.cash_ondelivery',
            'quantity' => 'info.quantity',
            'weight' => 'info.weight',
            'date_add' => 'info.date_add',
        ];

        if (isset($allowedOrderBy[$orderBy])) {
            $qb->orderBy($allowedOrderBy[$orderBy], $orderWay);
        } else {
            $qb->orderBy('info.date_add', 'DESC');
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
            ->from($this->dbPrefix . 'rj_multicarrier_infopackage', 'info')
            ->leftJoin('info', $this->dbPrefix . 'rj_multicarrier_infopackage_shop', 'info_shop', 'info_shop.id_infopackage = info.id_infopackage')
            ->leftJoin('info', $this->dbPrefix . 'rj_multicarrier_shipment', 'shipment', 'shipment.id_infopackage = info.id_infopackage AND shipment.`delete` = 0');

    $shopId = (int) $this->legacyContext->getContext()->shop->id;
        if ($shopId > 0) {
            $qb->andWhere('info_shop.id_shop = :count_shop_id')
                ->setParameter('count_shop_id', $shopId);
        }

        $qb->andWhere('shipment.id_shipment IS NULL');

        foreach ($searchCriteria->getFilters() as $filterName => $filterValue) {
            if (null === $filterValue || '' === $filterValue) {
                continue;
            }

            switch ($filterName) {
                case 'id_infopackage':
                    $qb->andWhere('info.id_infopackage = :count_id_infopackage')
                        ->setParameter('count_id_infopackage', (int) $filterValue);
                    break;
                case 'id_order':
                    $qb->andWhere('info.id_order = :count_id_order')
                        ->setParameter('count_id_order', (int) $filterValue);
                    break;
                case 'date_add':
                    $qb->andWhere('DATE(info.date_add) = :count_date_add')
                        ->setParameter('count_date_add', $filterValue);
                    break;
            }
        }

        return $qb;
    }

    private function getBaseQueryBuilder(): QueryBuilder
    {
        $qb = $this->connection->createQueryBuilder()
            ->select(
                'info.id_infopackage',
                'info.id_order',
                'orders.reference AS reference_order',
                'info.cash_ondelivery',
                'info.quantity',
                'info.weight',
                'info.date_add',
                'carrier.name AS carrier_name',
                'company.shortname AS company_shortname'
            )
            ->from($this->dbPrefix . 'rj_multicarrier_infopackage', 'info')
            ->leftJoin('info', $this->dbPrefix . 'rj_multicarrier_infopackage_shop', 'info_shop', 'info_shop.id_infopackage = info.id_infopackage')
            ->leftJoin('info', $this->dbPrefix . 'carrier', 'carrier', 'carrier.id_reference = info.id_reference_carrier AND carrier.deleted = 0')
            ->leftJoin('info', $this->dbPrefix . 'orders', 'orders', 'orders.id_order = info.id_order')
            ->leftJoin('info', $this->dbPrefix . 'rj_multicarrier_type_shipment', 'type_shipment', 'type_shipment.id_type_shipment = info.id_type_shipment')
            ->leftJoin('type_shipment', $this->dbPrefix . 'rj_multicarrier_company', 'company', 'company.id_carrier_company = type_shipment.id_carrier_company')
            ->leftJoin('info', $this->dbPrefix . 'rj_multicarrier_shipment', 'shipment', 'shipment.id_infopackage = info.id_infopackage AND shipment.`delete` = 0');

    $shopId = (int) $this->legacyContext->getContext()->shop->id;
        if ($shopId > 0) {
            $qb->andWhere('info_shop.id_shop = :shop_id')
                ->setParameter('shop_id', $shopId);
        }

        $qb->andWhere('shipment.id_shipment IS NULL');

        return $qb;
    }
}
