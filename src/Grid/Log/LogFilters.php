<?php
/**
 * Filters definition for the carrier log grid.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Grid\Log;

use PrestaShop\PrestaShop\Core\Search\Filters;

final class LogFilters extends Filters
{
    protected $filterId = LogGridDefinitionFactory::GRID_ID;

    public static function getDefaults(): array
    {
        return [
            'id_shop' => null,
            'limit' => 20,
            'offset' => 0,
            'orderBy' => 'date_add',
            'sortOrder' => 'DESC',
            'filters' => [],
        ];
    }
}
