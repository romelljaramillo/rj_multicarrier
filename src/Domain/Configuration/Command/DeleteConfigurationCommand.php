<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Configuration\Command;

final class DeleteConfigurationCommand
{
    public function __construct(private readonly int $ConfigurationId)
    {
    }

    public function getConfigurationId(): int
    {
        return $this->ConfigurationId;
    }
}
