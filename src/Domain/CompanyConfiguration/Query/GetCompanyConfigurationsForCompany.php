<?php
/**
 * Query to retrieve all configurations for a company.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\CompanyConfiguration\Query;

final class GetCompanyConfigurationsForCompany
{
    public function __construct(private readonly int $companyId)
    {
    }

    public function getCompanyId(): int
    {
        return $this->companyId;
    }
}
