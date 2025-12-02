<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Carrier\Command;

final class UpdateCarrierCommand
{
    /**
     * @param int[] $shopIds
     */
    public function __construct(
        private readonly int $carrierId,
        private readonly string $name,
        private readonly string $shortName,
        private readonly ?string $iconFilename,
        private readonly array $shopIds,
        private readonly bool $isActive,
        private readonly bool $supportsApi,
        private readonly ?string $adapterService,
        private readonly ?array $configurationSchema,
        private readonly ?array $defaultPayload,
    ) {
    }

    public function getCarrierId(): int
    {
        return $this->carrierId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getShortName(): string
    {
        return $this->shortName;
    }

    public function getIconFilename(): ?string
    {
        return $this->iconFilename;
    }

    /**
     * @return int[]
     */
    public function getShopIds(): array
    {
        return $this->shopIds;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function supportsApi(): bool
    {
        return $this->supportsApi;
    }

    public function getAdapterService(): ?string
    {
        return $this->adapterService;
    }

    public function getConfigurationSchema(): ?array
    {
        return $this->configurationSchema;
    }

    public function getDefaultPayload(): ?array
    {
        return $this->defaultPayload;
    }
}
