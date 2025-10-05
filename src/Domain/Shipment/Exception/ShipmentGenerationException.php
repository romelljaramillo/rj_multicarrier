<?php
/**
 * Raised when a shipment cannot be generated from an info package.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Shipment\Exception;

final class ShipmentGenerationException extends ShipmentException
{
    public static function infoPackageNotFound(int $infoPackageId): self
    {
        return new self(sprintf('El paquete %d no existe o no está disponible en esta tienda.', $infoPackageId));
    }

    public static function orderNotFound(int $orderId): self
    {
        return new self(sprintf('El pedido %d asociado al paquete no existe.', $orderId));
    }

    public static function shipmentAlreadyExists(int $infoPackageId): self
    {
        return new self(sprintf('El paquete %d ya tiene un envío generado.', $infoPackageId));
    }

    public static function carrierNotConfigured(int $referenceCarrierId): self
    {
        return new self(sprintf('No hay transportista configurado para la referencia %d.', $referenceCarrierId));
    }

    public static function typeShipmentsMissing(int $companyId): self
    {
        return new self(sprintf('La compañía de transporte %d no tiene servicios activos configurados.', $companyId));
    }
}
