<?php
/**
 * Filters definition for the type shipment grid.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Grid\TypeShipment;

use PrestaShop\PrestaShop\Core\Search\Filters;

final class TypeShipmentFilters extends Filters
{
    protected $filterId = TypeShipmentGridDefinitionFactory::GRID_ID;

    public static function getDefaults(): array
    {
        return [
            'limit' => 20,
            'offset' => 0,
            'orderBy' => 'id_type_shipment',
            'sortOrder' => 'desc',
            'filters' => [],
        ];
    }
}
