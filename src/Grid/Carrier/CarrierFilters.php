<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Grid\Carrier;

use PrestaShop\PrestaShop\Core\Search\Filters;

final class CarrierFilters extends Filters
{
    protected $filterId = 'rj_multicarrier_carrier';

    public static function getDefaults(): array
    {
        return [
            'limit' => 20,
            'offset' => 0,
            'orderBy' => 'id_carrier',
            'sortOrder' => 'asc',
            'filters' => [],
        ];
    }
}
