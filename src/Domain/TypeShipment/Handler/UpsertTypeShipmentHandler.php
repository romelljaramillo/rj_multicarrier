<?php
/**
 * Handler responsible for creating or updating type shipments.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\TypeShipment\Handler;

use Db;
use Doctrine\ORM\EntityManagerInterface;
use Roanja\Module\RjMulticarrier\Domain\TypeShipment\Command\UpsertTypeShipmentCommand;
use Roanja\Module\RjMulticarrier\Domain\TypeShipment\Exception\TypeShipmentCarrierConflictException;
use Roanja\Module\RjMulticarrier\Domain\TypeShipment\Exception\TypeShipmentException;
use Roanja\Module\RjMulticarrier\Domain\TypeShipment\Exception\TypeShipmentNotFoundException;
use Roanja\Module\RjMulticarrier\Entity\Carrier;
use Roanja\Module\RjMulticarrier\Entity\TypeShipment;
use Roanja\Module\RjMulticarrier\Repository\CarrierConfigurationRepository;
use Roanja\Module\RjMulticarrier\Repository\CarrierRepository;
use Roanja\Module\RjMulticarrier\Repository\TypeShipmentRepository;
use Roanja\Module\RjMulticarrier\Service\Configuration\DefaultCarrierConfigurationSeeder;

final class UpsertTypeShipmentHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CarrierRepository $carrierRepository,
        private readonly TypeShipmentRepository $typeShipmentRepository,
        private readonly CarrierConfigurationRepository $carrierConfigurationRepository,
        private readonly DefaultCarrierConfigurationSeeder $configurationSeeder
    ) {
    }

    public function handle(UpsertTypeShipmentCommand $command): TypeShipment
    {
        $carrier = $this->resolveCarrier($command->getCarrierId());
        $typeShipment = $this->resolveTypeShipment($command, $carrier);

        $referenceCarrierId = $command->getReferenceCarrierId();
        if (null !== $referenceCarrierId) {
            $this->assertCarrierIsAvailable($referenceCarrierId, $typeShipment->getId());
        }

        $isNew = null === $command->getTypeShipmentId();
        $currentReferenceCarrierId = $typeShipment->getReferenceCarrierId();
        if (null !== $referenceCarrierId && ($isNew || $referenceCarrierId !== $currentReferenceCarrierId)) {
            $this->assertCarrierIsConfiguredForReference($carrier);
        }

        $typeShipment
            ->setCarrier($carrier)
            ->setName($command->getName())
            ->setBusinessCode($command->getBusinessCode())
            ->setReferenceCarrierId($referenceCarrierId)
            ->setActive($command->isActive())
            ->touch();

        $this->entityManager->persist($typeShipment);
        $this->entityManager->flush();

        if (null !== $referenceCarrierId) {
            $this->syncTrackingUrl($carrier, $referenceCarrierId);
        }

        return $typeShipment;
    }

    private function resolveCarrier(int $carrierId): Carrier
    {
        $carrier = $this->carrierRepository->find($carrierId);

        if (!$carrier instanceof Carrier) {
            throw new TypeShipmentException(sprintf('Carrier with id %d was not found.', $carrierId));
        }

        return $carrier;
    }

    private function resolveTypeShipment(UpsertTypeShipmentCommand $command, Carrier $carrier): TypeShipment
    {
        $typeShipmentId = $command->getTypeShipmentId();

        if (null === $typeShipmentId) {
            $typeShipment = new TypeShipment($carrier, $command->getName(), $command->getBusinessCode());
            $typeShipment->setReferenceCarrierId($command->getReferenceCarrierId());
            $typeShipment->setActive($command->isActive());

            $this->entityManager->persist($typeShipment);

            return $typeShipment;
        }

        $typeShipment = $this->typeShipmentRepository->find($typeShipmentId);

        if (!$typeShipment instanceof TypeShipment) {
            throw TypeShipmentNotFoundException::fromId($typeShipmentId);
        }

        return $typeShipment;
    }

    private function assertCarrierIsAvailable(int $referenceCarrierId, ?int $currentTypeShipmentId): void
    {
        $existing = $this->typeShipmentRepository->findOneByReferenceCarrierId($referenceCarrierId);

        if (null === $existing) {
            return;
        }

        if (null !== $currentTypeShipmentId && $existing->getId() === $currentTypeShipmentId) {
            return;
        }

        throw TypeShipmentCarrierConflictException::fromReference($referenceCarrierId);
    }

    private function syncTrackingUrl(Carrier $carrier, int $referenceCarrierId): void
    {
        $trackingUrlKey = 'TRACKING_URL';
        $trackingConfig = $this->carrierConfigurationRepository->findOneForCarrierByName($carrier, $trackingUrlKey);
        $trackingUrl = null === $trackingConfig ? '' : trim((string) $trackingConfig->getValue());

        if ('' === $trackingUrl) {
            return;
        }

        Db::getInstance()->update(
            'carrier',
            ['url' => pSQL($trackingUrl)],
            'id_reference = ' . (int) $referenceCarrierId
        );
    }

    private function assertCarrierIsConfiguredForReference(Carrier $carrier): void
    {
        $definitions = $this->configurationSeeder->getDefaultDefinitionsForCarrier($carrier);
        $requiredNames = [];
        $labels = [];

        foreach ($definitions as $definition) {
            if (!is_array($definition)) {
                continue;
            }

            $name = isset($definition['name']) ? trim((string) $definition['name']) : '';
            if ('' === $name) {
                continue;
            }

            $label = isset($definition['label']) ? trim((string) $definition['label']) : '';
            $labels[$name] = '' !== $label ? $label : $name;

            $isRequired = isset($definition['required']) ? (bool) $definition['required'] : true;
            if ($isRequired) {
                $requiredNames[$name] = true;
            }
        }

        if (!isset($requiredNames['TRACKING_URL'])) {
            $requiredNames['TRACKING_URL'] = true;
        }

        if (!isset($labels['TRACKING_URL'])) {
            $labels['TRACKING_URL'] = 'URL seguimiento';
        }

        $values = $this->carrierConfigurationRepository->getKeyValueForCarrier($carrier);
        $missing = [];

        foreach (array_keys($requiredNames) as $name) {
            $value = $values[$name] ?? null;
            if (null === $value || '' === trim((string) $value)) {
                $missing[] = $labels[$name] ?? $name;
            }
        }

        if ([] !== $missing) {
            $missing = array_values(array_unique($missing));
            throw new TypeShipmentException(
                sprintf("Faltan configuraciones obligatorias:<br>%s", implode('<br>', $missing))
            );
        }
    }
}
