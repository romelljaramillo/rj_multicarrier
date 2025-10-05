<?php
/**
 * Contract for carrier-specific shipment generators.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Carrier\Adapter;

interface CarrierAdapterInterface
{
    public function getCode(): string;

    public function generateShipment(CarrierContext $context): CarrierGenerationResult;
}
