<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Grid\InfoShop;

use PrestaShop\PrestaShop\Core\Grid\Data\Factory\GridDataFactoryInterface;
use PrestaShop\PrestaShop\Core\Grid\Data\GridData;
use PrestaShop\PrestaShop\Core\Grid\Record\RecordCollection;
use PrestaShop\PrestaShop\Core\Grid\Search\SearchCriteriaInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class InfoShopGridDataFactory implements GridDataFactoryInterface
{
    public function __construct(
        private readonly GridDataFactoryInterface $dataFactory,
        private readonly CsrfTokenManagerInterface $csrfTokenManager
    ) {
    }

    public function getData(SearchCriteriaInterface $searchCriteria): GridData
    {
        $data = $this->dataFactory->getData($searchCriteria);
        $records = $data->getRecords()->all();

        foreach ($records as &$record) {
            $id = isset($record['id_infoshop']) ? (int) $record['id_infoshop'] : 0;
            $record['id_infoshop'] = $id;
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
            $record['toggle_token'] = $this->csrfTokenManager
                ->getToken('toggle_infoshop_' . $id)
                ->getValue();
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
