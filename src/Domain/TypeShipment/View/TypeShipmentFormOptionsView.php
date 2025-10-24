<?php
/**
 * View containing form options for shipment types.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\TypeShipment\View;

final class TypeShipmentFormOptionsView
{
    /**
     * @param array<string, int> $companies
     * @param array<string, int> $referenceCarriers
     */
    public function __construct(
        private readonly array $companies,
        private readonly array $referenceCarriers
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'companies' => $this->companies,
            'referenceCarriers' => $this->referenceCarriers,
        ];
    }
}
