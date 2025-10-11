<?php
/**
 * Command to toggle active status of an InfoShop entry.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\InfoShop\Command;

final class ToggleInfoShopStatusCommand
{
    public function __construct(private readonly int $infoShopId)
    {
    }

    public function getInfoShopId(): int
    {
        return $this->infoShopId;
    }
}
