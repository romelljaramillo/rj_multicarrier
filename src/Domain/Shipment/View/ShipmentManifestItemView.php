<?php
/**
 * View model describing a single row inside a shipment manifest.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Shipment\View;

final class ShipmentManifestItemView
{
    public function __construct(
        private readonly int $shipmentId,
        private readonly int $orderId,
        private readonly ?string $orderReference,
        private readonly ?string $shipmentNumber,
        private readonly ?string $carrierShortName,
        private readonly ?string $typeShipmentName,
        private readonly int $packageQuantity,
        private readonly float $weight,
        private readonly ?string $createdAt,
        private readonly ?string $employeeName,
        private readonly int $referenceCarrierId
    ) {
    }

    public function getShipmentId(): int
    {
        return $this->shipmentId;
    }

    public function getOrderId(): int
    {
        return $this->orderId;
    }

    public function getOrderReference(): ?string
    {
        return $this->orderReference;
    }

    public function getShipmentNumber(): ?string
    {
        return $this->shipmentNumber;
    }

    public function getCarrierShortName(): ?string
    {
        return $this->carrierShortName;
    }

    public function getTypeShipmentName(): ?string
    {
        return $this->typeShipmentName;
    }

    public function getPackageQuantity(): int
    {
        return $this->packageQuantity;
    }

    public function getWeight(): float
    {
        return $this->weight;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function getEmployeeName(): ?string
    {
        return $this->employeeName;
    }

    public function getReferenceCarrierId(): int
    {
        return $this->referenceCarrierId;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'shipment_id' => $this->shipmentId,
            'order_id' => $this->orderId,
            'order_reference' => $this->orderReference,
            'shipment_number' => $this->shipmentNumber,
            'carrier_short_name' => $this->carrierShortName,
            'type_shipment_name' => $this->typeShipmentName,
            'quantity' => $this->packageQuantity,
            'weight' => $this->weight,
            'created_at' => $this->createdAt,
            'employee_name' => $this->employeeName,
            'reference_carrier_id' => $this->referenceCarrierId,
        ];
    }
}
