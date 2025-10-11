<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Company\View;

final class CompanyView
{
    public function __construct(
        private int $id,
        private string $name,
        private string $shortName
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'shortName' => $this->shortName,
        ];
    }
}
