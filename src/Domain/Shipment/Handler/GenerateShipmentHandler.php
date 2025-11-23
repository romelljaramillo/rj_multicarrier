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
    ) {}

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
        $infoPackageId = (int) ($infoPackage['id_info_shipment'] ?? 0);

        if ($infoPackageId <= 0) {
            throw new RuntimeException('Missing info package identifier when generating shipment.');
        }

        $companyData = (array) ($normalizedPayload['info_company_carrier'] ?? []);
        $companyId = isset($companyData['id_carrier'])
            ? (int) $companyData['id_carrier']
            : null;

        [$employeeId, $employeeName] = $this->resolveEmployeeContext();

        $createShipmentCommand = new CreateShipmentCommand(
            $command->getOrderId(),
            $command->getOrderReference(),
            $result->getShipmentNumber(),
            $infoPackageId,
            $companyId,
            // pass current shop id for mapping
            (int) (\Context::getContext()->shop->id ?? 0),
            isset($normalizedPayload['name_carrier']) ? (string) $normalizedPayload['name_carrier'] : null,
            $normalizedPayload,
            $result->getResponsePayload(),
            $result->getLabels(),
            $employeeId,
            $employeeName
        );

        return $this->createShipmentHandler->handle($createShipmentCommand);
    }

    /**
     * @return array{0:?int,1:?string}
     */
    private function resolveEmployeeContext(): array
    {
        $context = \Context::getContext();
        $employee = $context->employee ?? null;

        if (null === $employee) {
            return [null, null];
        }

        $employeeId = isset($employee->id) ? (int) $employee->id : null;
        $fullName = trim(sprintf('%s %s', (string) ($employee->firstname ?? ''), (string) ($employee->lastname ?? '')));

        if ('' === $fullName && isset($employee->email)) {
            $fullName = (string) $employee->email;
        }

        return [$employeeId, '' !== $fullName ? $fullName : null];
    }
}
