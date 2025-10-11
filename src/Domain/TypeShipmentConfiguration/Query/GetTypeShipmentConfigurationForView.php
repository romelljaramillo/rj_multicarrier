<?php
/**
 * Query to retrieve a single configuration entry for a type shipment.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\TypeShipmentConfiguration\Query;

final class GetTypeShipmentConfigurationForView
{
    public function __construct(private readonly int $configurationId)
    {
    }

    public function getConfigurationId(): int
    {
        return $this->configurationId;
    }
}
