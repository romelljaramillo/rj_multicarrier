<?php
/**
 * Handler para eliminar una regla de validación.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\ValidationRule\Handler;

use Doctrine\ORM\EntityManagerInterface;
use Roanja\Module\RjMulticarrier\Domain\ValidationRule\Command\DeleteValidationRuleCommand;
use Roanja\Module\RjMulticarrier\Domain\ValidationRule\Exception\ValidationRuleNotFoundException;
use Roanja\Module\RjMulticarrier\Repository\ValidationRuleRepository;

final class DeleteValidationRuleHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidationRuleRepository $validationRuleRepository
    ) {
    }

    public function handle(DeleteValidationRuleCommand $command): void
    {
        $rule = $this->validationRuleRepository->findOneById($command->getValidationRuleId());

        if (null === $rule) {
            throw ValidationRuleNotFoundException::withId($command->getValidationRuleId());
        }

        $this->entityManager->remove($rule);
        $this->entityManager->flush();
    }
}
