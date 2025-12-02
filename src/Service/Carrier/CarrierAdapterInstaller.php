<?php
/**
 * Installs carrier entities preconfigured from adapter descriptors.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Service\Carrier;

use InvalidArgumentException;
use PrestaShop\PrestaShop\Core\CommandBus\CommandBusInterface;
use Roanja\Module\RjMulticarrier\Carrier\Adapter\CarrierAdapterInterface;
use Roanja\Module\RjMulticarrier\Carrier\Adapter\CarrierRegistry;
use Roanja\Module\RjMulticarrier\Domain\Carrier\Command\CreateCarrierCommand;
use Roanja\Module\RjMulticarrier\Entity\Carrier;
use Roanja\Module\RjMulticarrier\Repository\CarrierRepository;
use Roanja\Module\RjMulticarrier\Service\Carrier\Exception\CarrierAlreadyInstalledException;
use Roanja\Module\RjMulticarrier\Service\Configuration\DefaultCarrierConfigurationSeeder;
use Roanja\Module\RjMulticarrier\Service\Carrier\AdapterTypeShipmentSeeder;
use RuntimeException;

final class CarrierAdapterInstaller
{
    public function __construct(
        private readonly CarrierRegistry $carrierRegistry,
        private readonly CarrierRepository $carrierRepository,
        private readonly CommandBusInterface $commandBus,
        private readonly DefaultCarrierConfigurationSeeder $configurationSeeder,
        private readonly AdapterTypeShipmentSeeder $typeShipmentSeeder
    ) {
    }

    /**
     * @param int[] $shopIds
     */
    public function install(string $adapterCode, array $shopIds): Carrier
    {
        $normalizedCode = strtoupper(trim($adapterCode));

        if ('' === $normalizedCode) {
            throw new InvalidArgumentException('Adapter code cannot be empty.');
        }

        $existing = $this->carrierRepository->findOneByShortName($normalizedCode);
        if ($existing instanceof Carrier) {
            throw new CarrierAlreadyInstalledException($existing);
        }

        $adapter = $this->carrierRegistry->get($normalizedCode);
        $descriptor = $adapter->getDescriptor();

        $command = new CreateCarrierCommand(
            $descriptor->getDisplayName(),
            $normalizedCode,
            null,
            $shopIds,
            true,
            $descriptor->supportsApi(),
            $this->resolveAdapterServiceId($adapter),
            $descriptor->getConfigurationSchema(),
            $descriptor->getDefaultPayload()
        );

        $result = $this->commandBus->handle($command);

        if (!$result instanceof Carrier) {
            throw new RuntimeException(sprintf('Adapter %s installation did not return a carrier entity.', $normalizedCode));
        }

        $this->configurationSeeder->seedForCarrier($result);
        $this->typeShipmentSeeder->seedForAdapter($result, $normalizedCode);

        return $result;
    }

    private function resolveAdapterServiceId(CarrierAdapterInterface $adapter): string
    {
        return $adapter::class;
    }
}
