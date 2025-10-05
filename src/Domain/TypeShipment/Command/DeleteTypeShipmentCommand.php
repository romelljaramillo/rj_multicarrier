<?php
/**
 * Command to delete a type shipment.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\TypeShipment\Command;

final class DeleteTypeShipmentCommand
{
    public function __construct(private readonly int $typeShipmentId)
    {
    }

    public function getTypeShipmentId(): int
    {
        return $this->typeShipmentId;
    }
}
