<?php
/**
 * Value object representing shipment type data for presentation.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\TypeShipment\View;

use Roanja\Module\RjMulticarrier\Entity\TypeShipment;

final class TypeShipmentView
{
    public function __construct(
        private readonly int $id,
        private readonly int $carrierId,
        private readonly string $carrierName,
        private readonly ?string $carrierShortName,
        private readonly string $name,
        private readonly string $businessCode,
        private readonly ?int $referenceCarrierId,
        private readonly bool $active,
        private readonly ?string $createdAt,
        private readonly ?string $updatedAt
    ) {
    }

    public static function fromEntity(TypeShipment $typeShipment): self
    {
        $carrier = $typeShipment->getCarrier();

        return new self(
            $typeShipment->getId() ?? 0,
            $carrier->getId() ?? 0,
            $carrier->getName(),
            $carrier->getShortName(),
            $typeShipment->getName(),
            $typeShipment->getBusinessCode(),
            $typeShipment->getReferenceCarrierId(),
            $typeShipment->isActive(),
            $typeShipment->getCreatedAt()?->format('Y-m-d H:i:s'),
            $typeShipment->getUpdatedAt()?->format('Y-m-d H:i:s')
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'carrierId' => $this->carrierId,
            'companyId' => $this->carrierId,
            'companyName' => $this->carrierName,
            'companyShortName' => $this->carrierShortName,
            'name' => $this->name,
            'businessCode' => $this->businessCode,
            'referenceCarrierId' => $this->referenceCarrierId,
            'active' => $this->active,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }
}
