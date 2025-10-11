<?php
/**
 * Command to delete a type shipment configuration entry.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\TypeShipmentConfiguration\Command;

final class DeleteTypeShipmentConfigurationCommand
{
    public function __construct(private readonly int $configurationId)
    {
    }

    public function getConfigurationId(): int
    {
        return $this->configurationId;
    }
}
