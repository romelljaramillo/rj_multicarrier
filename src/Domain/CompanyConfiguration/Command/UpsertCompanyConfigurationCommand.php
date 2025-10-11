<?php
/**
 * Command to create or update a company configuration entry.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\CompanyConfiguration\Command;

final class UpsertCompanyConfigurationCommand
{
    public function __construct(
        private readonly int $companyId,
        private readonly string $name,
        private readonly ?string $value,
        private readonly ?int $configurationId = null,
    ) {
    }

    public function getConfigurationId(): ?int
    {
        return $this->configurationId;
    }

    public function getCompanyId(): int
    {
        return $this->companyId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }
}
