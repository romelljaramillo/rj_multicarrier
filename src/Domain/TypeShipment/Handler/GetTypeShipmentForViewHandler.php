<?php
/**
 * Handler building a read model for shipment type detail view.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\TypeShipment\Handler;

use Roanja\Module\RjMulticarrier\Domain\TypeShipment\Query\GetTypeShipmentForView;
use Roanja\Module\RjMulticarrier\Domain\TypeShipment\View\TypeShipmentView;
use Roanja\Module\RjMulticarrier\Repository\TypeShipmentRepository;

final class GetTypeShipmentForViewHandler
{
    public function __construct(private readonly TypeShipmentRepository $typeShipmentRepository)
    {
    }

    public function handle(GetTypeShipmentForView $query): ?TypeShipmentView
    {
        $typeShipment = $this->typeShipmentRepository->findOneById($query->getTypeShipmentId());

        if (!$typeShipment) {
            return null;
        }

        return TypeShipmentView::fromEntity($typeShipment);
    }
}
