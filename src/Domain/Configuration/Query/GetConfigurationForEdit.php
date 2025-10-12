<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Configuration\Query;

final class GetConfigurationForEdit
{
    public function __construct(private readonly int $ConfigurationId)
    {
    }

    public function getConfigurationId(): int
    {
        return $this->ConfigurationId;
    }
}
