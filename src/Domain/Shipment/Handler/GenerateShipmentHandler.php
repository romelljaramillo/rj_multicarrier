<?php
/**
 * Handles shipment generation by delegating to carrier adapters and persisting via CQRS.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Shipment\Handler;

use Roanja\Module\RjMulticarrier\Carrier\Adapter\CarrierContext;
use Roanja\Module\RjMulticarrier\Carrier\Adapter\CarrierRegistry;
use Roanja\Module\RjMulticarrier\Domain\Shipment\Command\CreateShipmentCommand;
use Roanja\Module\RjMulticarrier\Domain\Shipment\Command\GenerateShipmentCommand;
use Roanja\Module\RjMulticarrier\Entity\Shipment as ShipmentEntity;
use RuntimeException;

final class GenerateShipmentHandler
{
    public function __construct(
        private readonly CarrierRegistry $carrierRegistry,
        private readonly CreateShipmentHandler $createShipmentHandler
    ) {
    }

    public function handle(GenerateShipmentCommand $command): ShipmentEntity
    {
        $payload = $command->getPayload();

        $context = new CarrierContext(
            $command->getCarrierCode(),
            $command->getOrderId(),
            $command->getOrderReference(),
            $command->getShipmentNumber(),
            $payload,
            $command->getOptions()
        );

        $adapter = $this->carrierRegistry->get($command->getCarrierCode());
        $result = $adapter->generateShipment($context);

        $normalizedPayload = $result->getRequestPayload() ?? $payload;
        $normalizedPayload['num_shipment'] = $result->getShipmentNumber();

        $infoPackage = (array) ($normalizedPayload['info_package'] ?? []);
        $infoPackageId = (int) ($infoPackage['id_infopackage'] ?? 0);

        if ($infoPackageId <= 0) {
            throw new RuntimeException('Missing info package identifier when generating shipment.');
        }

        $companyData = (array) ($normalizedPayload['info_company_carrier'] ?? []);
        $companyId = isset($companyData['id_carrier_company'])
            ? (int) $companyData['id_carrier_company']
            : null;

    $createShipmentCommand = new CreateShipmentCommand(
            $command->getOrderId(),
            $command->getOrderReference(),
            $result->getShipmentNumber(),
            $infoPackageId,
            $companyId,
            isset($normalizedPayload['name_carrier']) ? (string) $normalizedPayload['name_carrier'] : null,
            $normalizedPayload,
            $result->getResponsePayload(),
            $result->getLabels()
        );

        return $this->createShipmentHandler->handle($createShipmentCommand);
    }
}
