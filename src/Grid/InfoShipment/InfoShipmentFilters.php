<?php
/**
 * Filters definition for the info shipment grid.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Grid\InfoShipment;

use PrestaShop\PrestaShop\Core\Search\Filters;

final class InfoShipmentFilters extends Filters
{
    protected $filterId = 'rj_multicarrier_info_shipment';

    public static function getDefaults(): array
    {
        return [
            'limit' => 20,
            'offset' => 0,
            'orderBy' => 'id_order',
            'sortOrder' => 'desc',
            'filters' => [],
        ];
    }
}
