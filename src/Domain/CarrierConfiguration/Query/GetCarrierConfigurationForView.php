<?php
/**
 * Query to retrieve a single carrier configuration for viewing or editing.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\CarrierConfiguration\Query;

final class GetCarrierConfigurationForView
{
    public function __construct(private readonly int $configurationId)
    {
    }

    public function getConfigurationId(): int
    {
        return $this->configurationId;
    }
}
