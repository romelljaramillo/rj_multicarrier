<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Configuration\Command;

final class SaveExtraConfigurationCommand
{
    public function __construct(
        private readonly string $labelPrefix,
        private readonly string $cashOnDeliveryModule
    ) {
    }

    public function getLabelPrefix(): string
    {
        return $this->labelPrefix;
    }

    public function getCashOnDeliveryModule(): string
    {
        return $this->cashOnDeliveryModule;
    }
}
