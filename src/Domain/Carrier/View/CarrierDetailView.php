<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Carrier\View;

final class CarrierDetailView
{
    public function __construct(
        private int $id,
        private string $name,
        private string $shortName,
        private ?string $icon,
        private array $shops,
        private bool $isActive,
        private bool $supportsApi,
        private ?string $adapterService,
        private ?array $configurationSchema,
        private ?array $defaultPayload,
        private ?string $createdAt,
        private ?string $updatedAt
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'shortName' => $this->shortName,
            'icon' => $this->icon,
            'shops' => $this->shops,
            'isActive' => $this->isActive,
            'supportsApi' => $this->supportsApi,
            'adapterService' => $this->adapterService,
            'configurationSchema' => $this->configurationSchema,
            'defaultPayload' => $this->defaultPayload,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
