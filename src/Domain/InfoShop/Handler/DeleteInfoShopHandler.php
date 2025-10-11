<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\InfoShop\Handler;

use Doctrine\ORM\EntityManagerInterface;
use Roanja\Module\RjMulticarrier\Domain\InfoShop\Command\DeleteInfoShopCommand;
use Roanja\Module\RjMulticarrier\Domain\InfoShop\Exception\InfoShopNotFoundException;
use Roanja\Module\RjMulticarrier\Repository\InfoShopRepository;

final class DeleteInfoShopHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly InfoShopRepository $infoShopRepository
    ) {
    }

    public function handle(DeleteInfoShopCommand $command): void
    {
        $infoShop = $this->infoShopRepository->find($command->getInfoShopId());

        if (null === $infoShop) {
            throw InfoShopNotFoundException::forId($command->getInfoShopId());
        }

        foreach ($infoShop->getShops() as $mapping) {
            $this->entityManager->remove($mapping);
        }

        $this->entityManager->remove($infoShop);
        $this->entityManager->flush();
    }
}
