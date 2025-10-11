<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\TypeShipmentConfiguration\Handler;

use Roanja\Module\RjMulticarrier\Domain\TypeShipmentConfiguration\Query\GetTypeShipmentConfigurations;
use Roanja\Module\RjMulticarrier\Domain\TypeShipmentConfiguration\View\TypeShipmentConfigurationView;
use Roanja\Module\RjMulticarrier\Repository\CarrierConfigurationRepository;
use Roanja\Module\RjMulticarrier\Repository\TypeShipmentRepository;

final class GetTypeShipmentConfigurationsHandler
{
    public function __construct(
        private readonly TypeShipmentRepository $typeShipmentRepository,
        private readonly CarrierConfigurationRepository $configurationRepository,
    ) {
    }

    /**
     * @return TypeShipmentConfigurationView[]
     */
    public function handle(GetTypeShipmentConfigurations $query): array
    {
        $typeShipment = $this->typeShipmentRepository->find($query->getTypeShipmentId());

        if (null === $typeShipment) {
            return [];
        }

        $entries = $this->configurationRepository->findByTypeShipment($typeShipment);
        $companyId = $typeShipment->getCompany()->getId() ?? 0;
        $views = [];

        foreach ($entries as $entry) {
            $views[] = new TypeShipmentConfigurationView(
                $entry->getId(),
                $typeShipment->getId() ?? 0,
                $companyId,
                $entry->getName(),
                $entry->getValue(),
                $entry->getCreatedAt()?->format('Y-m-d H:i:s'),
                $entry->getUpdatedAt()?->format('Y-m-d H:i:s')
            );
        }

        return $views;
    }
}
