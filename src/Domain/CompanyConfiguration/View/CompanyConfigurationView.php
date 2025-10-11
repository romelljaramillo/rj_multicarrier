<?php
/**
 * DTO representing a company configuration entry.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\CompanyConfiguration\View;

final class CompanyConfigurationView
{
    public function __construct(
        private readonly int $id,
        private readonly int $companyId,
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

    public function getCompanyId(): int
    {
        return $this->companyId;
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
            'companyId' => $this->companyId,
            'name' => $this->name,
            'value' => $this->value,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
