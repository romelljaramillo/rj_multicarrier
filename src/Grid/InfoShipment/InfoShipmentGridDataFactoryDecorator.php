<?php
/**
 * Grid data decorator to enrich info shipment rows with CSRF tokens.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Grid\InfoShipment;

use PrestaShop\PrestaShop\Core\Grid\Data\Factory\GridDataFactoryInterface;
use PrestaShop\PrestaShop\Core\Grid\Data\GridData;
use PrestaShop\PrestaShop\Core\Grid\Record\RecordCollection;
use PrestaShop\PrestaShop\Core\Grid\Search\SearchCriteriaInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class InfoShipmentGridDataFactoryDecorator implements GridDataFactoryInterface
{
    public function __construct(
        private readonly GridDataFactoryInterface $infoShipmentDoctrineGridDataFactory,
        private readonly CsrfTokenManagerInterface $csrfTokenManager
    ) {
    }

    public function getData(SearchCriteriaInterface $searchCriteria)
    {
        $gridData = $this->infoShipmentDoctrineGridDataFactory->getData($searchCriteria);

        $normalizedRecords = [];

        foreach ($gridData->getRecords()->all() as $record) {
            $rawId = (int) ($record['id_info_shipment'] ?? 0);

            $record['id_info_shipment_raw'] = $rawId;
            $record['id_info_shipment'] = $rawId > 0 ? (string) $rawId : '-';

            $normalizedRecords[] = $record;
        }

        return new GridData(
            new RecordCollection($normalizedRecords),
            $gridData->getRecordsTotal(),
            $gridData->getQuery()
        );
    }
}
