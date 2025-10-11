<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\InfoShop\Command;

final class DeleteInfoShopCommand
{
    public function __construct(private readonly int $infoShopId)
    {
    }

    public function getInfoShopId(): int
    {
        return $this->infoShopId;
    }
}
