<?php
/**
 * Comando para eliminar múltiples reglas de validación a la vez.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\ValidationRule\Command;

final class BulkDeleteValidationRuleCommand
{
    /**
     * @param int[] $validationRuleIds
     */
    public function __construct(private readonly array $validationRuleIds)
    {
    }

    /**
     * @return int[]
     */
    public function getValidationRuleIds(): array
    {
        return $this->validationRuleIds;
    }
}
