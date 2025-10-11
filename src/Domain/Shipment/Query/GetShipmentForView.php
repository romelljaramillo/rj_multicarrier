<?php
/**
 * Query to retrieve shipment detail information by shipment id.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Shipment\Query;

final class GetShipmentForView
{
    public function __construct(private readonly int $shipmentId)
    {
    }

    public function getShipmentId(): int
    {
        return $this->shipmentId;
    }
}
