<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Carrier\Handler;

use Doctrine\ORM\EntityManagerInterface;
use Roanja\Module\RjMulticarrier\Domain\Carrier\Command\CreateCarrierCommand;
use Roanja\Module\RjMulticarrier\Entity\Carrier;
use Roanja\Module\RjMulticarrier\Entity\CarrierShop;
use RuntimeException;

final class CreateCarrierHandler
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function handle(CreateCarrierCommand $command): Carrier
    {
        $carrier = new Carrier($command->getName(), $command->getShortName());

        // set icon filename (controller already moved the file)
        $carrier->setIcon($command->getIconFilename());

        foreach ($command->getShopIds() as $shopId) {
            if ($shopId > 0) {
                $carrier->addShop(new CarrierShop($carrier, $shopId));
            }
        }

        try {
            $this->em->persist($carrier);
            $this->em->flush();
        } catch (\Throwable $e) {
            throw new RuntimeException('Unable to create carrier: ' . $e->getMessage());
        }

        return $carrier;
    }
}
