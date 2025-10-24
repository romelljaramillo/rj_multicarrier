<?php
/**
 * Query to fetch form options for shipment types.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\TypeShipment\Query;

final class GetTypeShipmentFormOptions
{
    public function __construct(private readonly ?int $typeShipmentId = null)
    {
    }

    public function getTypeShipmentId(): ?int
    {
        return $this->typeShipmentId;
    }
}
