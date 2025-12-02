<?php
/**
 * View model describing an option for manifest carrier selector.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Shipment\View;

final class ManifestCarrierView
{
    public function __construct(
        private readonly int $referenceCarrierId,
        private readonly string $label,
        private readonly int $shipmentCount
    ) {
    }

    public function getReferenceCarrierId(): int
    {
        return $this->referenceCarrierId;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getShipmentCount(): int
    {
        return $this->shipmentCount;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->referenceCarrierId,
            'label' => $this->label,
            'shipments' => $this->shipmentCount,
        ];
    }
}
