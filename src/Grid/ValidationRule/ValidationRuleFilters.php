<?php
/**
 * Filtros del grid de reglas de validación.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Grid\ValidationRule;

use PrestaShop\PrestaShop\Core\Search\Filters;

final class ValidationRuleFilters extends Filters
{
    protected $filterId = ValidationRuleGridDefinitionFactory::GRID_ID;

    public static function getDefaults(): array
    {
        return [
            'limit' => 20,
            'offset' => 0,
            'orderBy' => 'priority',
            'sortOrder' => 'asc',
            'filters' => [],
        ];
    }
}
