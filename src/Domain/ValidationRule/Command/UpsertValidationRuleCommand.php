<?php
/**
 * Comando para crear o actualizar una regla de validación.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\ValidationRule\Command;

final class UpsertValidationRuleCommand
{
    /**
     * @param int[] $productIds
     * @param int[] $categoryIds
     * @param int[] $zoneIds
     * @param int[] $countryIds
     * @param int[] $allowIds
     * @param int[] $denyIds
     * @param int[] $addIds
     * @param int[] $preferIds
     */
    public function __construct(
        private readonly ?int $validationRuleId,
        private readonly string $name,
        private readonly int $priority,
        private readonly bool $active,
        private readonly ?int $shopId,
        private readonly ?int $shopGroupId,
        private readonly array $productIds,
        private readonly array $categoryIds,
        private readonly array $zoneIds,
        private readonly array $countryIds,
        private readonly ?float $minWeight,
        private readonly ?float $maxWeight,
        private readonly array $allowIds,
        private readonly array $denyIds,
        private readonly array $addIds,
        private readonly array $preferIds
    ) {
    }

    public function getValidationRuleId(): ?int
    {
        return $this->validationRuleId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function getShopId(): ?int
    {
        return $this->shopId;
    }

    public function getShopGroupId(): ?int
    {
        return $this->shopGroupId;
    }

    /**
     * @return int[]
     */
    public function getProductIds(): array
    {
        return $this->productIds;
    }

    /**
     * @return int[]
     */
    public function getCategoryIds(): array
    {
        return $this->categoryIds;
    }

    /**
     * @return int[]
     */
    public function getZoneIds(): array
    {
        return $this->zoneIds;
    }

    /**
     * @return int[]
     */
    public function getCountryIds(): array
    {
        return $this->countryIds;
    }

    public function getMinWeight(): ?float
    {
        return $this->minWeight;
    }

    public function getMaxWeight(): ?float
    {
        return $this->maxWeight;
    }

    /**
     * @return int[]
     */
    public function getAllowIds(): array
    {
        return $this->allowIds;
    }

    /**
     * @return int[]
     */
    public function getDenyIds(): array
    {
        return $this->denyIds;
    }

    /**
     * @return int[]
     */
    public function getAddIds(): array
    {
        return $this->addIds;
    }

    /**
     * @return int[]
     */
    public function getPreferIds(): array
    {
        return $this->preferIds;
    }
}
