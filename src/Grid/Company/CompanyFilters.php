<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Grid\Company;

use PrestaShop\PrestaShop\Core\Search\Filters;

final class CompanyFilters extends Filters
{
	protected $filterId = 'rj_multicarrier_company';

	public static function getDefaults(): array
	{
		return [
			'limit' => 20,
			'offset' => 0,
			'orderBy' => 'id_carrier_company',
			'sortOrder' => 'asc',
			'filters' => [],
		];
	}
}
