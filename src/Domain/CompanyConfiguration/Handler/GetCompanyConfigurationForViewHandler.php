<?php
/**
 * Handles retrieving a single company configuration for view/edit.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\CompanyConfiguration\Handler;

use Roanja\Module\RjMulticarrier\Domain\CompanyConfiguration\Exception\CompanyConfigurationNotFoundException;
use Roanja\Module\RjMulticarrier\Domain\CompanyConfiguration\Query\GetCompanyConfigurationForView;
use Roanja\Module\RjMulticarrier\Domain\CompanyConfiguration\View\CompanyConfigurationView;
use Roanja\Module\RjMulticarrier\Repository\CarrierConfigurationRepository;

final class GetCompanyConfigurationForViewHandler
{
    public function __construct(private readonly CarrierConfigurationRepository $configurationRepository)
    {
    }

    public function handle(GetCompanyConfigurationForView $query): CompanyConfigurationView
    {
        $configuration = $this->configurationRepository->find($query->getConfigurationId());

        if (null === $configuration || null === $configuration->getCompany()) {
            throw CompanyConfigurationNotFoundException::forId($query->getConfigurationId());
        }

        return new CompanyConfigurationView(
            $configuration->getId(),
            $configuration->getCompany()->getId() ?? 0,
            $configuration->getName(),
            $configuration->getValue(),
            $configuration->getCreatedAt()?->format('Y-m-d H:i:s'),
            $configuration->getUpdatedAt()?->format('Y-m-d H:i:s')
        );
    }
}
