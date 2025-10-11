<?php

/**
 * Handles persistence of shipments triggered from legacy flows.
 */

declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Shipment\Handler;

use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use Roanja\Module\RjMulticarrier\Domain\Shipment\Command\CreateShipmentCommand;
use Roanja\Module\RjMulticarrier\Entity\Company;
use Roanja\Module\RjMulticarrier\Entity\InfoPackage;
use Roanja\Module\RjMulticarrier\Entity\Label;
use Roanja\Module\RjMulticarrier\Entity\Shipment;
use Roanja\Module\RjMulticarrier\Entity\ShipmentShop;
use Roanja\Module\RjMulticarrier\Entity\LabelShop;
use Roanja\Module\RjMulticarrier\Support\Common;
use Roanja\Module\RjMulticarrier\Repository\CompanyRepository;
use Roanja\Module\RjMulticarrier\Repository\InfoPackageRepository;
use Roanja\Module\RjMulticarrier\Repository\ShipmentRepository;
use RuntimeException;

final class CreateShipmentHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ShipmentRepository $shipmentRepository,
        private readonly InfoPackageRepository $infoPackageRepository,
        private readonly CompanyRepository $companyRepository
    ) {}

    public function handle(CreateShipmentCommand $command): Shipment
    {
        $infoPackage = $this->getInfoPackage($command->getInfoPackageId());
        $shipment = $this->shipmentRepository->findOneByOrderId($command->getOrderId());

        if (null === $shipment) {
            $shipment = new Shipment($command->getOrderId(), $infoPackage);
            $this->entityManager->persist($shipment);
        } else {
            $shipment->setInfoPackage($infoPackage);
        }

        $shipment
            ->setOrderReference($command->getOrderReference())
            ->setShipmentNumber($command->getShipmentNumber())
            ->setProduct($command->getProduct())
            ->setRequestPayload($this->encodePayload($command->getRequestPayload()))
            ->setResponsePayload($this->encodePayload($command->getResponsePayload()));

        $companyId = $command->getCompanyId();
        $company = null === $companyId ? null : $this->companyRepository->find($companyId);
        $shipment->setCompany($company instanceof Company ? $company : null);

        $this->entityManager->flush();

        // Persist shop mapping for shipment
        $shopId = $command->getShopId();
        if ($shopId > 0) {
            $exists = false;
            foreach ($shipment->getShops() as $s) {
                if ($s->getShopId() === $shopId) {
                    $exists = true;
                    break;
                }
            }

            if (!$exists) {
                $mapping = new ShipmentShop($shipment, $shopId);
                $this->entityManager->persist($mapping);
                $this->entityManager->flush();
            }
        }

        $this->createLabels($shipment, $command->getLabels(), $shopId);

        return $shipment;
    }

    private function createLabels(Shipment $shipment, array $labels, int $shopId = 0): void
    {
        foreach ($labels as $labelData) {
            if (!isset($labelData['storage_key'], $labelData['package_id'])) {
                continue;
            }

            $label = new Label($shipment);
            $label->setPackageId($labelData['package_id'] ?? null);
            $label->setTrackerCode($labelData['tracker_code'] ?? null);
            $label->setLabelType($labelData['label_type'] ?? null);
            $label->setPdf($labelData['storage_key']);
            $label->setPrinted(false);

            $this->entityManager->persist($label);

            if ($shopId > 0) {
                $exists = false;
                foreach ($label->getShops() as $s) {
                    if ($s->getShopId() === $shopId) {
                        $exists = true;
                        break;
                    }
                }

                if (!$exists) {
                    $labelMapping = new LabelShop($label, $shopId);
                    $this->entityManager->persist($labelMapping);
                }
            }

            if (!empty($labelData['pdf_content']) && is_string($labelData['pdf_content'])) {
                Common::createFileLabel($labelData['pdf_content'], $labelData['storage_key']);
            }
        }

        $this->entityManager->flush();
    }

    private function getInfoPackage(int $infoPackageId): InfoPackage
    {
        $infoPackage = $this->infoPackageRepository->find($infoPackageId);

        if (!$infoPackage instanceof InfoPackage) {
            throw new RuntimeException(sprintf('InfoPackage with id %d not found', $infoPackageId));
        }

        return $infoPackage;
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    private function encodePayload(?array $payload): ?string
    {
        if (null === $payload) {
            return null;
        }

        try {
            return json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Unable to encode shipment payload to JSON', 0, $exception);
        }
    }
}
