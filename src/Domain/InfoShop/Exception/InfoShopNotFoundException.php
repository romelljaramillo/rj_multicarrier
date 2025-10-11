<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\InfoShop\Exception;

final class InfoShopNotFoundException extends \RuntimeException
{
    public static function forId(int $id): self
    {
        return new self(sprintf('InfoShop with id %d not found', $id));
    }
}
