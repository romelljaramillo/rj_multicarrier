<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Configuration\Exception;

use Symfony\Component\Validator\ConstraintViolationListInterface;

final class InvalidConfigurationDataException extends \RuntimeException
{
    public function __construct(
        private readonly ConstraintViolationListInterface $violations
    ) {
        parent::__construct('Invalid Configuration data.');
    }

    public function getViolations(): ConstraintViolationListInterface
    {
        return $this->violations;
    }

    public static function fromViolations(ConstraintViolationListInterface $violations): self
    {
        return new self($violations);
    }
}
