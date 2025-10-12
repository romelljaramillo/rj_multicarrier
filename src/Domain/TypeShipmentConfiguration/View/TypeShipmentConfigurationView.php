<?php
/**
 * DTO describing a type shipment configuration entry.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\TypeShipmentConfiguration\View;

final class TypeShipmentConfigurationView
{
    public function __construct(
        private readonly int $id,
        private readonly int $typeShipmentId,
        private readonly int $carrierId,
        private readonly string $name,
        private readonly ?string $value,
        private readonly ?string $createdAt,
        private readonly ?string $updatedAt
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getTypeShipmentId(): int
    {
        return $this->typeShipmentId;
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
            'typeShipmentId' => $this->typeShipmentId,
            'carrierId' => $this->carrierId,
            'companyId' => $this->carrierId,
            'name' => $this->name,
            'value' => $this->value,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
