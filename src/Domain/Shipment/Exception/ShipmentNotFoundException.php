<?php
/**
 * Thrown when a shipment cannot be located.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Shipment\Exception;

final class ShipmentNotFoundException extends ShipmentException
{
    public static function withId(int $shipmentId): self
    {
        return new self(sprintf('El envío %d no existe.', $shipmentId));
    }
}
