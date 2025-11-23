<?php
/**
 * Provides choice lists for the inline info shipment editor.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Service\InlineEntry;

use \Employee;
use Roanja\Module\RjMulticarrier\Entity\Carrier;
use Roanja\Module\RjMulticarrier\Entity\TypeShipment;
use Roanja\Module\RjMulticarrier\Repository\CarrierRepository;
use Roanja\Module\RjMulticarrier\Repository\TypeShipmentRepository;

final class InlineFieldChoicesProvider
{
    public function __construct(
        private readonly CarrierRepository $carrierRepository,
        private readonly TypeShipmentRepository $typeShipmentRepository
    ) {
    }

    /**
     * @return array<int, array{id: int, label: string, carrier_id: int, reference_carrier_id: int}>
     */
    public function getTypeShipmentChoices(): array
    {
        $carriers = $this->carrierRepository->findAllOrdered();
        if ([] === $carriers) {
            return [];
        }

        $choices = [];

        foreach ($carriers as $carrier) {
            if (!$carrier instanceof Carrier) {
                continue;
            }

            $types = $this->typeShipmentRepository->findByCarrier($carrier, true);
            if ([] === $types) {
                continue;
            }

            foreach ($types as $type) {
                if (!$type instanceof TypeShipment) {
                    continue;
                }

                $choices[] = [
                    'id' => (int) $type->getId(),
                    'label' => sprintf('%s · %s', $carrier->getShortName(), $type->getName()),
                    'carrier_id' => (int) $carrier->getId(),
                    'reference_carrier_id' => (int) $type->getReferenceCarrierId(),
                ];
            }
        }

        usort($choices, static function (array $left, array $right): int {
            return strcasecmp($left['label'], $right['label']);
        });

        return $choices;
    }

    /**
     * @return array<int, array{id: int, label: string}>
     */
    public function getEmployeeChoices(): array
    {
        if (!class_exists(Employee::class)) {
            return [];
        }

        $employees = Employee::getEmployees();

        if (!is_array($employees) || [] === $employees) {
            return [];
        }

        $choices = [];

        foreach ($employees as $employee) {
            $id = (int) ($employee['id_employee'] ?? 0);

            if ($id <= 0) {
                continue;
            }

            $fullName = trim(sprintf('%s %s', (string) ($employee['firstname'] ?? ''), (string) ($employee['lastname'] ?? '')));

            if ('' === $fullName) {
                $fullName = sprintf('#%d', $id);
            }

            $choices[] = [
                'id' => $id,
                'label' => $fullName,
            ];
        }

        usort($choices, static function (array $left, array $right): int {
            return strcasecmp($left['label'], $right['label']);
        });

        return $choices;
    }
}
