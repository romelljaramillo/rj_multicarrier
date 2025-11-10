<?php
/**
 * DTO representing a carrier configuration entry.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\CarrierConfiguration\View;

final class CarrierConfigurationView
{
    public function __construct(
        private readonly int $id,
        private readonly int $carrierId,
        private readonly string $name,
        private readonly ?string $value,
        private readonly bool $isRequired,
        private readonly ?string $label = null,
        private readonly ?string $description = null,
        private readonly ?string $createdAt = null,
        private readonly ?string $updatedAt = null
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getCarrierId(): int
    {
        return $this->carrierId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function isRequired(): bool
    {
        return $this->isRequired;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getDisplayName(): string
    {
        return $this->label ? $this->label : $this->name;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?string
    {
        return $this->updatedAt;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'carrierId' => $this->carrierId,
            'companyId' => $this->carrierId,
            'name' => $this->name,
            'value' => $this->value,
            'label' => $this->label,
            'displayName' => $this->getDisplayName(),
            'description' => $this->description,
            'isRequired' => $this->isRequired,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
