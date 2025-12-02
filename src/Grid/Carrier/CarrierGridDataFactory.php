<?php
/**
 * Decorates the Doctrine grid data factory so we can post-process carrier rows before rendering.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Grid\Carrier;

use PrestaShop\PrestaShop\Core\Grid\Data\Factory\GridDataFactoryInterface;
use PrestaShop\PrestaShop\Core\Grid\Data\GridData;
use PrestaShop\PrestaShop\Core\Grid\Record\RecordCollection;
use PrestaShop\PrestaShop\Core\Grid\Search\SearchCriteriaInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class CarrierGridDataFactory implements GridDataFactoryInterface
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
            // Normalize fields and provide a thumbnail field for the icon
            $record['name'] = $record['name'] ?? $this->translator->trans('—', [], 'Modules.RjMulticarrier.Admin');
            $record['shortname'] = $record['shortname'] ?? '';
            $record['icon_thumb'] = $this->buildIconThumb($record['icon'] ?? null);

            if (array_key_exists('is_active', $record)) {
                $record['is_active'] = (bool) (int) $record['is_active'];
            }

            if (array_key_exists('supports_api', $record)) {
                $record['supports_api'] = $this->formatBoolean(
                    $record['supports_api'],
                    $this->translator->trans('Yes', [], 'Admin.Global'),
                    $this->translator->trans('No', [], 'Admin.Global')
                );
            }
        }
        unset($record);

        return new GridData(
            new RecordCollection($records),
            $data->getRecordsTotal(),
            $data->getQuery()
        );
    }

    private function buildIconThumb(mixed $icon): ?string
    {
        if (null === $icon) {
            return null;
        }

        if (is_string($icon) && '' !== trim($icon)) {
            // Prefer a thumbnail filename if present
            $thumb = $this->makeThumbName($icon);
            $serverThumb = _PS_MODULE_DIR_ . 'rj_multicarrier/var/icons/' . $thumb;
            $moduleUri = _MODULE_DIR_ . 'rj_multicarrier/var/icons/';
            if (is_file($serverThumb)) {
                return $moduleUri . $thumb;
            }

            return $moduleUri . $icon;
        }

        return null;
    }

    private function makeThumbName(string $fileName): string
    {
        $pos = strrpos($fileName, '.');
        if (false === $pos) {
            return $fileName . '_thumb';
        }

        $base = substr($fileName, 0, $pos);
        $ext = substr($fileName, $pos + 1);

        return $base . '_thumb.' . $ext;
    }

    private function formatBoolean(mixed $value, string $trueLabel, string $falseLabel): string
    {
        if (null === $value) {
            return $this->translator->trans('—', [], 'Modules.RjMulticarrier.Admin');
        }

        return (int) $value ? $trueLabel : $falseLabel;
    }
}
