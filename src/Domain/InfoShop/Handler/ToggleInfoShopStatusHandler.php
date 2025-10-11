<?php
/**
 * Handler responsible for toggling InfoShop active status.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\InfoShop\Handler;

use Doctrine\ORM\EntityManagerInterface;
use Roanja\Module\RjMulticarrier\Domain\InfoShop\Command\ToggleInfoShopStatusCommand;
use Roanja\Module\RjMulticarrier\Domain\InfoShop\Exception\InfoShopNotFoundException;
use Roanja\Module\RjMulticarrier\Entity\InfoShop;
use Roanja\Module\RjMulticarrier\Repository\InfoShopRepository;

final class ToggleInfoShopStatusHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly InfoShopRepository $infoShopRepository
    ) {
    }

    public function handle(ToggleInfoShopStatusCommand $command): InfoShop
    {
        $infoShop = $this->infoShopRepository->find($command->getInfoShopId());

        if (!$infoShop instanceof InfoShop) {
            throw InfoShopNotFoundException::forId($command->getInfoShopId());
        }

        $infoShop->setActive(!$infoShop->isActive());
        $this->entityManager->flush();

        return $infoShop;
    }
}
