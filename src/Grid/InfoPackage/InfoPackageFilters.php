<?php
/**
 * Filters definition for the info package grid.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Grid\InfoPackage;

use PrestaShop\PrestaShop\Core\Search\Filters;

final class InfoPackageFilters extends Filters
{
    protected $filterId = 'rj_multicarrier_info_package';

    public static function getDefaults(): array
    {
        return [
            'limit' => 20,
            'offset' => 0,
            'orderBy' => 'date_add',
            'sortOrder' => 'desc',
            'filters' => [],
        ];
    }
}
