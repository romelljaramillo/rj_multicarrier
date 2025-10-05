<?php
/**
 * Query to retrieve shipment information by order id.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Shipment\Query;

final class GetShipmentByOrderId
{
    public function __construct(private readonly int $orderId)
    {
    }

    public function getOrderId(): int
    {
        return $this->orderId;
    }
}
