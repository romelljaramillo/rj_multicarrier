<?php
/**
 * Thrown when labels cannot be recovered or streamed.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Shipment\Exception;

final class ShipmentLabelException extends ShipmentException
{
    public static function missing(): self
    {
        return new self('El envío no tiene etiquetas disponibles.');
    }

    public static function corrupt(string $labelId): self
    {
        return new self(sprintf('No se pudo reconstruir la etiqueta con identificador %s.', $labelId));
    }
}
