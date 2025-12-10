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
            $shopLabel = trim((string) ($record['shop_name'] ?? ''));
            $record['shop_label'] = $shopLabel === '' ? 'No asignada' : $shopLabel;
            $record['updated_at'] = $record['updated_at'] ?? '';
            $record['conditions_summary'] = $this->formatConditionsSummary($record);
            $record['actions_summary'] = $this->formatActionsSummary($record);

            $records[] = $record;
        }
        return new GridData(
            new RecordCollection($records),
            $gridData->getRecordsTotal(),
            $gridData->getQuery()
        );
    }

    /**
     * @param array<string, mixed> $record
     */
    private function formatConditionsSummary(array $record): string
    {
        $parts = [];

        $productIds = $this->normalizeIdList($record['product_ids'] ?? null);
        if (!empty($productIds)) {
            $parts[] = sprintf('Productos: %s', implode(', ', $productIds));
        }

        $categoryIds = $this->normalizeIdList($record['category_ids'] ?? null);
        if (!empty($categoryIds)) {
            $parts[] = sprintf('Categorías: %s', implode(', ', $categoryIds));
        }

        $zoneIds = $this->normalizeIdList($record['zone_ids'] ?? null);
        if (!empty($zoneIds)) {
            $parts[] = sprintf('Zonas: %s', implode(', ', $zoneIds));
        }

        $countryIds = $this->normalizeIdList($record['country_ids'] ?? null);
        if (!empty($countryIds)) {
            $parts[] = sprintf('Países: %s', implode(', ', $countryIds));
        }

        $minWeight = $record['min_weight'] ?? null;
        $maxWeight = $record['max_weight'] ?? null;

        if (null !== $minWeight || null !== $maxWeight) {
            $parts[] = sprintf('Peso: %s - %s',
                null === $minWeight ? '—' : (float) $minWeight,
                null === $maxWeight ? '—' : (float) $maxWeight
            );
        }

        return empty($parts) ? '—' : implode(' | ', $parts);
    }

    /**
     * @param array<string, mixed> $record
     */
    private function formatActionsSummary(array $record): string
    {
        $parts = [];

        $allow = $this->normalizeIdList($record['allow_ids'] ?? null);
        if (!empty($allow)) {
            $parts[] = sprintf('Permitir: %s', implode(', ', $allow));
        }

        $deny = $this->normalizeIdList($record['deny_ids'] ?? null);
        if (!empty($deny)) {
            $parts[] = sprintf('Denegar: %s', implode(', ', $deny));
        }

        $add = $this->normalizeIdList($record['add_ids'] ?? null);
        if (!empty($add)) {
            $parts[] = sprintf('Añadir: %s', implode(', ', $add));
        }

        return empty($parts) ? '—' : implode(' | ', $parts);
    }

    /**
     * @param mixed $value
     *
     * @return int[]
     */
    private function normalizeIdList($value): array
    {
        if (is_array($value)) {
            $data = $value;
        } elseif (null === $value) {
            $data = [];
        } else {
            $decoded = json_decode((string) $value, true);
            $data = is_array($decoded) ? $decoded : [];
        }

        $result = [];

        foreach ($data as $item) {
            if (!is_numeric($item)) {
                continue;
            }

            $id = (int) $item;

            if ($id > 0) {
                $result[] = $id;
            }
        }

        return array_values(array_unique($result));
    }

}
