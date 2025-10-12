<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Carrier\Handler;

use Doctrine\ORM\EntityManagerInterface;
use Roanja\Module\RjMulticarrier\Domain\Carrier\Command\UpdateCarrierCommand;
use Roanja\Module\RjMulticarrier\Entity\Carrier;
use Roanja\Module\RjMulticarrier\Entity\CarrierShop;
use Roanja\Module\RjMulticarrier\Repository\CarrierRepository;
use RuntimeException;

final class UpdateCarrierHandler
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CarrierRepository $carrierRepository
    ) {
    }

    public function handle(UpdateCarrierCommand $command): Carrier
    {
        $carrier = $this->carrierRepository->find($command->getCarrierId());
        if (!$carrier instanceof Carrier) {
            throw new RuntimeException(sprintf('Carrier with id %d not found', $command->getCarrierId()));
        }

        $carrier->setName($command->getName())->setShortName($command->getShortName());

        // update icon if provided
        if (null !== $command->getIconFilename()) {
            $carrier->setIcon($command->getIconFilename());
        }

        // Sync shops
        $newIds = $command->getShopIds();
        $existingIds = $carrier->getShopIds();

        // remove
        foreach ($existingIds as $existingId) {
            if (!in_array($existingId, $newIds, true)) {
                foreach ($carrier->getShops() as $shopEntity) {
                    if ($shopEntity->getIdShop() === $existingId) {
                        $carrier->removeShop($shopEntity);
                    }
                }
            }
        }

        // add
        foreach ($newIds as $shopId) {
            if ($shopId > 0 && !in_array($shopId, $existingIds, true)) {
                $carrier->addShop(new CarrierShop($carrier, $shopId));
            }
        }

        try {
            $this->em->flush();
        } catch (\Throwable $e) {
            throw new RuntimeException('Unable to update carrier: ' . $e->getMessage());
        }

        return $carrier;
    }
}
