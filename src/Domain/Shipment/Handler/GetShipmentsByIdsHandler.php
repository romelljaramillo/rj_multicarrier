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

use Roanja\Module\RjMulticarrier\Domain\Shipment\Query\GetShipmentsByIds;
use Roanja\Module\RjMulticarrier\Domain\Shipment\View\ShipmentView;

/**
 * Handler for getting shipments by their IDs.
 */
final class GetShipmentsByIdsHandler
{
    private \Roanja\Module\RjMulticarrier\Repository\ShipmentRepository $shipmentRepository;
    private \Roanja\Module\RjMulticarrier\Repository\LabelRepository $labelRepository;

    public function __construct(
        \Roanja\Module\RjMulticarrier\Repository\ShipmentRepository $shipmentRepository,
        \Roanja\Module\RjMulticarrier\Repository\LabelRepository $labelRepository
    ) {
        $this->shipmentRepository = $shipmentRepository;
        $this->labelRepository = $labelRepository;
    }

    /**
     * @param GetShipmentsByIds $query
     *
     * @return ShipmentView[]
     */
    public function handle(GetShipmentsByIds $query): array
    {
        $shipmentIds = $query->getShipmentIds();
        if (empty($shipmentIds)) {
            return [];
        }

        $views = [];
        foreach ($shipmentIds as $shipmentId) {
            $shipment = $this->shipmentRepository->find($shipmentId);
            if (!$shipment || $shipment->isDeleted()) {
                continue;
            }

            $package = $this->buildPackageView($shipment->getInfoShipment());
            $labels = $this->labelRepository->findBy(['shipment' => $shipment]);

            $metadata = [
                'account' => $shipment->getAccount(),
                'product' => $shipment->getProduct(),
                'requestPayload' => $shipment->getRequestPayload(),
                'responsePayload' => $shipment->getResponsePayload(),
                'deleted' => $shipment->isDeleted(),
            ];

            $views[] = ShipmentView::fromEntities($shipment, $labels, $package, $metadata);
        }

        return $views;
    }

    private function buildPackageView($infoPackage): array
    {
        if (!$infoPackage) {
            return [];
        }

        return [
            'id' => $infoPackage->getId(),
            'weight' => $infoPackage->getWeight(),
            'width' => $infoPackage->getWidth(),
            'height' => $infoPackage->getHeight(),
            'length' => $infoPackage->getLength(),
        ];
    }
}
