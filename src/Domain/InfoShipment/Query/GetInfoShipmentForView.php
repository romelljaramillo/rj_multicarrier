<?php
/**
 * Query to fetch info package details for presentation.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\InfoShipment\Query;

final class GetInfoShipmentForView
{
    public function __construct(private readonly int $infoShipmentId)
    {
    }

    public function getInfoShipmentId(): int
    {
        return $this->infoShipmentId;
    }
}
