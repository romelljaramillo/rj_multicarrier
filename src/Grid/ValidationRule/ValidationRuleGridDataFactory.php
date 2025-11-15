<?php
/**
 * Data factory para enriquecer los registros del grid de reglas de validación.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Grid\ValidationRule;

use PrestaShop\PrestaShop\Core\Grid\Data\Factory\GridDataFactoryInterface;
use PrestaShop\PrestaShop\Core\Grid\Data\GridData;
use PrestaShop\PrestaShop\Core\Grid\Record\RecordCollection;
use PrestaShop\PrestaShop\Core\Grid\Search\SearchCriteriaInterface;

final class ValidationRuleGridDataFactory implements GridDataFactoryInterface
{
    public function __construct(private readonly GridDataFactoryInterface $doctrineDataFactory)
    {
    }

    public function getData(SearchCriteriaInterface $searchCriteria)
    {
        $gridData = $this->doctrineDataFactory->getData($searchCriteria);

        $records = [];

        foreach ($gridData->getRecords() as $record) {
            $scopeLabel = $this->formatScope(
                $record['shop_group_name'] ?? null,
                $record['shop_name'] ?? null
            );

            $record['scope_label'] = $scopeLabel;
            $record['updated_at'] = $record['updated_at'] ?? '';

            $records[] = $record;
        }

        return new GridData(
            new RecordCollection($records),
            $gridData->getRecordsTotal(),
            $gridData->getQuery()
        );
    }

    private function formatScope(?string $shopGroupName, ?string $shopName): string
    {
        if (null !== $shopName && '' !== $shopName) {
            return sprintf('Tienda: %s', $shopName);
        }

        if (null !== $shopGroupName && '' !== $shopGroupName) {
            return sprintf('Grupo: %s', $shopGroupName);
        }

        return 'Global';
    }
}
