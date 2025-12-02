<?php
/**
 * Query to retrieve shipment manifest data for a date range and optional carrier reference.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Shipment\Query;

use DateTimeImmutable;

final class GetShipmentManifest
{
    public function __construct(
        private readonly DateTimeImmutable $dateFrom,
        private readonly DateTimeImmutable $dateTo,
        private readonly ?int $referenceCarrierId = null
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
}
