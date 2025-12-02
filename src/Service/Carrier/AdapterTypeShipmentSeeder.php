<?php
/**
 * Seeds predefined shipment types after installing a carrier adapter.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Service\Carrier;

use Doctrine\ORM\EntityManagerInterface;
use Roanja\Module\RjMulticarrier\Entity\Carrier;
use Roanja\Module\RjMulticarrier\Entity\TypeShipment;
use Roanja\Module\RjMulticarrier\Repository\TypeShipmentRepository;

final class AdapterTypeShipmentSeeder
{
    /**
     * @var array<string, array<int, array{name:string, code:string, reference?:?int, active?:bool}>>
     */
    private const DEFINITIONS = [
        'CEX' => [
            ['name' => 'PAQ 10', 'code' => '61'],
            ['name' => 'PAQ 14', 'code' => '62'],
            ['name' => 'PAQ 24', 'code' => '63'],
            ['name' => 'Baleares', 'code' => '66'],
            ['name' => 'Canarias Express', 'code' => '67'],
            ['name' => 'Canarias Aéreo', 'code' => '68'],
            ['name' => 'Canarias Marítimo', 'code' => '69'],
            ['name' => 'CEX Portugal Óptica', 'code' => '73'],
            ['name' => 'Paquetería Óptica', 'code' => '76'],
            ['name' => 'Internacional Express', 'code' => '91'],
            ['name' => 'Internacional Estandard', 'code' => '90'],
            ['name' => 'Paq Empresa 14', 'code' => '92'],
            ['name' => 'ePaq 24', 'code' => '93'],
            ['name' => 'Campaña CEX', 'code' => '27'],
            ['name' => 'Entrega en Oficina', 'code' => '44'],
            ['name' => 'Entrega + Recogida Multichrono', 'code' => '54'],
            ['name' => 'Entrega + recogida + Manip Multichrono', 'code' => '55'],
        ],
        'DHL' => [
            ['name' => 'DHL PARCEL IBERIA', 'code' => 'IBERIA'],
        ],
        'GOI' => [
            ['name' => 'Goi carrier', 'code' => 'T,M', 'reference' => 2],
            ['name' => 'GOI - Montaje', 'code' => 'T,I,M', 'reference' => 1],
        ],
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TypeShipmentRepository $typeShipmentRepository
    ) {
    }

    public function seedForAdapter(Carrier $carrier, string $adapterCode): void
    {
        $definitions = self::DEFINITIONS[strtoupper($adapterCode)] ?? [];

        if (empty($definitions)) {
            return;
        }

        $hasChanges = false;

        foreach ($definitions as $definition) {
            if ($this->typeShipmentRepository->findOneByCarrierAndBusinessCode($carrier, $definition['code'])) {
                continue;
            }

            $typeShipment = new TypeShipment($carrier, $definition['name'], $definition['code']);

            if (array_key_exists('active', $definition)) {
                $typeShipment->setActive((bool) $definition['active']);
            }

            if (array_key_exists('reference', $definition)) {
                $typeShipment->setReferenceCarrierId(null === $definition['reference'] ? null : (int) $definition['reference']);
            }

            $this->entityManager->persist($typeShipment);
            $hasChanges = true;
        }

        if ($hasChanges) {
            $this->entityManager->flush();
        }
    }
}
