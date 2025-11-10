<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\InfoShipment\View;

use Roanja\Module\RjMulticarrier\Entity\InfoShipment;

final class InfoShipmentView
{
    public function __construct(
        private readonly int $id,
        private readonly int $orderId,
        private readonly int $referenceCarrierId,
        private readonly int $typeShipmentId,
        private readonly string $typeShipmentName,
        private readonly int $carrierId,
        private readonly string $carrierName,
        private readonly ?string $carrierShortName,
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
        private readonly ?float $vsec,
        private readonly ?string $dorig,
        private readonly ?string $createdAt,
        private readonly ?string $updatedAt
    ) {
    }

    public static function fromEntity(InfoShipment $infoShipment): self
    {
        $typeShipment = $infoShipment->getTypeShipment();
        $carrier = $typeShipment->getCarrier();

        $vsecRaw = $infoShipment->getVsec();
        $vsec = null;
        if (null !== $vsecRaw) {
            $floatValue = (float) $vsecRaw;
            if ($floatValue > 0.0) {
                $vsec = $floatValue;
            }
        }

        $dorigRaw = $infoShipment->getDorig();
        $dorig = ($dorigRaw !== null && $dorigRaw !== '') ? $dorigRaw : null;

        return new self(
            $infoShipment->getId() ?? 0,
            $infoShipment->getOrderId(),
            $infoShipment->getReferenceCarrierId(),
            $typeShipment->getId() ?? 0,
            $typeShipment->getName(),
            $carrier->getId() ?? 0,
            $carrier->getName(),
            $carrier->getShortName(),
            $infoShipment->getQuantity(),
            $infoShipment->getWeight(),
            $infoShipment->getLength(),
            $infoShipment->getWidth(),
            $infoShipment->getHeight(),
            $infoShipment->getCashOnDelivery(),
            $infoShipment->getMessage(),
            $infoShipment->getHourFrom()?->format('H:i:s'),
            $infoShipment->getHourUntil()?->format('H:i:s'),
            $infoShipment->getRetorno(),
            $infoShipment->isRcsEnabled(),
            $vsec,
            $dorig,
            $infoShipment->getCreatedAt()?->format('Y-m-d H:i:s'),
            $infoShipment->getUpdatedAt()?->format('Y-m-d H:i:s')
        );
    }

    /**
     * Crea una instancia desde un array asociativo (como fetchAllAssociative de Doctrine).
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            (int)($row['id_info_shipment'] ?? 0),
            (int)($row['id_order'] ?? 0),
            (int)($row['id_reference_carrier'] ?? 0),
            (int)($row['id_type_shipment'] ?? 0),
            (string)($row['type_shipment_name'] ?? ''), // not present in table unless joined
            (int)($row['carrier_id'] ?? $row['company_id'] ?? 0), // not present in table by default
            (string)($row['carrier_name'] ?? $row['company_name'] ?? ''), // not present unless joined
            isset($row['carrier_short_name']) ? (string)$row['carrier_short_name'] : (isset($row['company_short_name']) ? (string)$row['company_short_name'] : null),
            (int)($row['quantity'] ?? 0),
            (float)($row['weight'] ?? 0),
            isset($row['length']) ? (float)$row['length'] : null,
            isset($row['width']) ? (float)$row['width'] : null,
            isset($row['height']) ? (float)$row['height'] : null,
            isset($row['cash_ondelivery']) ? (string)$row['cash_ondelivery'] : null,
            isset($row['message']) ? (string)$row['message'] : null,
            isset($row['hour_from']) ? (string)$row['hour_from'] : null,
            isset($row['hour_until']) ? (string)$row['hour_until'] : null,
            isset($row['retorno']) ? (int)$row['retorno'] : null,
            (bool)($row['rcs'] ?? false),
            isset($row['vsec']) ? (float)$row['vsec'] : null,
            isset($row['dorig']) ? (string)$row['dorig'] : null,
            isset($row['date_add']) ? (string)$row['date_add'] : null,
            isset($row['date_upd']) ? (string)$row['date_upd'] : null
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'orderId' => $this->orderId,
            'referenceCarrierId' => $this->referenceCarrierId,
            'typeShipmentId' => $this->typeShipmentId,
            'typeShipmentName' => $this->typeShipmentName,
            'carrierId' => $this->carrierId,
            'companyId' => $this->carrierId,
            'companyName' => $this->carrierName,
            'companyShortName' => $this->carrierShortName,
            'quantity' => $this->quantity,
            'weight' => $this->weight,
            'length' => $this->length,
            'width' => $this->width,
            'height' => $this->height,
            'cashOnDelivery' => $this->cashOnDelivery,
            'message' => $this->message,
            'hourFrom' => $this->hourFrom,
            'hourUntil' => $this->hourUntil,
            'retorno' => $this->retorno,
            'rcs' => $this->rcs,
            'vsec' => $this->vsec,
            'dorig' => $this->dorig,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
    }

    public function toCsvRow(): array
    {
        return [
            $this->id,
            $this->orderId,
            $this->referenceCarrierId,
            $this->typeShipmentId,
            $this->typeShipmentName,
            $this->carrierId,
            $this->carrierName,
            $this->carrierShortName,
            $this->quantity,
            $this->weight,
            $this->length,
            $this->width,
            $this->height,
            $this->cashOnDelivery,
            $this->message,
            $this->hourFrom,
            $this->hourUntil,
            $this->retorno,
            $this->rcs,
            $this->vsec,
            $this->dorig,
            $this->createdAt,
            $this->updatedAt,
        ];
    }
}
