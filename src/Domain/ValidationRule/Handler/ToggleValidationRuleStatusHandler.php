<?php
/**
 * Handler para alternar el estado activo de una regla de validación.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\ValidationRule\Handler;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Roanja\Module\RjMulticarrier\Domain\ValidationRule\Command\ToggleValidationRuleStatusCommand;
use Roanja\Module\RjMulticarrier\Domain\ValidationRule\Exception\ValidationRuleNotFoundException;
use Roanja\Module\RjMulticarrier\Entity\ValidationRule;
use Roanja\Module\RjMulticarrier\Repository\ValidationRuleRepository;

final class ToggleValidationRuleStatusHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidationRuleRepository $validationRuleRepository
    ) {
    }

    public function handle(ToggleValidationRuleStatusCommand $command): ValidationRule
    {
        $rule = $this->validationRuleRepository->findOneById($command->getValidationRuleId());

        if (null === $rule) {
            throw ValidationRuleNotFoundException::withId($command->getValidationRuleId());
        }

        $rule->setActive(!$rule->isActive());
        $rule->setUpdatedAt(new DateTimeImmutable('now'));

        $this->entityManager->persist($rule);
        $this->entityManager->flush();

        return $rule;
    }
}
