<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\InfoPackage\View;

use Roanja\Module\RjMulticarrier\Entity\InfoPackage;

final class InfoPackageView
{
    public function __construct(
        private readonly int $id,
        private readonly int $orderId,
        private readonly int $referenceCarrierId,
        private readonly int $typeShipmentId,
        private readonly string $typeShipmentName,
        private readonly int $companyId,
        private readonly string $companyName,
        private readonly ?string $companyShortName,
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

    public static function fromEntity(InfoPackage $infoPackage): self
    {
        $typeShipment = $infoPackage->getTypeShipment();
        $company = $typeShipment->getCompany();

        $vsecRaw = $infoPackage->getVsec();
        $vsec = null;
        if (null !== $vsecRaw) {
            $floatValue = (float) $vsecRaw;
            if ($floatValue > 0.0) {
                $vsec = $floatValue;
            }
        }

        $dorigRaw = $infoPackage->getDorig();
        $dorig = ($dorigRaw !== null && $dorigRaw !== '') ? $dorigRaw : null;

        return new self(
            $infoPackage->getId() ?? 0,
            $infoPackage->getOrderId(),
            $infoPackage->getReferenceCarrierId(),
            $typeShipment->getId() ?? 0,
            $typeShipment->getName(),
            $company->getId() ?? 0,
            $company->getName(),
            $company->getShortName(),
            $infoPackage->getQuantity(),
            $infoPackage->getWeight(),
            $infoPackage->getLength(),
            $infoPackage->getWidth(),
            $infoPackage->getHeight(),
            $infoPackage->getCashOnDelivery(),
            $infoPackage->getMessage(),
            $infoPackage->getHourFrom()?->format('H:i:s'),
            $infoPackage->getHourUntil()?->format('H:i:s'),
            $infoPackage->getRetorno(),
            $infoPackage->isRcsEnabled(),
            $vsec,
            $dorig,
            $infoPackage->getCreatedAt()?->format('Y-m-d H:i:s'),
            $infoPackage->getUpdatedAt()?->format('Y-m-d H:i:s')
        );
    }

    /**
     * Crea una instancia desde un array asociativo (como fetchAllAssociative de Doctrine).
     * @param array<string, mixed> $row
     */
    public static function fromArray(array $row): self
    {
        return new self(
            (int)($row['id_infopackage'] ?? 0),
            (int)($row['id_order'] ?? 0),
            (int)($row['id_reference_carrier'] ?? 0),
            (int)($row['id_type_shipment'] ?? 0),
            (string)($row['type_shipment_name'] ?? ''), // not present in table unless joined
            (int)($row['company_id'] ?? 0), // not present in table by default
            (string)($row['company_name'] ?? ''), // not present unless joined
            isset($row['company_short_name']) ? (string)$row['company_short_name'] : null,
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
            'companyId' => $this->companyId,
            'companyName' => $this->companyName,
            'companyShortName' => $this->companyShortName,
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
            $this->companyId,
            $this->companyName,
            $this->companyShortName,
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
