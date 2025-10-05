<?php
/**
 * Exception thrown when attempting to link a carrier already assigned to an active type shipment.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\TypeShipment\Exception;

final class TypeShipmentCarrierConflictException extends TypeShipmentException
{
    public static function fromReference(int $referenceCarrierId): self
    {
        return new self(sprintf('Reference carrier %d is already assigned to an active type shipment.', $referenceCarrierId));
    }
}
