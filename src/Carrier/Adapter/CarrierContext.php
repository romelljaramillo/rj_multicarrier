<?php
/**
 * Immutable context passed to carrier adapters.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Carrier\Adapter;

final class CarrierContext
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed> $options
     */
    public function __construct(
        private readonly string $carrierCode,
        private readonly int $orderId,
        private readonly ?string $orderReference,
        private readonly string $shipmentNumber,
        private readonly array $payload,
        private readonly array $options = []
    ) {
    }

    public function getCarrierCode(): string
    {
        return $this->carrierCode;
    }

    public function getOrderId(): int
    {
        return $this->orderId;
    }

    public function getOrderReference(): ?string
    {
        return $this->orderReference;
    }

    public function getShipmentNumber(): string
    {
        return $this->shipmentNumber;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return $this->options;
    }
}
