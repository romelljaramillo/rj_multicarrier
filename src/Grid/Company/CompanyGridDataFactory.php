<?php
/**
 * Decorates the Doctrine grid data factory so we can post-process company rows before rendering.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Grid\Company;

use PrestaShop\PrestaShop\Core\Grid\Data\Factory\GridDataFactoryInterface;
use PrestaShop\PrestaShop\Core\Grid\Data\GridData;
use PrestaShop\PrestaShop\Core\Grid\Record\RecordCollection;
use PrestaShop\PrestaShop\Core\Grid\Search\SearchCriteriaInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

final class CompanyGridDataFactory implements GridDataFactoryInterface
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
}
