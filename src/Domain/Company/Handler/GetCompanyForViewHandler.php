<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Company\Handler;

use Roanja\Module\RjMulticarrier\Domain\Company\Query\GetCompanyForView;
use Roanja\Module\RjMulticarrier\Domain\Company\View\CompanyDetailView;
use Roanja\Module\RjMulticarrier\Repository\CompanyRepository;

final class GetCompanyForViewHandler
{
    public function __construct(private CompanyRepository $companyRepository)
    {
    }

    public function handle(GetCompanyForView $query): ?CompanyDetailView
    {
        $company = $this->companyRepository->find($query->getId());

        if (null === $company) {
            return null;
        }

        $createdAt = $company->getCreatedAt();
        $updatedAt = $company->getUpdatedAt();

        return new CompanyDetailView(
            $company->getId(),
            $company->getName(),
            $company->getShortName(),
            $this->buildIconUrl($company->getIcon()),
            $company->getShopIds(),
            $createdAt ? $createdAt->format('Y-m-d H:i:s') : null,
            $updatedAt ? $updatedAt->format('Y-m-d H:i:s') : null
        );
    }

    private function buildIconUrl(?string $fileName): ?string
    {
        if (null === $fileName || '' === $fileName) {
            return null;
        }

        return _MODULE_DIR_ . 'rj_multicarrier/var/icons/' . $fileName;
    }
}
