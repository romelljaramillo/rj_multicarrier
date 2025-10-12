<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Carrier\Handler;

use Roanja\Module\RjMulticarrier\Domain\Carrier\Query\GetCarriersForGrid;
use Roanja\Module\RjMulticarrier\Repository\CarrierRepository;

final class GetCarriersForGridHandler
{
    public function __construct(private readonly CarrierRepository $carrierRepository)
    {
    }

    /**
     * @return array<int, array<string,mixed>> Rows compatible with the Carrier grid
     */
    public function handle(GetCarriersForGrid $query): array
    {
        // For now we ignore filters and return all ordered companies. The Doctrine Grid uses its own
        // QueryBuilder for pagination/search; this handler is useful for export or other flows.
        $carriers = $this->carrierRepository->findAllOrdered();

        $rows = [];
        foreach ($carriers as $carrier) {
            $icon = $carrier->getIcon();
            $iconUrl = $icon ? (_MODULE_DIR_ . 'rj_multicarrier/var/icons/' . $icon) : null;

            $rows[] = [
                'id_carrier' => $carrier->getId(),
                'name' => $carrier->getName(),
                'icon' => $iconUrl,
                'shortname' => $carrier->getShortName(),
            ];
        }

        return $rows;
    }
}
