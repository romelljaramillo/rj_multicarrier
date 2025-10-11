<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Company\Handler;

use Doctrine\ORM\EntityManagerInterface;
use Roanja\Module\RjMulticarrier\Domain\Company\Command\CreateCompanyCommand;
use Roanja\Module\RjMulticarrier\Entity\Company;
use Roanja\Module\RjMulticarrier\Entity\CompanyShop;
use RuntimeException;

final class CreateCompanyHandler
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function handle(CreateCompanyCommand $command): Company
    {
        $company = new Company($command->getName(), $command->getShortName());

        // set icon filename (controller already moved the file)
        $company->setIcon($command->getIconFilename());

        foreach ($command->getShopIds() as $shopId) {
            if ($shopId > 0) {
                $company->addShop(new CompanyShop($company, $shopId));
            }
        }

        try {
            $this->em->persist($company);
            $this->em->flush();
        } catch (\Throwable $e) {
            throw new RuntimeException('Unable to create company: ' . $e->getMessage());
        }

        return $company;
    }
}
