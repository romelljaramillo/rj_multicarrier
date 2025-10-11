<?php
/**
 * Decorates the Doctrine grid data factory so we can post-process log rows before rendering.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Grid\Log;

use PrestaShop\PrestaShop\Core\Grid\Data\Factory\GridDataFactoryInterface;
use PrestaShop\PrestaShop\Core\Grid\Data\GridData;
use PrestaShop\PrestaShop\Core\Grid\Record\RecordCollection;
use PrestaShop\PrestaShop\Core\Grid\Search\SearchCriteriaInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class LogGridDataFactory implements GridDataFactoryInterface
{
    public function __construct(
        private readonly GridDataFactoryInterface $dataFactory,
        private readonly TranslatorInterface $translator
    ) {
    }

    public function getData(SearchCriteriaInterface $searchCriteria): GridData
    {
        $data = $this->dataFactory->getData($searchCriteria);
        $records = $data->getRecords()->all();

        foreach ($records as &$record) {
            $record['created_at'] = $this->formatDateTime($record['created_at'] ?? null);
            $record['updated_at'] = $this->formatDateTime($record['updated_at'] ?? null);
            $record['shop_name'] = $this->normalizeShopName($record['shop_name'] ?? null);
        }
        unset($record);

        return new GridData(
            new RecordCollection($records),
            $data->getRecordsTotal(),
            $data->getQuery()
        );
    }

    private function formatDateTime(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        if (is_string($value) && '' !== trim($value)) {
            try {
                $date = new \DateTimeImmutable($value);

                return $date->format('Y-m-d H:i:s');
            } catch (\Exception) {
                return $value;
            }
        }

        return null;
    }

    private function normalizeShopName(mixed $value): string
    {
        if (is_string($value) && '' !== trim($value)) {
            return $value;
        }

        return $this->translator->trans('Tienda principal', [], 'Modules.RjMulticarrier.Admin');
    }
}
