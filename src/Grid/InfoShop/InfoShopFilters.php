<?php
/**
 * Filters definition for the InfoShop grid.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Grid\InfoShop;

use PrestaShop\PrestaShop\Core\Search\Filters;

final class InfoShopFilters extends Filters
{
    protected $filterId = InfoShopGridDefinitionFactory::GRID_ID;

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
