<?php

/**
 * Symfony controller for carrier logs management.
 */

declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Controller\Admin;

use Roanja\Module\RjMulticarrier\Domain\InfoShipment\Query\GetInfoShipmentsForExport;
use Roanja\Module\RjMulticarrier\Domain\InfoShipment\Query\GetInfoShipmentsByIds;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use PrestaShop\PrestaShop\Core\Grid\Presenter\GridPresenterInterface;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Security\Annotation\AdminSecurity;
use Roanja\Module\RjMulticarrier\Domain\InfoShipment\Query\GetInfoShipmentForView;
use Roanja\Module\RjMulticarrier\Domain\InfoShipment\View\InfoShipmentView;
use Roanja\Module\RjMulticarrier\Domain\Shipment\Exception\ShipmentGenerationException;
use Roanja\Module\RjMulticarrier\Grid\InfoShipment\InfoShipmentFilters;
use Roanja\Module\RjMulticarrier\Grid\InfoShipment\InfoShipmentGridFactory;
use Roanja\Module\RjMulticarrier\Service\Shipment\ShipmentGenerationService;
use Roanja\Module\RjMulticarrier\Service\Presenter\OrderViewPresenter;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class InfoShipmentController extends FrameworkBundleAdminController
{
    private const TRANSLATION_DOMAIN = 'Modules.RjMulticarrier.Admin';

    public function __construct(
        private readonly InfoShipmentGridFactory $gridFactory,
        private readonly GridPresenterInterface $gridPresenter,
        private readonly ShipmentGenerationService $shipmentGenerationService,
        private readonly TranslatorInterface $translator,
        private readonly OrderViewPresenter $orderViewPresenter
    ) {}

    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function panelAction(Request $request, int $orderId): Response
    {
        if ($orderId <= 0) {
            return new Response(
                $this->translate('Identificador de pedido inválido.'),
                Response::HTTP_BAD_REQUEST
            );
        }

        $mode = (string) $request->query->get('mode', 'form');
        $mode = in_array($mode, ['form', 'preview'], true) ? $mode : 'form';
        $infoShipmentId = max(0, (int) $request->query->get('infoShipmentId', 0));

        $context = [
            'notifications' => $this->collectFlashNotifications(),
            'mode' => $mode,
            'infoShipmentId' => $infoShipmentId,
        ];

        if ($infoShipmentId > 0) {
            /** @var InfoShipmentView|null $packageView */
            $packageView = $this->getQueryBus()->handle(new GetInfoShipmentForView($infoShipmentId));

            if (null === $packageView) {
                if ('preview' === $mode) {
                    return new Response(
                        $this->translate('El paquete solicitado ya no existe.'),
                        Response::HTTP_NOT_FOUND
                    );
                }
            } else {
                $packageArray = $this->mapInfoShipmentView($packageView);

                if ((int) ($packageArray['id_order'] ?? 0) !== $orderId) {
                    if ('preview' === $mode) {
                        return new Response(
                            $this->translate('El paquete no pertenece al pedido solicitado.'),
                            Response::HTTP_NOT_FOUND
                        );
                    }

                    $context['notifications']['warning'][] = $this->translate('El paquete seleccionado no coincide con el pedido.');
                } else {
                    $context['package'] = $packageArray;
                }
            }
        }

        try {
            $html = $this->orderViewPresenter->present($orderId, $context);
        } catch (Throwable $throwable) {
            if ($this->has('logger')) {
                $this->get('logger')->error('rj_multicarrier: unable to render info shipment panel', [
                    'order_id' => $orderId,
                    'exception' => $throwable,
                ]);
            }

            return new Response(
                $this->translate('No se pudo cargar la información del pedido.'),
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }

        if ('' === trim($html)) {
            return new Response(
                $this->translate('No se encontró información para este pedido.'),
                Response::HTTP_NOT_FOUND
            );
        }

        return new Response($html);
    }

    public function indexAction(Request $request, InfoShipmentFilters $filters): Response
    {
        $filters->setNeedsToBePersisted(false);
        $grid = $this->gridFactory->getGrid($filters);
        $gridView = $this->gridPresenter->present($grid);

        return $this->render('@Modules/rj_multicarrier/views/templates/admin/info_shipment/index.html.twig', [
            'infoShipmentGrid' => $gridView,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapInfoShipmentView(InfoShipmentView $view): array
    {
        $data = $view->toArray();

        return [
            'id_info_shipment' => $data['id'] ?? null,
            'id_order' => $data['orderId'] ?? null,
            'id_reference_carrier' => $data['referenceCarrierId'] ?? null,
            'id_type_shipment' => $data['typeShipmentId'] ?? null,
            'id_carrier' => $data['carrierId'] ?? null,
            'quantity' => $data['quantity'] ?? null,
            'weight' => $data['weight'] ?? null,
            'length' => $data['length'] ?? null,
            'width' => $data['width'] ?? null,
            'height' => $data['height'] ?? null,
            'cash_ondelivery' => $data['cashOnDelivery'] ?? null,
            'message' => $data['message'] ?? null,
            'hour_from' => $data['hourFrom'] ?? null,
            'hour_until' => $data['hourUntil'] ?? null,
            'retorno' => $data['retorno'] ?? null,
            'rcs' => isset($data['rcs']) ? (int) $data['rcs'] : null,
            'vsec' => $data['vsec'] ?? null,
            'dorig' => $data['dorig'] ?? null,
            'date_add' => $data['createdAt'] ?? null,
            'date_upd' => $data['updatedAt'] ?? null,
        ];
    }

    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function viewAction(int $id): JsonResponse
    {
        /** @var InfoShipmentView|null $infoShipment */
        $infoShipment = $this->getQueryBus()->handle(new GetInfoShipmentForView($id));

        if (null === $infoShipment) {
            return $this->json([
                'message' => $this->translate('El paquete solicitado ya no existe.'),
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->formatInfoShipmentView($infoShipment));
    }

    public function generateAction(Request $request, int $infoShipmentId): RedirectResponse
    {
        if ($infoShipmentId <= 0) {
            $this->addFlash('warning', $this->translate('Primero crea un paquete de información para este pedido.'));

            return $this->redirectToInfoShipments();
        }

        try {
            $this->shipmentGenerationService->generateForInfoShipment($infoShipmentId);
            $this->addFlash('success', $this->translate('Envío generado correctamente.'));
        } catch (ShipmentGenerationException $exception) {
            $this->addFlash('error', $exception->getMessage());
        } catch (Throwable $throwable) {
            $this->addFlash('error', $this->translate('No se pudo generar el envío: %error%', [
                '%error%' => $throwable->getMessage(),
            ]));
        }

        return $this->redirectToInfoShipments();
    }

    public function bulkGenerateAction(Request $request): RedirectResponse
    {
        $selectedIds = $this->getSelectedInfoShipmentIds($request);

        if ([] === $selectedIds) {
            $this->addFlash('warning', $this->translate('Selecciona al menos un paquete.'));

            return $this->redirectToInfoShipments();
        }

        $result = $this->shipmentGenerationService->generateBulk($selectedIds);

        if (!empty($result['generated'])) {
            $this->addFlash('success', $this->translate('Se generaron %count% envíos.', [
                '%count%' => count($result['generated']),
            ]));
        }

        if (!empty($result['errors'])) {
            foreach ($result['errors'] as $infoShipmentId => $message) {
                $this->addFlash('error', $this->translate('Paquete #%id%: %message%', [
                    '%id%' => $infoShipmentId,
                    '%message%' => $message,
                ]));
            }
        }

        return $this->redirectToInfoShipments();
    }

    /**
     * @return array<string, mixed>
     */
    private function formatInfoShipmentView(InfoShipmentView $view): array
    {
        $data = $view->toArray();

        return [
            'id' => $data['id'],
            'orderId' => $data['orderId'],
            'referenceCarrierId' => $data['referenceCarrierId'],
            'typeShipmentId' => $data['typeShipmentId'],
            'typeShipmentName' => $data['typeShipmentName'],
            'companyId' => $data['companyId'],
            'companyName' => $data['companyName'],
            'companyShortName' => $data['companyShortName'],
            'quantity' => $data['quantity'],
            'weight' => $data['weight'],
            'length' => $data['length'],
            'width' => $data['width'],
            'height' => $data['height'],
            'cashOnDelivery' => $data['cashOnDelivery'],
            'message' => $data['message'],
            'hourFrom' => $data['hourFrom'],
            'hourUntil' => $data['hourUntil'],
            'retorno' => $data['retorno'],
            'rcs' => $data['rcs'],
            'vsec' => $data['vsec'],
            'dorig' => $data['dorig'],
            'createdAt' => $data['createdAt'],
            'updatedAt' => $data['updatedAt'],
        ];
    }

    /**
     * @AdminSecurity("is_granted('delete', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function deleteBulkAction(Request $request): RedirectResponse
    {
        $infoShipmentIds = $this->getBulkInfoShipmentIds($request);

        if (empty($infoShipmentIds)) {
            $this->addFlash('warning', $this->translate('No se seleccionaron paquetes para eliminar.'));

            return $this->redirectToRoute('admin_rj_multicarrier_info_shipments_index');
        }

        try {
            // TODO: Implement bulk delete command when needed
            $this->addFlash('info', $this->translate('Funcionalidad de eliminación masiva no implementada aún.'));
        } catch (Throwable $exception) {
            $this->addFlash('error', $this->translate('No se pudo completar la eliminación masiva.'));
        }

        return $this->redirectToRoute('admin_rj_multicarrier_info_shipments_index');
    }

    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function exportCsvAction(Request $request): Response
    {
        $selectedIds = $this->getSelectedInfoShipmentIds($request);

        if (!empty($selectedIds)) {
            if ($this->has('logger')) {
                $this->get('logger')->debug('rj_multicarrier: info_shipments exportCsvAction - exporting selected ids', [
                    'selected_ids' => $selectedIds,
                    'request_query' => $request->query->all(),
                    'request_post' => $request->request->all(),
                ]);
            }

            $infoShipments = $this->getQueryBus()->handle(new GetInfoShipmentsByIds($selectedIds));
        } else {
            $filters = $this->buildFilters($request);
            $filters->set('limit', 0);
            $filters->set('offset', 0);

            if ($this->has('logger')) {
                $this->get('logger')->debug('rj_multicarrier: info_shipments exportCsvAction request', [
                    'request_query' => $request->query->all(),
                    'request_post' => $request->request->all(),
                    'resolved_filters' => $filters->getFilters(),
                ]);
            }

            $infoShipments = $this->getQueryBus()->handle(new GetInfoShipmentsForExport($filters->getFilters()));
        }

    $fileName = sprintf('rj_multicarrier_info_shipments_%s.csv', date('Ymd_His'));

        return $this->createCsvResponse($infoShipments, $fileName);
    }

    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function exportSelectedCsvAction(Request $request): Response
    {
        $infoShipmentIds = $this->getBulkInfoShipmentIds($request);

        if (empty($infoShipmentIds)) {
            $this->addFlash('warning', $this->translate('Selecciona al menos un paquete para exportar.'));
            return $this->redirectToRoute('admin_rj_multicarrier_info_shipments_index');
        }

        /** @var InfoShipmentView[] $infoShipments */
        $infoShipments = $this->getQueryBus()->handle(new GetInfoShipmentsByIds($infoShipmentIds));

        if (empty($infoShipments)) {
            $this->addFlash('warning', $this->translate('No se encontraron paquetes para exportar.'));
            return $this->redirectToRoute('admin_rj_multicarrier_info_shipments_index');
        }

        $fileName = sprintf('rj_multicarrier_info_shipments_seleccion_%s.csv', date('Ymd_His'));

        // Temporary debug: log selected ids and request payload
        if ($this->has('logger')) {
            $this->get('logger')->debug('rj_multicarrier: info_shipments exportSelectedCsvAction request', [
                'request_query' => $request->query->all(),
                'request_post' => $request->request->all(),
                'selected_ids' => $infoShipmentIds,
            ]);
        }

        return $this->createCsvResponse($infoShipments, $fileName);
    }

    /**
     * @param iterable<InfoShipmentView|array> $infoShipments
     */
    private function createCsvResponse(iterable $infoShipments, string $fileName): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($infoShipments): void {
            $handle = fopen('php://output', 'wb');
            fputcsv($handle, [
                'ID',
                'ID Pedido',
                'Referencia Pedido',
                'ID Carrier Ref',
                'ID Tipo Envío',
                'Tipo Envío',
                'ID Compañía',
                'Compañía',
                'Compañía (corto)',
                'Cantidad',
                'Peso',
                'Largo',
                'Ancho',
                'Alto',
                'Contra reembolso',
                'Mensaje',
                'Hora desde',
                'Hora hasta',
                'Retorno',
                'RCS',
                'VSEC',
                'DORIG',
                'Creado',
                'Actualizado',
            ]);
            foreach ($infoShipments as $infoShipment) {
                if (method_exists($infoShipment, 'toCsvRow')) {
                    fputcsv($handle, $infoShipment->toCsvRow());
                } else {
                    fputcsv($handle, is_array($infoShipment) ? $infoShipment : []);
                }
            }
            fclose($handle);
        });
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $fileName
        ));
        return $response;
    }

    private function getBulkInfoShipmentIds(Request $request): array
    {
        $infoShipmentIds = $request->request->all('info_shipment_bulk');

        if (!is_array($infoShipmentIds)) {
            return [];
        }

        return array_values(array_filter(array_map('intval', $infoShipmentIds), static fn(int $id): bool => $id > 0));
    }

    private function buildFilters(Request $request): InfoShipmentFilters
    {
        $defaults = InfoShipmentFilters::getDefaults();
        $scopedParameters = $this->extractScopedParameters($request, 'rj_multicarrier_info_shipment');

        $filterValues = array_merge($defaults, [
            'limit' => $this->getIntParam($request, $scopedParameters, 'limit', $defaults['limit']),
            'offset' => $this->getIntParam($request, $scopedParameters, 'offset', $defaults['offset']),
            'orderBy' => $this->getStringParam($request, $scopedParameters, 'orderBy', (string) $defaults['orderBy']),
            'sortOrder' => $this->getStringParam($request, $scopedParameters, 'sortOrder', (string) $defaults['sortOrder']),
            'filters' => $this->getArrayParam($request, $scopedParameters, 'filters', $defaults['filters']),
        ]);

        $filters = new InfoShipmentFilters($filterValues);
        $filters->setNeedsToBePersisted(false);

        return $filters;
    }

    private function getSelectedInfoShipmentIds(Request $request): array
    {
        $selected = $request->request->get('rj_multicarrier_info_shipment_info_shipment_bulk');

        if (!is_array($selected)) {
            return [];
        }

        return array_values(array_filter(array_map('intval', $selected), static fn(int $id): bool => $id > 0));
    }

    private function redirectToInfoShipments(): RedirectResponse
    {
        return $this->redirect($this->generateUrl('admin_rj_multicarrier_info_shipments_index'));
    }

    private function extractScopedParameters(Request $request, string $scope): array
    {
        // Use get($scope, []) to retrieve nested scoped parameters from query or request
        $parameters = $request->query->get($scope, []);

        if (!is_array($parameters) || empty($parameters)) {
            $parameters = $request->request->get($scope, []);
        }

        return is_array($parameters) ? $parameters : [];
    }

    private function getIntParam(Request $request, array $scopedParameters, string $key, int $default): int
    {
        if (isset($scopedParameters[$key])) {
            return (int) $scopedParameters[$key];
        }

        if ($request->query->has($key)) {
            return $request->query->getInt($key, $default);
        }

        if ($request->request->has($key)) {
            return $request->request->getInt($key, $default);
        }

        return $default;
    }

    private function getStringParam(Request $request, array $scopedParameters, string $key, string $default): string
    {
        if (isset($scopedParameters[$key]) && '' !== (string) $scopedParameters[$key]) {
            return (string) $scopedParameters[$key];
        }

        if ($request->query->has($key)) {
            return (string) $request->query->get($key, $default);
        }

        if ($request->request->has($key)) {
            return (string) $request->request->get($key, $default);
        }

        return $default;
    }

    private function getArrayParam(Request $request, array $scopedParameters, string $key, array $default): array
    {
        if (isset($scopedParameters[$key]) && is_array($scopedParameters[$key])) {
            return $scopedParameters[$key];
        }

        $queryValue = $request->query->get($key);
        if (is_array($queryValue)) {
            return $queryValue;
        }

        $requestValue = $request->request->get($key);
        if (is_array($requestValue)) {
            return $requestValue;
        }

        return $default;
    }

    private function translate(string $message, array $parameters = []): string
    {
        return $this->translator->trans($message, $parameters, self::TRANSLATION_DOMAIN);
    }

    /**
     * @return array{success: string[], errors: string[], warning: string[], info: string[]}
     */
    private function collectFlashNotifications(): array
    {
        $notifications = [
            'success' => [],
            'errors' => [],
            'warning' => [],
            'info' => [],
        ];

        if (!$this->has('session')) {
            return $notifications;
        }

        $flashBag = $this->get('session')->getFlashBag();

        $mapping = [
            'success' => 'success',
            'error' => 'errors',
            'warning' => 'warning',
            'info' => 'info',
        ];

        foreach ($mapping as $flashKey => $targetKey) {
            $messages = method_exists($flashBag, 'peek') ? $flashBag->peek($flashKey) : $flashBag->get($flashKey);

            if (!is_array($messages)) {
                $messages = null === $messages ? [] : [$messages];
            }

            if (!empty($messages)) {
                $notifications[$targetKey] = array_merge($notifications[$targetKey], $messages);
            }
        }

        return $notifications;
    }
}
