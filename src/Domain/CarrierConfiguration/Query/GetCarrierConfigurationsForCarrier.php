<?php
/**
 * Query to retrieve all configurations for a carrier.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\CarrierConfiguration\Query;

final class GetCarrierConfigurationsForCarrier
{
    public function __construct(private readonly int $carrierId)
    {
    }

    public function getCarrierId(): int
    {
        return $this->carrierId;
    }
}
