<?php
/**
 * Grid factory wrapper for shipments.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Grid\Shipment;

use PrestaShop\PrestaShop\Core\Grid\Data\Factory\GridDataFactoryInterface;
use PrestaShop\PrestaShop\Core\Grid\Filter\GridFilterFormFactoryInterface;
use PrestaShop\PrestaShop\Core\Grid\Grid;
use PrestaShop\PrestaShop\Core\Grid\GridFactory;
use PrestaShop\PrestaShop\Core\Hook\HookDispatcherInterface;

final class ShipmentGridFactory
{
    public function __construct(
        private readonly ShipmentGridDefinitionFactory $gridDefinitionFactory,
        private readonly GridDataFactoryInterface $gridDataFactory,
        private readonly GridFilterFormFactoryInterface $gridFilterFormFactory,
        private readonly HookDispatcherInterface $hookDispatcher
    ) {
    }

    public function getGrid(ShipmentFilters $filters): Grid
    {
        $factory = new GridFactory(
            $this->gridDefinitionFactory,
            $this->gridDataFactory,
            $this->gridFilterFormFactory,
            $this->hookDispatcher
        );

        return $factory->getGrid($filters);
    }
}
