<?php
/**
 * Command to delete a company configuration entry.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\CompanyConfiguration\Command;

final class DeleteCompanyConfigurationCommand
{
    public function __construct(private readonly int $configurationId)
    {
    }

    public function getConfigurationId(): int
    {
        return $this->configurationId;
    }
}
