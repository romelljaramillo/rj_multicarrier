<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Company\Handler;

use Roanja\Module\RjMulticarrier\Domain\Company\Query\GetCompaniesForExport;
use Roanja\Module\RjMulticarrier\Repository\CompanyRepository;

final class GetCompaniesForExportHandler
{
    public function __construct(private readonly CompanyRepository $companyRepository)
    {
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    public function handle(GetCompaniesForExport $query): array
    {
        $companies = $this->companyRepository->findAllOrdered();

        $rows = [];
        foreach ($companies as $company) {
            $rows[] = [
                'id' => $company->getId(),
                'name' => $company->getName(),
                'shortName' => $company->getShortName(),
            ];
        }

        return $rows;
    }
}
