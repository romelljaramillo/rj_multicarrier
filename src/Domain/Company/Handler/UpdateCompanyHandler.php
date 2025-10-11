<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Company\Handler;

use Doctrine\ORM\EntityManagerInterface;
use Roanja\Module\RjMulticarrier\Domain\Company\Command\UpdateCompanyCommand;
use Roanja\Module\RjMulticarrier\Entity\Company;
use Roanja\Module\RjMulticarrier\Entity\CompanyShop;
use Roanja\Module\RjMulticarrier\Repository\CompanyRepository;
use RuntimeException;

final class UpdateCompanyHandler
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CompanyRepository $companyRepository
    ) {
    }

    public function handle(UpdateCompanyCommand $command): Company
    {
        $company = $this->companyRepository->find($command->getCompanyId());
        if (!$company instanceof Company) {
            throw new RuntimeException(sprintf('Company with id %d not found', $command->getCompanyId()));
        }

        $company->setName($command->getName())->setShortName($command->getShortName());

        // update icon if provided
        if (null !== $command->getIconFilename()) {
            $company->setIcon($command->getIconFilename());
        }

        // Sync shops
        $newIds = $command->getShopIds();
        $existingIds = $company->getShopIds();

        // remove
        foreach ($existingIds as $existingId) {
            if (!in_array($existingId, $newIds, true)) {
                foreach ($company->getShops() as $shopEntity) {
                    if ($shopEntity->getIdShop() === $existingId) {
                        $company->removeShop($shopEntity);
                    }
                }
            }
        }

        // add
        foreach ($newIds as $shopId) {
            if ($shopId > 0 && !in_array($shopId, $existingIds, true)) {
                $company->addShop(new CompanyShop($company, $shopId));
            }
        }

        try {
            $this->em->flush();
        } catch (\Throwable $e) {
            throw new RuntimeException('Unable to update company: ' . $e->getMessage());
        }

        return $company;
    }
}
