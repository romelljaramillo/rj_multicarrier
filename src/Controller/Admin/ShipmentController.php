<?php
/**
 * Symfony controller for managing shipments.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Controller\Admin;

use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Security\Annotation\AdminSecurity;
use Roanja\Module\RjMulticarrier\Domain\Shipment\Command\BulkDeleteShipmentCommand;
use Roanja\Module\RjMulticarrier\Domain\Shipment\Command\DeleteShipmentCommand;
use Roanja\Module\RjMulticarrier\Domain\Shipment\Exception\ShipmentException;
use Roanja\Module\RjMulticarrier\Domain\Shipment\Exception\ShipmentNotFoundException;
use Roanja\Module\RjMulticarrier\Domain\Shipment\Query\GetShipmentForView;
use Roanja\Module\RjMulticarrier\Domain\Shipment\Query\GetShipmentsByIds;
use Roanja\Module\RjMulticarrier\Domain\Shipment\Query\GetShipmentsForExport;
use Roanja\Module\RjMulticarrier\Domain\Shipment\View\ShipmentView;
use Roanja\Module\RjMulticarrier\Grid\Shipment\ShipmentFilters;
use Roanja\Module\RjMulticarrier\Grid\Shipment\ShipmentGridDefinitionFactory;
use Roanja\Module\RjMulticarrier\Grid\Shipment\ShipmentGridFactory;
use Roanja\Module\RjMulticarrier\Service\Shipment\ShipmentLabelPrinter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Throwable;

final class ShipmentController extends FrameworkBundleAdminController
{
    private const TRANSLATION_DOMAIN = 'Modules.RjMulticarrier.Admin';

    public function __construct(
        private readonly ShipmentGridFactory $gridFactory,
        private readonly ShipmentLabelPrinter $labelPrinter
    ) {
    }

    public function indexAction(Request $request, \Roanja\Module\RjMulticarrier\Grid\Shipment\ShipmentFilters $filters): Response
    {
        $filters->setNeedsToBePersisted(false);
        $grid = $this->gridFactory->getGrid($filters);

        return $this->render('@Modules/rj_multicarrier/views/templates/admin/shipment/index.html.twig', [
            'shipmentGrid' => $this->presentGrid($grid),
        ]);
    }

    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function viewAction(int $id): JsonResponse
    {
        try {
            /** @var ShipmentView|null $shipment */
            $shipment = $this->getQueryBus()->handle(new GetShipmentForView($id));

            if (null === $shipment) {
                return $this->json([
                    'message' => $this->translate('El envío solicitado ya no existe.'),
                ], Response::HTTP_NOT_FOUND);
            }

            return $this->json($this->formatShipmentView($shipment));
        } catch (\Throwable $exception) {
            return $this->json([
                'message' => $this->translate('Error al cargar el detalle del envío: %error%', [
                    '%error%' => $exception->getMessage(),
                ]),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function printAction(int $id): Response
    {
        try {
            return $this->labelPrinter->streamShipmentLabels($id);
        } catch (ShipmentException $exception) {
            $this->addFlash('error', $exception->getMessage());
        } catch (Throwable $throwable) {
            $this->addFlash('error', $this->translate('No se pudieron generar las etiquetas: %error%', [
                '%error%' => $throwable->getMessage(),
            ]));
        }

        return $this->redirectToShipments();
    }

    public function deleteAction(Request $request, int $id): RedirectResponse
    {
        $this->validateCsrfToken('delete_shipment_' . $id, (string) $request->request->get('_token'));

        try {
            $this->getCommandBus()->handle(new DeleteShipmentCommand($id));
            $this->addFlash('success', $this->translate('Envío eliminado correctamente.'));
        } catch (ShipmentException $exception) {
            $this->addFlash('error', $exception->getMessage());
        } catch (Throwable $throwable) {
            $this->addFlash('error', $this->translate('No se pudo eliminar el envío: %error%', [
                '%error%' => $throwable->getMessage(),
            ]));
        }

        return $this->redirectToShipments();
    }

    /**
     * @AdminSecurity("is_granted('delete', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function deleteBulkAction(Request $request): RedirectResponse
    {
        $shipmentIds = $this->getBulkShipmentIds($request);

        if (empty($shipmentIds)) {
            $this->addFlash('warning', $this->translate('No se seleccionaron registros para eliminar.'));

            return $this->redirectToRoute('admin_rj_multicarrier_shipments_index');
        }

        try {
            $this->getCommandBus()->handle(new BulkDeleteShipmentCommand($shipmentIds));
            $this->addFlash('success', $this->translate('%count% registros eliminados.', ['%count%' => count($shipmentIds)]));
        } catch (ShipmentException | Throwable $exception) {
            $this->addFlash('error', $this->translate('No se pudo completar la eliminación masiva.'));
        }

        return $this->redirectToRoute('admin_rj_multicarrier_shipments_index');
    }

    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function exportCsvAction(Request $request): Response
    {
        $selectedIds = $this->getBulkShipmentIds($request);

        if (!empty($selectedIds)) {
            if ($this->has('logger')) {
                $this->get('logger')->debug('rj_multicarrier: shipments exportCsvAction - exporting selected ids', [
                    'selected_ids' => $selectedIds,
                    'request_query' => $request->query->all(),
                    'request_post' => $request->request->all(),
                ]);
            }

            $shipments = $this->getQueryBus()->handle(new GetShipmentsByIds($selectedIds));
        } else {
            $filters = $this->buildFilters($request);
            $filters->set('limit', 0);
            $filters->set('offset', 0);

            if ($this->has('logger')) {
                $this->get('logger')->debug('rj_multicarrier: shipments exportCsvAction request', [
                    'request_query' => $request->query->all(),
                    'request_post' => $request->request->all(),
                    'resolved_filters' => $filters->getFilters(),
                ]);
            }

            $shipments = $this->getQueryBus()->handle(new GetShipmentsForExport($filters->getFilters()));
        }

        $fileName = sprintf('rj_multicarrier_shipments_%s.csv', date('Ymd_His'));

        return $this->createShipmentCsvResponse($shipments, $fileName);
    }

    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function exportSelectedCsvAction(Request $request): Response
    {
        $shipmentIds = $this->getBulkShipmentIds($request);

        if (empty($shipmentIds)) {
            $this->addFlash('warning', $this->translate('Selecciona al menos un registro para exportar.'));

            return $this->redirectToRoute('admin_rj_multicarrier_shipments_index');
        }

        // Temporary debug: log selected ids and request payload
        if ($this->has('logger')) {
            $this->get('logger')->debug('rj_multicarrier: shipments exportSelectedCsvAction request', [
                'request_query' => $request->query->all(),
                'request_post' => $request->request->all(),
                'selected_ids' => $shipmentIds,
            ]);
        }

        /** @var ShipmentView[] $shipments */
        $shipments = $this->getQueryBus()->handle(new GetShipmentsByIds($shipmentIds));

        if (empty($shipments)) {
            $this->addFlash('warning', $this->translate('No se encontraron registros para exportar.'));

            return $this->redirectToRoute('admin_rj_multicarrier_shipments_index');
        }

        $fileName = sprintf('rj_multicarrier_shipments_seleccion_%s.csv', date('Ymd_His'));

        return $this->createShipmentCsvResponse($shipments, $fileName);
    }

    private function buildFilters(Request $request): ShipmentFilters
    {
        $defaults = ShipmentFilters::getDefaults();
        $scopedParameters = $this->extractScopedParameters($request, 'rj_multicarrier_shipment');

        $filterValues = array_merge($defaults, [
            'limit' => $this->getIntParam($request, $scopedParameters, 'limit', $defaults['limit']),
            'offset' => $this->getIntParam($request, $scopedParameters, 'offset', $defaults['offset']),
            'orderBy' => $this->getStringParam($request, $scopedParameters, 'orderBy', (string) $defaults['orderBy']),
            'sortOrder' => $this->getStringParam($request, $scopedParameters, 'sortOrder', (string) $defaults['sortOrder']),
            'filters' => $this->getArrayParam($request, $scopedParameters, 'filters', $defaults['filters']),
        ]);

        $filters = new ShipmentFilters($filterValues);
        $filters->setNeedsToBePersisted(false);

        return $filters;
    }

    private function redirectToShipments(): RedirectResponse
    {
        try {
            return $this->redirect($this->generateUrl('admin_rj_multicarrier_shipments_index'));
        } catch (RouteNotFoundException $exception) {
            return $this->redirect($this->generateLegacyModuleUrl());
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function formatShipmentView(ShipmentView $shipment): array
    {
        $data = $shipment->toArray();
        $metadata = $data['metadata'] ?? [];

        return [
            'id' => $data['id'],
            'orderId' => $data['orderId'],
            'orderReference' => $data['orderReference'],
            'shipmentNumber' => $data['shipmentNumber'],
            'carrierShortName' => $data['carrierShortName'],
            'createdAt' => $data['createdAt'],
            'updatedAt' => $data['updatedAt'],
            'package' => $data['package'],
            'labels' => $data['labels'],
            'account' => $metadata['account'] ?? null,
            'product' => $metadata['product'] ?? null,
            'deleted' => (bool) ($metadata['deleted'] ?? false),
            'requestPayload' => $metadata['requestPayload'] ?? null,
            'responsePayload' => $metadata['responsePayload'] ?? null,
        ];
    }

    private function generateLegacyModuleUrl(): string
    {
        return $this->get('router')->generate('admin_modules_manage');
    }

    private function validateCsrfToken(string $id, string $token): void
    {
        if (!$this->isCsrfTokenValid($id, $token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
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
        return $this->trans($message, self::TRANSLATION_DOMAIN, $parameters);
    }

        /**
     * @return array<int>
     */
    private function getBulkShipmentIds(Request $request): array
    {
        $gridId = ShipmentGridDefinitionFactory::GRID_ID;
        $columnName = $gridId . '_shipment_bulk';

        $collected = [];

        $gridPayload = $request->request->get($gridId);
        if (is_array($gridPayload)) {
            if (isset($gridPayload[$columnName]['ids']) && is_array($gridPayload[$columnName]['ids'])) {
                $collected = array_merge($collected, $gridPayload[$columnName]['ids']);
            }

            if (isset($gridPayload[$columnName]) && is_array($gridPayload[$columnName])) {
                $collected = array_merge($collected, $gridPayload[$columnName]);
            }
        }

        $flat = $request->request->get($columnName);
        if (is_array($flat)) {
            $collected = array_merge($collected, $flat);
        }

        $legacy = $request->request->get('ids');
        if (is_array($legacy)) {
            $collected = array_merge($collected, $legacy);
        }

        $collected = array_filter($collected, static function ($value): bool {
            return ctype_digit((string) $value) && (int) $value > 0;
        });

        $collected = array_map(static function ($value): int {
            return (int) $value;
        }, $collected);

        return array_values(array_unique($collected));
    }

    /**
     * Creates CSV response from shipment data.
     *
     * @param array $shipments
     * @param string $fileName
     *
     * @return Response
     */
    private function createShipmentCsvResponse(array $shipments, string $fileName): Response
    {
        $headers = [
            'ID',
            'Order ID',
            'Order Reference',
            'Shipment Number',
            'Carrier',
            'Package Weight',
            'Package Width',
            'Package Height',
            'Package Length',
            'Labels Count',
            'Created At',
            'Updated At'
        ];

        $csvContent = implode(',', $headers) . "\n";

        foreach ($shipments as $shipment) {
            $package = $shipment->getPackage();
            $labels = $shipment->getLabels();

            $row = [
                $shipment->getId(),
                $shipment->getOrderId(),
                '"' . str_replace('"', '""', $shipment->getOrderReference() ?? '') . '"',
                '"' . str_replace('"', '""', $shipment->getShipmentNumber() ?? '') . '"',
                '"' . str_replace('"', '""', $shipment->getCarrierShortName() ?? '') . '"',
                $package['weight'] ?? '',
                $package['width'] ?? '',
                $package['height'] ?? '',
                $package['length'] ?? '',
                count($labels),
                '"' . str_replace('"', '""', $shipment->getCreatedAt() ?? '') . '"',
                '"' . str_replace('"', '""', $shipment->getUpdatedAt() ?? '') . '"'
            ];

            $csvContent .= implode(',', $row) . "\n";
        }

        $response = new Response($csvContent);
        $response->headers->set('Content-Type', 'text/csv; charset=utf-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . $fileName . '"');

        return $response;
    }
}
