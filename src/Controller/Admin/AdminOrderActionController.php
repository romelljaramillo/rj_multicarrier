<?php

declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Controller\Admin;

use Roanja\Module\RjMulticarrier\Domain\InfoShipment\Command\UpsertInfoShipmentCommand;
use Roanja\Module\RjMulticarrier\Domain\Shipment\Command\DeleteShipmentCommand;
use Roanja\Module\RjMulticarrier\Repository\InfoShipmentRepository;
use Roanja\Module\RjMulticarrier\Repository\ShipmentRepository;
use Roanja\Module\RjMulticarrier\Domain\TypeShipment\Query\GetTypeShipmentForView;
use Roanja\Module\RjMulticarrier\Service\Shipment\ShipmentGenerationService;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class AdminOrderActionController extends FrameworkBundleAdminController
{
    private const TRANSLATION_DOMAIN = 'Modules.RjMulticarrier.Admin';

    public function upsertPackageAction(Request $request, int $orderId): RedirectResponse
    {
        /** @var \Context $context */
        $context = \Context::getContext();
        $shopId = (int) ($context->shop->id ?? 0);

        $typeShipmentId = (int) $request->request->get('id_type_shipment', 0);

        if ($typeShipmentId <= 0) {
            $this->addFlash('error', $this->translate('Please select a shipment type.'));
            return $this->redirect($this->getRedirectUrl($request));
        }

        $typeShipmentView = $this->getQueryBus()->handle(new GetTypeShipmentForView($typeShipmentId));

        if (null === $typeShipmentView) {
            $this->addFlash('error', $this->translate('The selected shipment type does not exist.'));
            return $this->redirect($this->getRedirectUrl($request));
        }

        $typeShipmentArr = is_object($typeShipmentView) && method_exists($typeShipmentView, 'toArray')
            ? $typeShipmentView->toArray()
            : (array) $typeShipmentView;

        $referenceCarrierId = (int) ($typeShipmentArr['referenceCarrierId'] ?? 0);
        if ($referenceCarrierId <= 0) {
            $referenceCarrierId = (int) $request->request->get('id_reference_carrier', 0);
        }

        if ($referenceCarrierId <= 0) {
            $this->addFlash('error', $this->translate('The selected shipment type is not linked to a carrier reference.'));
            return $this->redirect($this->getRedirectUrl($request));
        }

        $infoPackageIdRaw = $request->request->get('id_info_shipment');
        $infoPackageId = ($infoPackageIdRaw !== null && '' !== trim((string) $infoPackageIdRaw)) ? (int) $infoPackageIdRaw : null;

        $quantity = max(1, (int) $request->request->get('rj_quantity', 1));
        $weight = (float) $request->request->get('rj_weight', 0);
        $length = $this->toNullableFloat($request->request->get('rj_length'));
        $width = $this->toNullableFloat($request->request->get('rj_width'));
        $height = $this->toNullableFloat($request->request->get('rj_height'));
        $cashOnDelivery = $this->normalizeStringValue($request->request->get('rj_cash_ondelivery'));
        $message = $this->normalizeStringValue($request->request->get('rj_message'));
        $hourFrom = $this->normalizeTimeValue($request->request->get('rj_hour_from'));
        $hourUntil = $this->normalizeTimeValue($request->request->get('rj_hour_until'));
        $retornoRaw = $request->request->get('rj_retorno');
        $retorno = ($retornoRaw === null || $retornoRaw === '') ? null : (int) $retornoRaw;
        $rcs = (bool) $request->request->get('rj_rcs');
        $vsec = $this->normalizeStringValue($request->request->get('rj_vsec'));
        $dorig = $this->normalizeStringValue($request->request->get('rj_dorig'));

        try {
            $this->getCommandBus()->handle(new UpsertInfoShipmentCommand(
                $infoPackageId,
                $orderId,
                $referenceCarrierId,
                $typeShipmentId,
                $quantity,
                $weight,
                $length,
                $width,
                $height,
                $cashOnDelivery,
                $message,
                $hourFrom,
                $hourUntil,
                $retorno,
                $rcs,
                $vsec,
                $dorig,
                $shopId
            ));

            // After upsert, if carrier changed remove previous shipment
            /** @var InfoShipmentRepository $infoShipmentRepository */
            $infoShipmentRepository = $this->get(InfoShipmentRepository::class);
            $package = $infoShipmentRepository->getPackageByOrder($orderId, $shopId);

            if ($package) {
                $newCarrierId = (int) ($typeShipmentArr['carrierId'] ?? ($typeShipmentArr['referenceCarrierId'] ?? 0));
                /** @var ShipmentRepository $shipmentRepository */
                $shipmentRepository = $this->get(ShipmentRepository::class);
                $currentShipment = $shipmentRepository->findOneByOrderId($orderId);

                if ($currentShipment && $currentShipment->getCarrier() && $currentShipment->getCarrier()->getId() !== $newCarrierId) {
                    try {
                        $this->getCommandBus()->handle(new DeleteShipmentCommand((int) $currentShipment->getId()));
                        $this->addFlash('info', $this->translate('Existing shipment removed because the carrier changed.'));
                    } catch (\Throwable $e) {
                        $this->addFlash('error', $this->translate('Unable to remove the previous shipment: %message%', ['%message%' => $e->getMessage()]));
                    }
                }
            }

            $this->addFlash('success', $this->translate('Package information saved.'));
        } catch (\Throwable $e) {
            $this->addFlash('error', $this->translate('Unable to save package information: %message%', ['%message%' => $e->getMessage()]));
        }

        return $this->redirect($this->getRedirectUrl($request));
    }

    public function generateShipmentAction(Request $request, int $orderId, int $infoPackageId): RedirectResponse
    {
        try {
            /** @var ShipmentGenerationService $generationService */
            $generationService = $this->get(ShipmentGenerationService::class);
            $generationService->generateForInfoShipment($infoPackageId);
            $this->addFlash('success', $this->translate('Shipment generated successfully.'));
        } catch (\Throwable $e) {
            $this->addFlash('error', $e->getMessage());
        }

        return $this->redirect($this->getRedirectUrl($request));
    }

    public function deleteShipmentAction(Request $request, int $orderId, int $shipmentId): RedirectResponse
    {
        // No CSRF token validation to match other native controllers' patterns
        try {
            $this->getCommandBus()->handle(new DeleteShipmentCommand($shipmentId));
            $this->addFlash('success', $this->translate('Shipment deleted successfully.'));
        } catch (\Throwable $e) {
            $this->addFlash('error', $this->translate('Unable to delete shipment: %message%', ['%message%' => $e->getMessage()]));
        }

        return $this->redirect($this->getRedirectUrl($request));
    }

    private function getRedirectUrl(Request $request): string
    {
        $referer = $request->headers->get('referer');
        if ($referer) {
            return $referer;
        }

        // fallback to module configuration or orders list
        try {
            return $this->generateUrl('admin_orders_index');
        } catch (\Throwable $e) {
            return '/';
        }
    }

    private function toNullableFloat($value): ?float
    {
        if ($value === null || $value === '' || $value === false) {
            return null;
        }

        return (float) $value;
    }

    private function normalizeStringValue($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return '' === $trimmed ? null : $trimmed;
    }

    private function normalizeTimeValue($value): ?string
    {
        if ($value === null || '' === trim((string) $value)) {
            return null;
        }

        $time = trim((string) $value);

        if (preg_match('/^\d{2}:\d{2}$/', $time)) {
            $time .= ':00';
        }

        if (!preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
            return null;
        }

        return $time;
    }

    private function translate(string $message, array $parameters = []): string
    {
        return $this->trans($message, self::TRANSLATION_DOMAIN, $parameters);
    }
}
