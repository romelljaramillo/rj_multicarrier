<?php
/**
 * Filters definition for the Configuration grid.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Grid\Configuration;

use PrestaShop\PrestaShop\Core\Search\Filters;

final class ConfigurationFilters extends Filters
{
    protected $filterId = ConfigurationGridDefinitionFactory::GRID_ID;

    public static function getDefaults(): array
    {
        return [
            'limit' => 20,
            'offset' => 0,
            'orderBy' => 'date_add',
            'sortOrder' => 'DESC',
            'filters' => [],
        ];
    }
}
