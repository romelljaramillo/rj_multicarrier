<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Carrier\Handler;

use Roanja\Module\RjMulticarrier\Domain\Carrier\Query\GetCarriersForExport;
use Roanja\Module\RjMulticarrier\Repository\CarrierRepository;

final class GetCarriersForExportHandler
{
    public function __construct(private readonly CarrierRepository $carrierRepository)
    {
    }

    /**
     * @return array<int, array<string,mixed>>
     */
    public function handle(GetCarriersForExport $query): array
    {
        $carriers = $this->carrierRepository->findAllOrdered();

        $rows = [];
        foreach ($carriers as $carrier) {
            $rows[] = [
                'id' => $carrier->getId(),
                'name' => $carrier->getName(),
                'shortName' => $carrier->getShortName(),
            ];
        }

        return $rows;
    }
}
