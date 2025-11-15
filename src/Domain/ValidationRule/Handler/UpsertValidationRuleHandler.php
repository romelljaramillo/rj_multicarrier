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

final class UpsertValidationRuleHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidationRuleRepository $validationRuleRepository
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
            ->setShopGroupId($command->getShopGroupId())
            ->setProductIds($this->normalizeArray($command->getProductIds()))
            ->setCategoryIds($this->normalizeArray($command->getCategoryIds()))
            ->setZoneIds($this->normalizeArray($command->getZoneIds()))
            ->setCountryIds($this->normalizeArray($command->getCountryIds()))
            ->setMinWeight($command->getMinWeight())
            ->setMaxWeight($command->getMaxWeight())
            ->setAllowIds($this->normalizeArray($command->getAllowIds()))
            ->setDenyIds($this->normalizeArray($command->getDenyIds()))
            ->setAddIds($this->normalizeArray($command->getAddIds()))
            ->setPreferIds($this->normalizeArray($command->getPreferIds()));

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
}
