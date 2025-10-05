<?php
/**
 * Command to create or update a type shipment.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\TypeShipment\Command;

final class UpsertTypeShipmentCommand
{
    public function __construct(
        private readonly ?int $typeShipmentId,
        private readonly int $companyId,
        private readonly string $name,
        private readonly string $businessCode,
        private readonly ?int $referenceCarrierId,
        private readonly bool $active
    ) {
    }

    public function getTypeShipmentId(): ?int
    {
        return $this->typeShipmentId;
    }

    public function getCompanyId(): int
    {
        return $this->companyId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getBusinessCode(): string
    {
        return $this->businessCode;
    }

    public function getReferenceCarrierId(): ?int
    {
        return $this->referenceCarrierId;
    }

    public function isActive(): bool
    {
        return $this->active;
    }
}
