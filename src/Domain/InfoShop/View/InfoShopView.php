<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\InfoShop\View;

final class InfoShopView
{
    /**
     * @param array<string, mixed> $formData
     */
    public function __construct(
        private readonly int $shopId,
        private readonly array $formData
    ) {
    }

    public function getShopId(): int
    {
        return $this->shopId;
    }

    /**
     * @return array<string, mixed>
     */
    public function getFormData(): array
    {
        return $this->formData;
    }
}
