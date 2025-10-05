<?php
/**
 * Provides data and rendering helpers for the admin order view integration.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Service\Presenter;

use Roanja\Module\RjMulticarrier\Domain\Shipment\Handler\GetShipmentByOrderIdHandler;
use Roanja\Module\RjMulticarrier\Domain\Shipment\Query\GetShipmentByOrderId;
use Twig\Environment;

final class OrderViewPresenter
{
    public function __construct(
        private readonly Environment $twig,
        private readonly GetShipmentByOrderIdHandler $getShipmentByOrderIdHandler
    ) {
    }

    public function present(int $orderId): string
    {
        if (0 === $orderId) {
            return '';
        }

        $shipmentView = $this->getShipmentByOrderIdHandler->handle(new GetShipmentByOrderId($orderId));

    return $this->twig->render('@Modules/rj_multicarrier/views/templates/admin/order/panel.html.twig', [
            'orderId' => $orderId,
            'shipment' => $shipmentView,
        ]);
    }
}
