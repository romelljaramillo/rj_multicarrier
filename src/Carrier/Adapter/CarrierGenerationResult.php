<?php
/**
 * Value object returned by carrier adapters.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Carrier\Adapter;

final class CarrierGenerationResult
{
    /**
     * @param array<string, mixed>|null $requestPayload
     * @param array<string, mixed>|null $responsePayload
     * @param array<int, array<string, mixed>> $labels
     */
    public function __construct(
        private readonly string $shipmentNumber,
        private readonly ?array $requestPayload,
        private readonly ?array $responsePayload,
        private readonly array $labels
    ) {
    }

    public function getShipmentNumber(): string
    {
        return $this->shipmentNumber;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getRequestPayload(): ?array
    {
        return $this->requestPayload;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getResponsePayload(): ?array
    {
        return $this->responsePayload;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getLabels(): array
    {
        return $this->labels;
    }
}
