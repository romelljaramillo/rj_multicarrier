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
        private readonly ?string $fieldGroup = null,
        private readonly ?string $fieldGroupLabel = null,
        private readonly ?string $fieldType = null,
        private readonly ?string $payloadDirection = null,
        private readonly ?string $payloadKey = null,
        private readonly ?string $payloadSource = null,
        private readonly int $position = 0,
        private readonly bool $isDynamic = false,
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

    public function getFieldGroup(): ?string
    {
        return $this->fieldGroup;
    }

    public function getFieldGroupLabel(): ?string
    {
        return $this->fieldGroupLabel;
    }

    public function getFieldType(): ?string
    {
        return $this->fieldType;
    }

    public function getPayloadDirection(): ?string
    {
        return $this->payloadDirection;
    }

    public function getPayloadKey(): ?string
    {
        return $this->payloadKey;
    }

    public function getPayloadSource(): ?string
    {
        return $this->payloadSource;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function isDynamicField(): bool
    {
        return $this->isDynamic;
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
            'fieldGroup' => $this->fieldGroup,
            'fieldGroupLabel' => $this->fieldGroupLabel,
            'fieldType' => $this->fieldType,
            'payloadDirection' => $this->payloadDirection,
            'payloadKey' => $this->payloadKey,
            'payloadSource' => $this->payloadSource,
            'position' => $this->position,
            'isDynamic' => $this->isDynamic,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
