<?php
/**
 * Read model representing shipment details.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Shipment\View;

use Roanja\Module\RjMulticarrier\Entity\Label;
use Roanja\Module\RjMulticarrier\Entity\Shipment;

final class ShipmentView
{
    /**
     * @param array<string, mixed> $package
     * @param array<int, array<string, mixed>> $labels
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        private readonly int $id,
        private readonly int $orderId,
        private readonly ?string $orderReference,
        private readonly ?string $shipmentNumber,
        private readonly ?string $carrierShortName,
        private readonly array $package,
        private readonly array $labels,
        private readonly array $metadata
    ) {
    }

    public function getId(): int
    {
        return $this->id;
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

    /**
     * @return array<string, mixed>
     */
    public function getPackage(): array
    {
        return $this->package;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getLabels(): array
    {
        return $this->labels;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * Create a view from Doctrine entities.
     *
     * @param Label[] $labels
     */
    public static function fromEntities(
        Shipment $shipment,
        array $labels,
        array $package,
        array $metadata
    ): self {
        $company = $shipment->getCompany();

        return new self(
            $shipment->getId() ?? 0,
            $shipment->getOrderId(),
            $shipment->getOrderReference(),
            $shipment->getShipmentNumber(),
            $company?->getShortName(),
            $package,
            array_map(static function (Label $label): array {
                return [
                    'id' => $label->getId(),
                    'package_id' => $label->getPackageId(),
                    'tracker_code' => $label->getTrackerCode(),
                    'label_type' => $label->getLabelType(),
                    'printed' => $label->isPrinted(),
                ];
            }, $labels),
            $metadata
        );
    }
}
