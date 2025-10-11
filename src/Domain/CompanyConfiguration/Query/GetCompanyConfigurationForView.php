<?php
/**
 * Query to retrieve a single company configuration for viewing or editing.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\CompanyConfiguration\Query;

final class GetCompanyConfigurationForView
{
    public function __construct(private readonly int $configurationId)
    {
    }

    public function getConfigurationId(): int
    {
        return $this->configurationId;
    }
}
