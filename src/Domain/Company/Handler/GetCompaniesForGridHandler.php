<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Company\Handler;

use Roanja\Module\RjMulticarrier\Domain\Company\Query\GetCompaniesForGrid;
use Roanja\Module\RjMulticarrier\Repository\CompanyRepository;

final class GetCompaniesForGridHandler
{
    public function __construct(private readonly CompanyRepository $companyRepository)
    {
    }

    /**
     * @return array<int, array<string,mixed>> Rows compatible with the Company grid
     */
    public function handle(GetCompaniesForGrid $query): array
    {
        // For now we ignore filters and return all ordered companies. The Doctrine Grid uses its own
        // QueryBuilder for pagination/search; this handler is useful for export or other flows.
        $companies = $this->companyRepository->findAllOrdered();

        $rows = [];
        foreach ($companies as $company) {
            $icon = $company->getIcon();
            $iconUrl = $icon ? (_MODULE_DIR_ . 'rj_multicarrier/var/icons/' . $icon) : null;

            $rows[] = [
                'id_carrier_company' => $company->getId(),
                'name' => $company->getName(),
                'icon' => $iconUrl,
                'shortname' => $company->getShortName(),
            ];
        }

        return $rows;
    }
}
