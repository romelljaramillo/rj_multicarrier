<?php
/**
 * Command to create or update configuration for a type shipment.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\TypeShipmentConfiguration\Command;

final class UpsertTypeShipmentConfigurationCommand
{
    public function __construct(
        private readonly int $typeShipmentId,
        private readonly string $name,
        private readonly ?string $value,
        private readonly ?int $configurationId = null,
    ) {
    }

    public function getConfigurationId(): ?int
    {
        return $this->configurationId;
    }

    public function getTypeShipmentId(): int
    {
        return $this->typeShipmentId;
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
