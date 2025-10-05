<?php
/**
 * Filters definition for the shipment grid.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Grid\Shipment;

use PrestaShop\PrestaShop\Core\Search\Filters;

final class ShipmentFilters extends Filters
{
    protected $filterId = 'rj_multicarrier_shipment';

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
