<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\InfoShop\Query;

final class GetInfoShopForEdit
{
    public function __construct(private readonly int $infoShopId)
    {
    }

    public function getInfoShopId(): int
    {
        return $this->infoShopId;
    }
}
