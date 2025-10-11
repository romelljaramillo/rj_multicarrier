<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Company\Command;

final class UpdateCompanyCommand
{
    /**
     * @param int[] $shopIds
     */
    public function __construct(
        private readonly int $companyId,
        private readonly string $name,
        private readonly string $shortName,
        private readonly ?string $iconFilename,
        private readonly array $shopIds
    ) {
    }

    public function getCompanyId(): int
    {
        return $this->companyId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getShortName(): string
    {
        return $this->shortName;
    }

    public function getIconFilename(): ?string
    {
        return $this->iconFilename;
    }

    /**
     * @return int[]
     */
    public function getShopIds(): array
    {
        return $this->shopIds;
    }
}
