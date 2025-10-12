<?php
/**
 * Thrown when a requested carrier configuration entry cannot be located.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\CarrierConfiguration\Exception;

final class CarrierConfigurationNotFoundException extends CarrierConfigurationException
{
    public static function forId(int $id): self
    {
        return new self(sprintf('Carrier configuration with id %d was not found', $id));
    }
}
