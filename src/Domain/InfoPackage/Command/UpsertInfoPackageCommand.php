<?php
/**
 * Command for creating or updating package information linked to an order shipment.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\InfoPackage\Command;

/**
 * @psalm-immutable
 */
final class UpsertInfoPackageCommand
{
    public function __construct(
        private readonly ?int $infoPackageId,
        private readonly int $orderId,
        private readonly int $referenceCarrierId,
        private readonly int $typeShipmentId,
        private readonly int $quantity,
        private readonly float $weight,
        private readonly ?float $length,
        private readonly ?float $width,
        private readonly ?float $height,
        private readonly ?string $cashOnDelivery,
        private readonly ?string $message,
        private readonly ?string $hourFrom,
        private readonly ?string $hourUntil,
        private readonly ?int $retorno,
        private readonly bool $rcs,
        private readonly ?string $vsec,
        private readonly ?string $dorig,
        private readonly int $shopId
    ) {
    }

    public function getInfoPackageId(): ?int
    {
        return $this->infoPackageId;
    }

    public function getOrderId(): int
    {
        return $this->orderId;
    }

    public function getReferenceCarrierId(): int
    {
        return $this->referenceCarrierId;
    }

    public function getTypeShipmentId(): int
    {
        return $this->typeShipmentId;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getWeight(): float
    {
        return $this->weight;
    }

    public function getLength(): ?float
    {
        return $this->length;
    }

    public function getWidth(): ?float
    {
        return $this->width;
    }

    public function getHeight(): ?float
    {
        return $this->height;
    }

    public function getCashOnDelivery(): ?string
    {
        return $this->cashOnDelivery;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function getHourFrom(): ?string
    {
        return $this->hourFrom;
    }

    public function getHourUntil(): ?string
    {
        return $this->hourUntil;
    }

    public function getRetorno(): ?int
    {
        return $this->retorno;
    }

    public function isRcsEnabled(): bool
    {
        return $this->rcs;
    }

    public function getVsec(): ?string
    {
        return $this->vsec;
    }

    public function getDorig(): ?string
    {
        return $this->dorig;
    }

    public function getShopId(): int
    {
        return $this->shopId;
    }
}
