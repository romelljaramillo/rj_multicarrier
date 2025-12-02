<?php
/**
 * Value object describing a carrier adapter for installation/UI metadata.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Carrier\Adapter;

final class CarrierAdapterDescriptor
{
    public function __construct(
        private readonly string $code,
        private readonly string $displayName,
        private readonly string $description,
        private readonly ?string $iconPath = null,
        private readonly bool $supportsApi = true,
        private readonly ?array $configurationSchema = null,
        private readonly ?array $defaultPayload = null,
    ) {
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getIconPath(): ?string
    {
        return $this->iconPath;
    }

    public function supportsApi(): bool
    {
        return $this->supportsApi;
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
