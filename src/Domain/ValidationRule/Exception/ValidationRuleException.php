<?php
/**
 * Excepción base para errores del dominio de reglas de validación.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\ValidationRule\Exception;

use DomainException;

class ValidationRuleException extends DomainException
{
}
