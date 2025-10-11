<?php
/**
 * Query to fetch a shipment type by id for presentation.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\TypeShipment\Query;

final class GetTypeShipmentForView
{
    public function __construct(private readonly int $typeShipmentId)
    {
    }

    public function getTypeShipmentId(): int
    {
        return $this->typeShipmentId;
    }
}
