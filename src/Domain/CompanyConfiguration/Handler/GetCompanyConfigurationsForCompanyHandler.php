<?php
/**
 * Handles listing configurations for a company.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\CompanyConfiguration\Handler;

use Roanja\Module\RjMulticarrier\Domain\CompanyConfiguration\Query\GetCompanyConfigurationsForCompany;
use Roanja\Module\RjMulticarrier\Domain\CompanyConfiguration\View\CompanyConfigurationView;
use Roanja\Module\RjMulticarrier\Repository\CarrierConfigurationRepository;
use Roanja\Module\RjMulticarrier\Repository\CompanyRepository;

final class GetCompanyConfigurationsForCompanyHandler
{
    public function __construct(
        private readonly CompanyRepository $companyRepository,
        private readonly CarrierConfigurationRepository $configurationRepository,
    ) {
    }

    /**
     * @return CompanyConfigurationView[]
     */
    public function handle(GetCompanyConfigurationsForCompany $query): array
    {
        $company = $this->companyRepository->find($query->getCompanyId());

        if (null === $company) {
            return [];
        }

        $entries = $this->configurationRepository->findByCompany($company);
        $views = [];

        foreach ($entries as $entry) {
            $views[] = new CompanyConfigurationView(
                $entry->getId(),
                $company->getId() ?? 0,
                $entry->getName(),
                $entry->getValue(),
                $entry->getCreatedAt()?->format('Y-m-d H:i:s'),
                $entry->getUpdatedAt()?->format('Y-m-d H:i:s')
            );
        }

        return $views;
    }
}
