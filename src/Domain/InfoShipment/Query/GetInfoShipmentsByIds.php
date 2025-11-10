<?php

declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\InfoShipment\Query;

final class GetInfoShipmentsByIds
{
    /**
     * @var int[]
     */
    private array $infoShipmentIds;

    /**
     * @param int[] $infoShipmentIds
     */
    public function __construct(array $infoShipmentIds)
    {
        $this->infoShipmentIds = $infoShipmentIds;
    }

    /**
     * @return int[]
     */
    public function getInfoShipmentIds(): array
    {
        return $this->infoShipmentIds;
    }
}
