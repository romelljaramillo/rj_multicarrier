<?php
/**
 * Command to toggle carrier active flag from grid actions.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Carrier\Command;

final class ToggleCarrierStatusCommand
{
    public function __construct(private readonly int $carrierId)
    {
    }

    public function getCarrierId(): int
    {
        return $this->carrierId;
    }
}
