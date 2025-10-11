<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\TypeShipmentConfiguration\Exception;

final class TypeShipmentConfigurationNotFoundException extends TypeShipmentConfigurationException
{
    public static function forId(int $id): self
    {
        return new self(sprintf('Type shipment configuration with id %d was not found', $id));
    }
}
