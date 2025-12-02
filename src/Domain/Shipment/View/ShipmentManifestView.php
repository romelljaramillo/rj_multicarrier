<?php
/**
 * View model aggregating manifest metadata and rows.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Shipment\View;

use DateTimeImmutable;

final class ShipmentManifestView
{
    /**
     * @param ShipmentManifestItemView[] $items
     */
    public function __construct(
        private readonly DateTimeImmutable $dateFrom,
        private readonly DateTimeImmutable $dateTo,
        private readonly ?int $referenceCarrierId,
        private readonly ?string $referenceCarrierLabel,
        private readonly array $items
    ) {
    }

    public function getDateFrom(): DateTimeImmutable
    {
        return $this->dateFrom;
    }

    public function getDateTo(): DateTimeImmutable
    {
        return $this->dateTo;
    }

    public function getReferenceCarrierId(): ?int
    {
        return $this->referenceCarrierId;
    }

    public function getReferenceCarrierLabel(): ?string
    {
        return $this->referenceCarrierLabel;
    }

    /**
     * @return ShipmentManifestItemView[]
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function isEmpty(): bool
    {
        return 0 === count($this->items);
    }

    public function getShipmentCount(): int
    {
        return count($this->items);
    }

    public function getPackageCount(): int
    {
        $total = 0;

        foreach ($this->items as $item) {
            $total += $item->getPackageQuantity();
        }

        return $total;
    }

    public function getTotalWeight(): float
    {
        $weight = 0.0;

        foreach ($this->items as $item) {
            $weight += $item->getWeight();
        }

        return (float) round($weight, 3);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'date_from' => $this->dateFrom->format('Y-m-d'),
            'date_to' => $this->dateTo->format('Y-m-d'),
            'carrier' => [
                'id' => $this->referenceCarrierId,
                'label' => $this->referenceCarrierLabel,
            ],
            'items' => array_map(static fn (ShipmentManifestItemView $item): array => $item->toArray(), $this->items),
            'totals' => [
                'shipments' => $this->getShipmentCount(),
                'packages' => $this->getPackageCount(),
                'weight' => $this->getTotalWeight(),
            ],
        ];
    }
}
