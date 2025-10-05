<?php
/**
 * Command to toggle the active status of a type shipment.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\TypeShipment\Command;

final class ToggleTypeShipmentStatusCommand
{
    public function __construct(private readonly int $typeShipmentId)
    {
    }

    public function getTypeShipmentId(): int
    {
        return $this->typeShipmentId;
    }
}
