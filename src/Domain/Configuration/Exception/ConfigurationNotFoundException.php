<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Configuration\Exception;

final class ConfigurationNotFoundException extends \RuntimeException
{
    public static function forId(int $id): self
    {
        return new self(sprintf('Configuration with id %d not found', $id));
    }
}
