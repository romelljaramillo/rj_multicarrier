<?php
/**
 * Se lanza cuando no se encuentra una regla de validación.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\ValidationRule\Exception;

class ValidationRuleNotFoundException extends ValidationRuleException
{
    public static function withId(int $id): self
    {
        return new self(sprintf('No se encontró la regla de validación con ID %d.', $id));
    }
}
