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

    /**
     * @return array<int, array{
     *     name: string,
     *     label?: string,
     *     value?: ?string,
     *     required?: bool,
     *     description?: string,
     *     legacy?: array<int, string>
     * }>
     */
    public static function getDefaultConfiguration(): array;
}
