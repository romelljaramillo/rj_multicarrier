<?php
/**
 * Handler para eliminar en lote reglas de validación.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\ValidationRule\Handler;

use Doctrine\ORM\EntityManagerInterface;
use Roanja\Module\RjMulticarrier\Domain\ValidationRule\Command\BulkDeleteValidationRuleCommand;
use Roanja\Module\RjMulticarrier\Repository\ValidationRuleRepository;

final class BulkDeleteValidationRuleHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidationRuleRepository $validationRuleRepository
    ) {
    }

    public function handle(BulkDeleteValidationRuleCommand $command): int
    {
        $ids = array_unique(array_filter(array_map(static function ($value): int {
            return (int) $value;
        }, $command->getValidationRuleIds()), static function (int $value): bool {
            return $value > 0;
        }));

        if (empty($ids)) {
            return 0;
        }

        $deleted = 0;

        foreach ($ids as $id) {
            $rule = $this->validationRuleRepository->findOneById($id);

            if (null === $rule) {
                continue;
            }

            $this->entityManager->remove($rule);
            ++$deleted;
        }

        if ($deleted > 0) {
            $this->entityManager->flush();
        }

        return $deleted;
    }
}
