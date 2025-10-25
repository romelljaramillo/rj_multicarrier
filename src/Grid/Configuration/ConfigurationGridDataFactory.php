<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Grid\Configuration;

use PrestaShop\PrestaShop\Core\Grid\Data\Factory\GridDataFactoryInterface;
use PrestaShop\PrestaShop\Core\Grid\Data\GridData;
use PrestaShop\PrestaShop\Core\Grid\Record\RecordCollection;
use PrestaShop\PrestaShop\Core\Grid\Search\SearchCriteriaInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class ConfigurationGridDataFactory implements GridDataFactoryInterface
{
    public function __construct(
        private readonly GridDataFactoryInterface $dataFactory,
        
    ) {
    }

    public function getData(SearchCriteriaInterface $searchCriteria): GridData
    {
        $data = $this->dataFactory->getData($searchCriteria);
        $records = $data->getRecords()->all();

        foreach ($records as &$record) {
            $id = isset($record['id_configuration']) ? (int) $record['id_configuration'] : 0;
            $record['id_configuration'] = $id;
            if (!isset($record['shops']) || null === $record['shops']) {
                $record['shops'] = '';
            }
            if (isset($record['active'])) {
                $record['active'] = (bool) $record['active'];
            }
            if (isset($record['shop_ids']) && is_string($record['shop_ids'])) {
                $parsedShopIds = $this->parseShopIds($record['shop_ids']);
                $record['shop_ids'] = $parsedShopIds;
                $record['shop_association'] = $parsedShopIds;
            }
            // No per-row CSRF tokens: follow Log grid behaviour
        }
        unset($record);

        return new GridData(new RecordCollection($records), $data->getRecordsTotal(), $data->getQuery());
    }

    /**
     * @return int[]
     */
    private function parseShopIds(string $value): array
    {
        $ids = array_map('intval', array_filter(explode(',', $value)));

        return array_values(array_filter($ids, static fn (int $id): bool => $id > 0));
    }
}
