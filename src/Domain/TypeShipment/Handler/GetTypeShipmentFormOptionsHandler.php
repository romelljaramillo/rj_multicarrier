<?php
/**
 * Handler providing form options for shipment types.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\TypeShipment\Handler;

use Roanja\Module\RjMulticarrier\Domain\TypeShipment\Query\GetTypeShipmentFormOptions;
use Roanja\Module\RjMulticarrier\Domain\TypeShipment\View\TypeShipmentFormOptionsView;
use Roanja\Module\RjMulticarrier\Entity\TypeShipment;
use Roanja\Module\RjMulticarrier\Form\TypeShipment\TypeShipmentFormOptionsProvider;
use Roanja\Module\RjMulticarrier\Repository\TypeShipmentRepository;

final class GetTypeShipmentFormOptionsHandler
{
    public function __construct(
        private readonly TypeShipmentFormOptionsProvider $formOptionsProvider,
        private readonly TypeShipmentRepository $typeShipmentRepository
    ) {
    }

    public function handle(GetTypeShipmentFormOptions $query): TypeShipmentFormOptionsView
    {
        $referenceCarrierId = null;

        $typeShipmentId = $query->getTypeShipmentId();
        if (null !== $typeShipmentId) {
            $typeShipment = $this->typeShipmentRepository->findOneById($typeShipmentId);
            if ($typeShipment instanceof TypeShipment) {
                $referenceCarrierId = $typeShipment->getReferenceCarrierId();
            }
        }

        $companies = $this->formOptionsProvider->getCompaniesForContext();
        $companyChoices = $this->formOptionsProvider->buildCompanyChoices($companies, null, false);
        $referenceChoices = $this->formOptionsProvider->buildReferenceCarrierChoices($referenceCarrierId);

        return new TypeShipmentFormOptionsView($companyChoices, $referenceChoices);
    }
}
