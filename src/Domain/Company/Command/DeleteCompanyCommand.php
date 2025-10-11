<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Company\Command;

final class DeleteCompanyCommand
{
    public function __construct(private readonly int $companyId)
    {
    }

    public function getCompanyId(): int
    {
        return $this->companyId;
    }
}
