<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Domain\Shipment\Handler;

use Roanja\Module\RjMulticarrier\Domain\Shipment\Command\BulkDeleteShipmentCommand;
use Roanja\Module\RjMulticarrier\Domain\Shipment\Exception\ShipmentException;
use Roanja\Module\RjMulticarrier\Repository\ShipmentRepository;

/**
 * Handler for bulk deleting shipments.
 */
final class BulkDeleteShipmentHandler
{
    private ShipmentRepository $shipmentRepository;

    public function __construct(ShipmentRepository $shipmentRepository)
    {
        $this->shipmentRepository = $shipmentRepository;
    }

    /**
     * @param BulkDeleteShipmentCommand $command
     *
     * @throws ShipmentException
     */
    public function handle(BulkDeleteShipmentCommand $command): void
    {
        $shipmentIds = $command->getShipmentIds();

        if (empty($shipmentIds)) {
            throw new ShipmentException('No shipment IDs provided for bulk delete');
        }

        $deletedCount = 0;
        $errors = [];

        foreach ($shipmentIds as $shipmentId) {
            try {
                $this->shipmentRepository->delete($shipmentId);
                $deletedCount++;
            } catch (\Exception $e) {
                $errors[] = sprintf('Failed to delete shipment %d: %s', $shipmentId, $e->getMessage());
            }
        }

        if (!empty($errors) && $deletedCount === 0) {
            throw new ShipmentException('Failed to delete any shipments: ' . implode('; ', $errors));
        }
    }
}