<?php
/**
 * Handler to toggle the carrier availability directly from the grid.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Carrier\Handler;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Roanja\Module\RjMulticarrier\Domain\Carrier\Command\ToggleCarrierStatusCommand;
use Roanja\Module\RjMulticarrier\Entity\Carrier;
use Roanja\Module\RjMulticarrier\Repository\CarrierRepository;
use RuntimeException;

final class ToggleCarrierStatusHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CarrierRepository $carrierRepository
    ) {
    }

    public function handle(ToggleCarrierStatusCommand $command): Carrier
    {
        $carrier = $this->carrierRepository->find($command->getCarrierId());

        if (!$carrier instanceof Carrier) {
            throw new RuntimeException(sprintf('Carrier with id %d not found.', $command->getCarrierId()));
        }

        $carrier->setIsActive(!$carrier->isActive());
        $carrier->setUpdatedAt(new DateTimeImmutable());

        $this->entityManager->flush();

        return $carrier;
    }
}
