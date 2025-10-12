<?php
/**
 * Command to delete a carrier configuration entry.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\CarrierConfiguration\Command;

final class DeleteCarrierConfigurationCommand
{
    public function __construct(private readonly int $configurationId)
    {
    }

    public function getConfigurationId(): int
    {
        return $this->configurationId;
    }
}
