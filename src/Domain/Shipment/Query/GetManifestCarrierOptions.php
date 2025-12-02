<?php
/**
 * Query to retrieve manifest carrier options for a date range.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Shipment\Query;

use DateTimeImmutable;

final class GetManifestCarrierOptions
{
    public function __construct(
        private readonly DateTimeImmutable $dateFrom,
        private readonly DateTimeImmutable $dateTo
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
}
