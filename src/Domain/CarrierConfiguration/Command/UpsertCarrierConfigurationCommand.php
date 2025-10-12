<?php
/**
 * Command to create or update a carrier configuration entry.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\CarrierConfiguration\Command;

final class UpsertCarrierConfigurationCommand
{
    public function __construct(
        private readonly int $carrierId,
        private readonly string $name,
        private readonly ?string $value,
        private readonly ?int $configurationId = null,
    ) {
    }

    public function getConfigurationId(): ?int
    {
        return $this->configurationId;
    }

    public function getCarrierId(): int
    {
        return $this->carrierId;
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
