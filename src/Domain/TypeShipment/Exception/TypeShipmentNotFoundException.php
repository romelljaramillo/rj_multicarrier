<?php
/**
 * Exception thrown when a type shipment is not found.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\TypeShipment\Exception;

final class TypeShipmentNotFoundException extends TypeShipmentException
{
    public static function fromId(int $typeShipmentId): self
    {
        return new self(sprintf('Type shipment with id %d was not found.', $typeShipmentId));
    }
}
