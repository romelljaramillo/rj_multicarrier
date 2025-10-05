<?php
/**
 * Handles the deletion of shipments, cleaning associated labels and files.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Shipment\Handler;

use Doctrine\ORM\EntityManagerInterface;
use Roanja\Module\RjMulticarrier\Domain\Shipment\Command\DeleteShipmentCommand;
use Roanja\Module\RjMulticarrier\Domain\Shipment\Exception\ShipmentNotFoundException;
use Roanja\Module\RjMulticarrier\Entity\Label;
use Roanja\Module\RjMulticarrier\Entity\Shipment;
use Roanja\Module\RjMulticarrier\Repository\LabelRepository;
use Roanja\Module\RjMulticarrier\Repository\ShipmentRepository;
use Roanja\Module\RjMulticarrier\Support\Common;

final class DeleteShipmentHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ShipmentRepository $shipmentRepository,
        private readonly LabelRepository $labelRepository
    ) {
    }

    public function handle(DeleteShipmentCommand $command): void
    {
        $shipment = $this->shipmentRepository->find($command->getShipmentId());

        if (!$shipment instanceof Shipment || $shipment->isDeleted()) {
            throw ShipmentNotFoundException::withId($command->getShipmentId());
        }

        $labels = $this->labelRepository->findBy(['shipment' => $shipment]);
        $this->removeLabelFiles($labels);

        foreach ($labels as $label) {
            $this->entityManager->remove($label);
        }

        $shipment->markDeleted();
        $this->entityManager->persist($shipment);
        $this->entityManager->flush();
    }

    /**
     * @param Label[] $labels
     */
    private function removeLabelFiles(array $labels): void
    {
        foreach ($labels as $label) {
            if (!$label instanceof Label) {
                continue;
            }

            $candidates = array_filter([
                $label->getPdf(),
                $label->getPackageId(),
            ], static fn (?string $value): bool => null !== $value && '' !== $value);

            foreach ($candidates as $candidate) {
                $path = Common::getFileLabel($candidate);
                if (is_file($path)) {
                    @unlink($path);
                }
            }
        }
    }
}
