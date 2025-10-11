<?php
/**
 * Command for creating or updating shipments from legacy flow.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Shipment\Command;

/**
 * @psalm-immutable
 */
final class CreateShipmentCommand
{
    /**
     * @param array<string, mixed> $requestPayload
     * @param array<string, mixed>|null $responsePayload
     * @param array<int, array<string, mixed>> $labels
     */
    public function __construct(
        private readonly int $orderId,
        private readonly ?string $orderReference,
        private readonly ?string $shipmentNumber,
        private readonly int $infoPackageId,
        private readonly ?int $companyId,
        private readonly int $shopId,
        private readonly ?string $product,
        private readonly array $requestPayload,
        private readonly ?array $responsePayload,
        private readonly array $labels
    ) {
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

    public function getInfoPackageId(): int
    {
        return $this->infoPackageId;
    }

    public function getCompanyId(): ?int
    {
        return $this->companyId;
    }

    public function getShopId(): int
    {
        return $this->shopId;
    }

    public function getProduct(): ?string
    {
        return $this->product;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRequestPayload(): array
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
