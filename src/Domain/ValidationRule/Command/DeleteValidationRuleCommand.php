<?php
/**
 * Comando para eliminar una regla de validación individual.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\ValidationRule\Command;

final class DeleteValidationRuleCommand
{
    public function __construct(private readonly int $validationRuleId)
    {
    }

    public function getValidationRuleId(): int
    {
        return $this->validationRuleId;
    }
}
