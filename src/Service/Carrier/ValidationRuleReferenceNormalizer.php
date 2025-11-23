<?php
/**
 * Normalizes stored validation-rule carrier identifiers to use carrier references.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Service\Carrier;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Roanja\Module\RjMulticarrier\Entity\ValidationRule;
use Roanja\Module\RjMulticarrier\Repository\ValidationRuleRepository;

final class ValidationRuleReferenceNormalizer
{
    private bool $normalized = false;

    public function __construct(
        private readonly ValidationRuleRepository $validationRuleRepository,
        private readonly CarrierReferenceResolver $carrierReferenceResolver,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function normalizeAll(): void
    {
        if ($this->normalized) {
            return;
        }

        $rules = $this->validationRuleRepository->findAll();

        if (empty($rules)) {
            $this->normalized = true;

            return;
        }

        $hasChanges = false;

        foreach ($rules as $rule) {
            if (!$rule instanceof ValidationRule) {
                continue;
            }

            $updated = false;

            $allowIds = $this->normalizeReferences($rule->getAllowIds());
            if ($allowIds !== $rule->getAllowIds()) {
                $rule->setAllowIds($allowIds);
                $updated = true;
            }

            $denyIds = $this->normalizeReferences($rule->getDenyIds());
            if ($denyIds !== $rule->getDenyIds()) {
                $rule->setDenyIds($denyIds);
                $updated = true;
            }

            $addIds = $this->normalizeReferences($rule->getAddIds());
            if ($addIds !== $rule->getAddIds()) {
                $rule->setAddIds($addIds);
                $updated = true;
            }

            if ($updated) {
                $rule->setUpdatedAt(new DateTimeImmutable());
                $this->entityManager->persist($rule);
                $hasChanges = true;
            }
        }

        if ($hasChanges) {
            $this->entityManager->flush();
        }

        $this->normalized = true;
    }

    /**
     * @param int[] $values
     *
     * @return int[]
     */
    private function normalizeReferences(array $values): array
    {
        if (empty($values)) {
            return [];
        }

        return $this->carrierReferenceResolver->ensureReferences($values);
    }
}
