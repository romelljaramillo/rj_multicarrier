<?php
/**
 * Command to soft-delete a shipment and its labels.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Shipment\Command;

final class DeleteShipmentCommand
{
    public function __construct(private readonly int $shipmentId)
    {
    }

    public function getShipmentId(): int
    {
        return $this->shipmentId;
    }
}
