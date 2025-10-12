<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Carrier\Command;

final class DeleteCarrierCommand
{
    public function __construct(private readonly int $carrierId)
    {
    }

    public function getCarrierId(): int
    {
        return $this->carrierId;
    }
}
