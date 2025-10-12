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
        private readonly ?string $createdAt,
        private readonly ?string $updatedAt
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
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
