<?php
/**
 * Grid data factory decorator for type shipments.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Grid\TypeShipment;

use PrestaShop\PrestaShop\Core\Grid\Data\Factory\GridDataFactoryInterface;
use PrestaShop\PrestaShop\Core\Grid\Data\GridData;
use PrestaShop\PrestaShop\Core\Grid\Record\RecordCollection;
use PrestaShop\PrestaShop\Core\Grid\Search\SearchCriteriaInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class TypeShipmentGridDataFactoryDecorator implements GridDataFactoryInterface
{
    public function __construct(
        private readonly GridDataFactoryInterface $typeShipmentDoctrineGridDataFactory,
        private readonly CsrfTokenManagerInterface $csrfTokenManager
    ) {
    }

    public function getData(SearchCriteriaInterface $searchCriteria)
    {
        $gridData = $this->typeShipmentDoctrineGridDataFactory->getData($searchCriteria);

        $recordsWithTokens = [];
        foreach ($gridData->getRecords() as $record) {
            $id = (int) ($record['id_type_shipment'] ?? 0);
            $companyId = (int) ($record['id_carrier'] ?? 0);

            $record['id_type_shipment'] = $id;
            $record['id_carrier'] = $companyId;
            $record['toggle_token'] = $this->csrfTokenManager
                ->getToken('toggle_type_shipment_' . $id)
                ->getValue();
            $record['delete_token'] = $this->csrfTokenManager
                ->getToken('delete_type_shipment_' . $id)
                ->getValue();

            $recordsWithTokens[] = $record;
        }

        return new GridData(
            new RecordCollection($recordsWithTokens),
            $gridData->getRecordsTotal(),
            $gridData->getQuery()
        );
    }
}
