<?php
/**
 * Handler para crear o actualizar reglas de validación.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\ValidationRule\Handler;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Roanja\Module\RjMulticarrier\Domain\ValidationRule\Command\UpsertValidationRuleCommand;
use Roanja\Module\RjMulticarrier\Domain\ValidationRule\Exception\ValidationRuleNotFoundException;
use Roanja\Module\RjMulticarrier\Entity\ValidationRule;
use Roanja\Module\RjMulticarrier\Repository\ValidationRuleRepository;
use Roanja\Module\RjMulticarrier\Service\Carrier\CarrierReferenceResolver;

final class UpsertValidationRuleHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidationRuleRepository $validationRuleRepository,
        private readonly CarrierReferenceResolver $carrierReferenceResolver
    ) {
    }

    public function handle(UpsertValidationRuleCommand $command): ValidationRule
    {
        $ruleId = $command->getValidationRuleId();
        $rule = null === $ruleId
            ? new ValidationRule()
            : $this->validationRuleRepository->findOneById($ruleId);

        if (null === $rule) {
            throw ValidationRuleNotFoundException::withId($ruleId ?? 0);
        }

        $now = new DateTimeImmutable('now');

        if (null === $rule->getCreatedAt()) {
            $rule->setCreatedAt($now);
        }

        $rule->setUpdatedAt($now)
            ->setName($command->getName())
            ->setPriority($command->getPriority())
            ->setActive($command->isActive())
            ->setShopId($command->getShopId())
            ->setProductIds($this->normalizeArray($command->getProductIds()))
            ->setCategoryIds($this->normalizeArray($command->getCategoryIds()))
            ->setZoneIds($this->normalizeArray($command->getZoneIds()))
            ->setCountryIds($this->normalizeArray($command->getCountryIds()))
            ->setMinWeight($command->getMinWeight())
            ->setMaxWeight($command->getMaxWeight())
            ->setAllowIds($this->normalizeCarrierIds($command->getAllowIds()))
            ->setDenyIds($this->normalizeCarrierIds($command->getDenyIds()))
            ->setAddIds($this->normalizeCarrierIds($command->getAddIds()));

        $this->entityManager->persist($rule);
        $this->entityManager->flush();

        return $rule;
    }

    /**
     * @param int[] $values
     */
    private function normalizeArray(array $values): ?array
    {
        $normalized = array_values(array_unique(array_map(static function ($value): int {
            return (int) $value;
        }, $values)));

        return empty($normalized) ? null : $normalized;
    }

    /**
     * @param int[] $values
     */
    private function normalizeCarrierIds(array $values): ?array
    {
        $references = $this->carrierReferenceResolver->ensureReferences($values);

        return $this->normalizeArray($references);
    }
}
