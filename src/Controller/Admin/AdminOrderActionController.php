<?php

declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Controller\Admin;

use Roanja\Module\RjMulticarrier\Domain\InfoShipment\Command\UpsertInfoShipmentCommand;
use Roanja\Module\RjMulticarrier\Domain\Shipment\Command\DeleteShipmentCommand;
use Roanja\Module\RjMulticarrier\Entity\InfoShipment;
use Roanja\Module\RjMulticarrier\Repository\InfoShipmentRepository;
use Roanja\Module\RjMulticarrier\Repository\ShipmentRepository;
use Roanja\Module\RjMulticarrier\Domain\TypeShipment\Query\GetTypeShipmentForView;
use Roanja\Module\RjMulticarrier\Service\Shipment\ShipmentGenerationService;
use \Employee;
use \Validate;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class AdminOrderActionController extends FrameworkBundleAdminController
{
    private const TRANSLATION_DOMAIN = 'Modules.RjMulticarrier.Admin';

    public function __construct(private readonly ShipmentGenerationService $shipmentGenerationService) {}

    public function upsertPackageAction(Request $request, int $orderId): Response
    {
        $isAjax = $request->isXmlHttpRequest();
        $messages = $this->initMessageStack();
        $responsePayload = [
            'packageId' => null,
            'shipmentId' => null,
            'labelsUrl' => null,
        ];
        $statusCode = Response::HTTP_OK;
        $success = false;

        /** @var \Context $context */
        $context = \Context::getContext();
        $shopId = (int) ($context->shop->id ?? 0);

        $typeShipmentId = (int) $request->request->get('id_type_shipment', 0);

        if ($typeShipmentId <= 0) {
            $this->pushMessage($messages, 'error', $this->translate('Please select a shipment type.'), !$isAjax);

            return $this->finalizeUpsertResponse($request, $isAjax, $messages, $responsePayload, false, Response::HTTP_BAD_REQUEST);
        }

        $typeShipmentView = $this->getQueryBus()->handle(new GetTypeShipmentForView($typeShipmentId));

        if (null === $typeShipmentView) {
            $this->pushMessage($messages, 'error', $this->translate('The selected shipment type does not exist.'), !$isAjax);

            return $this->finalizeUpsertResponse($request, $isAjax, $messages, $responsePayload, false, Response::HTTP_NOT_FOUND);
        }

        $typeShipmentArr = is_object($typeShipmentView) && method_exists($typeShipmentView, 'toArray')
            ? $typeShipmentView->toArray()
            : (array) $typeShipmentView;

        $referenceCarrierId = (int) ($typeShipmentArr['referenceCarrierId'] ?? 0);
        if ($referenceCarrierId <= 0) {
            $referenceCarrierId = (int) $request->request->get('id_reference_carrier', 0);
        }

        if ($referenceCarrierId <= 0) {
            $this->pushMessage($messages, 'error', $this->translate('The selected shipment type is not linked to a carrier reference.'), !$isAjax);

            return $this->finalizeUpsertResponse($request, $isAjax, $messages, $responsePayload, false, Response::HTTP_BAD_REQUEST);
        }

        $infoPackageIdRaw = $request->request->get('id_info_shipment');
        $infoPackageId = ($infoPackageIdRaw !== null && '' !== trim((string) $infoPackageIdRaw)) ? (int) $infoPackageIdRaw : null;

        $shouldGenerateShipment = null !== $request->request->get('generate_shipment');

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
        $employeeId = $this->normalizeEmployeeId($request->request->get('rj_employee_id'));
        if (null === $employeeId) {
            [$employeeId] = $this->resolveCurrentEmployee();
        }

        $loggedEmployeeId = $this->normalizeEmployeeId($request->request->get('rj_logged_employee_id'));
        if (null === $loggedEmployeeId) {
            [$loggedEmployeeId] = $this->resolveCurrentEmployee();
        }

        /** @var ShipmentRepository $shipmentRepository */
        $shipmentRepository = $this->get(ShipmentRepository::class);
        $previousShipment = $shipmentRepository->findOneByOrderId($orderId);

        try {
            /** @var InfoShipment|null $infoShipment */
            $infoShipment = $this->getCommandBus()->handle(new UpsertInfoShipmentCommand(
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
                $employeeId,
                $loggedEmployeeId,
                $shopId
            ));

            $infoPackageIdPersisted = $infoShipment instanceof InfoShipment ? (int) $infoShipment->getId() : null;

            // After upsert, if carrier changed remove previous shipment
            /** @var InfoShipmentRepository $infoShipmentRepository */
            $infoShipmentRepository = $this->get(InfoShipmentRepository::class);
            $package = $infoShipmentRepository->getPackageByOrder($orderId, $shopId);

            if ($package && null === $infoPackageIdPersisted) {
                $infoPackageIdPersisted = (int) ($package['id_info_shipment'] ?? 0);
            }

            if ($infoPackageIdPersisted > 0) {
                $responsePayload['packageId'] = $infoPackageIdPersisted;
            }

            if ($package) {
                $newCarrierId = (int) ($typeShipmentArr['carrierId'] ?? ($typeShipmentArr['referenceCarrierId'] ?? 0));
                $currentShipment = $shipmentRepository->findOneByOrderId($orderId);

                if ($currentShipment && $currentShipment->getCarrier() && $currentShipment->getCarrier()->getId() !== $newCarrierId) {
                    try {
                        $this->getCommandBus()->handle(new DeleteShipmentCommand((int) $currentShipment->getId()));
                        $this->pushMessage($messages, 'info', $this->translate('Existing shipment removed because the carrier changed.'), !$isAjax);
                    } catch (\Throwable $e) {
                        $this->pushMessage($messages, 'error', $this->translate('Unable to remove the previous shipment: %message%', ['%message%' => $e->getMessage()]), !$isAjax);
                    }
                }
            }

            if ($shouldGenerateShipment) {
                if ($infoPackageIdPersisted > 0) {
                    try {
                        $packageRow = $infoShipmentRepository->getPackageByOrder($orderId, $shopId);
                        $reloadedPackageId = (int) ($packageRow['id_info_shipment'] ?? 0);
                        if ($reloadedPackageId > 0) {
                            $infoPackageIdPersisted = $reloadedPackageId;
                            $responsePayload['packageId'] = $infoPackageIdPersisted;
                        }

                        $shipmentEntity = $this->shipmentGenerationService->generateForInfoShipment($infoPackageIdPersisted);
                        $shipmentId = (int) ($shipmentEntity->getId() ?? 0);
                        if ($shipmentId > 0) {
                            $responsePayload['shipmentId'] = $shipmentId;
                            $responsePayload['labelsUrl'] = $this->generateUrl('admin_rj_multicarrier_shipments_print', ['id' => $shipmentId]);
                        }

                        $this->pushMessage($messages, 'success', $this->translate('Package information saved and shipment generated successfully.'), !$isAjax);
                        $success = true;
                    } catch (\Throwable $e) {
                        $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR;
                        $this->cleanupFailedShipment($orderId, $infoPackageIdPersisted, $shipmentRepository, $previousShipment);
                        $this->pushMessage($messages, 'error', $this->translate('Package information saved but shipment generation failed: %message%', ['%message%' => $e->getMessage()]), !$isAjax);
                    }
                } else {
                    $statusCode = Response::HTTP_BAD_REQUEST;
                    $this->pushMessage($messages, 'warning', $this->translate('Package information saved but shipment generation could not be started because the package identifier is missing.'), !$isAjax);
                }
            } else {
                $this->pushMessage($messages, 'success', $this->translate('Package information saved.'), !$isAjax);
                $success = true;
            }
        } catch (\Throwable $e) {
            $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR;
            $this->pushMessage($messages, 'error', $this->translate('Unable to save package information: %message%', ['%message%' => $e->getMessage()]), !$isAjax);
        }

        $finalSuccess = $success && empty($messages['error']);

        return $this->finalizeUpsertResponse($request, $isAjax, $messages, $responsePayload, $finalSuccess, $statusCode);
    }

    public function generateShipmentAction(Request $request, int $orderId, int $infoPackageId): RedirectResponse
    {
        try {
            $this->shipmentGenerationService->generateForInfoShipment($infoPackageId);
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

    private function normalizeEmployeeId($value): ?int
    {
        if ($value === null || '' === trim((string) $value)) {
            return null;
        }

        $candidate = (int) $value;

        return $candidate > 0 ? $candidate : null;
    }

    /**
     * @return array{0:?int,1:?string}
     */
    private function resolveCurrentEmployee(): array
    {
        $context = \Context::getContext();
        $employee = $context->employee ?? null;

        if (null === $employee) {
            return [null, null];
        }

        $employeeId = isset($employee->id) ? (int) $employee->id : null;
        $fullName = trim(sprintf('%s %s', (string) ($employee->firstname ?? ''), (string) ($employee->lastname ?? '')));

        if ('' === $fullName && isset($employee->email)) {
            $fullName = (string) $employee->email;
        }

        return [$employeeId, '' === $fullName ? null : $fullName];
    }

    private function translate(string $message, array $parameters = []): string
    {
        return $this->trans($message, self::TRANSLATION_DOMAIN, $parameters);
    }

    private function cleanupFailedShipment(int $orderId, int $infoPackageId, ShipmentRepository $shipmentRepository, ?\Roanja\Module\RjMulticarrier\Entity\Shipment $previousShipment): void
    {
        try {
            $currentShipment = $shipmentRepository->findOneByOrderId($orderId);

            if (!$currentShipment || $currentShipment === $previousShipment) {
                return;
            }

            if ($currentShipment->getInfoShipment()->getId() !== $infoPackageId) {
                return;
            }

            $this->getCommandBus()->handle(new DeleteShipmentCommand((int) $currentShipment->getId()));
        } catch (\Throwable) {
            // swallow cleanup failures to avoid masking original error
        }
    }

    /**
     * @return array{success: list<string>, error: list<string>, warning: list<string>, info: list<string>}
     */
    private function initMessageStack(): array
    {
        return [
            'success' => [],
            'error' => [],
            'warning' => [],
            'info' => [],
        ];
    }

    /**
     * @param array{success: list<string>, error: list<string>, warning: list<string>, info: list<string>} $stack
     */
    private function pushMessage(array &$stack, string $type, string $message, bool $shouldFlash): void
    {
        if (!isset($stack[$type])) {
            $stack[$type] = [];
        }

        $stack[$type][] = $message;

        if ($shouldFlash) {
            $flashType = 'error' === $type ? 'error' : $type;
            $this->addFlash($flashType, $message);
        }
    }

    /**
     * @param array{success: list<string>, error: list<string>, warning: list<string>, info: list<string>} $messages
     * @param array{packageId: ?int, shipmentId: ?int, labelsUrl: ?string} $payload
     */
    private function finalizeUpsertResponse(Request $request, bool $isAjax, array $messages, array $payload, bool $success, int $statusCode): Response
    {
        if (!$isAjax) {
            return $this->redirect($this->getRedirectUrl($request));
        }

        return $this->json([
            'success' => $success,
            'messages' => $messages,
            'packageId' => $payload['packageId'],
            'shipmentId' => $payload['shipmentId'],
            'labelsUrl' => $payload['labelsUrl'],
        ], $statusCode);
    }
}
