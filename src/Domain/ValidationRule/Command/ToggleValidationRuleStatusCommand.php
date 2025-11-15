<?php
/**
 * Comando para alternar el estado activo de una regla de validación.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\ValidationRule\Command;

final class ToggleValidationRuleStatusCommand
{
    public function __construct(private readonly int $validationRuleId)
    {
    }

    public function getValidationRuleId(): int
    {
        return $this->validationRuleId;
    }
}
