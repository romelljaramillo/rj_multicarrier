<?php
/**
 * Thrown when a requested company configuration entry cannot be located.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\CompanyConfiguration\Exception;

final class CompanyConfigurationNotFoundException extends CompanyConfigurationException
{
    public static function forId(int $id): self
    {
        return new self(sprintf('Company configuration with id %d was not found', $id));
    }
}
