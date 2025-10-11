<?php
/**
 * Query to retrieve configurations tied to a type shipment.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\TypeShipmentConfiguration\Query;

final class GetTypeShipmentConfigurations
{
    public function __construct(private readonly int $typeShipmentId)
    {
    }

    public function getTypeShipmentId(): int
    {
        return $this->typeShipmentId;
    }
}
