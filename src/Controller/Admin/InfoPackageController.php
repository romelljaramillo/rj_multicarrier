<?php

/**
 * Symfony controller for carrier logs management.
 */

declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Controller\Admin;

use Roanja\Module\RjMulticarrier\Domain\InfoPackage\Query\GetInfoPackagesForExport;
use Roanja\Module\RjMulticarrier\Domain\InfoPackage\Query\GetInfoPackagesByIds;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use PrestaShop\PrestaShop\Core\Grid\Presenter\GridPresenterInterface;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Security\Annotation\AdminSecurity;
use Roanja\Module\RjMulticarrier\Domain\InfoPackage\Query\GetInfoPackageForView;
use Roanja\Module\RjMulticarrier\Domain\InfoPackage\View\InfoPackageView;
use Roanja\Module\RjMulticarrier\Domain\Shipment\Exception\ShipmentGenerationException;
use Roanja\Module\RjMulticarrier\Grid\InfoPackage\InfoPackageFilters;
use Roanja\Module\RjMulticarrier\Grid\InfoPackage\InfoPackageGridFactory;
use Roanja\Module\RjMulticarrier\Service\Shipment\ShipmentGenerationService;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class InfoPackageController extends FrameworkBundleAdminController
{
    private const TRANSLATION_DOMAIN = 'Modules.RjMulticarrier.Admin';

    public function __construct(
        private readonly InfoPackageGridFactory $gridFactory,
        private readonly GridPresenterInterface $gridPresenter,
        private readonly ShipmentGenerationService $shipmentGenerationService,
        private readonly TranslatorInterface $translator
    ) {}

    public function indexAction(Request $request, InfoPackageFilters $filters): Response
    {
        $filters->setNeedsToBePersisted(false);
        $grid = $this->gridFactory->getGrid($filters);
        $gridView = $this->gridPresenter->present($grid);

        return $this->render('@Modules/rj_multicarrier/views/templates/admin/info_package/index.html.twig', [
            'infoPackageGrid' => $gridView,
        ]);
    }

    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function viewAction(int $id): JsonResponse
    {
        /** @var InfoPackageView|null $infoPackage */
        $infoPackage = $this->getQueryBus()->handle(new GetInfoPackageForView($id));

        if (null === $infoPackage) {
            return $this->json([
                'message' => $this->translate('El paquete solicitado ya no existe.'),
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->formatInfoPackageView($infoPackage));
    }

    public function generateAction(Request $request, int $infoPackageId): RedirectResponse
    {
        try {
            $this->shipmentGenerationService->generateForInfoPackage($infoPackageId);
            $this->addFlash('success', $this->translate('Envío generado correctamente.'));
        } catch (ShipmentGenerationException $exception) {
            $this->addFlash('error', $exception->getMessage());
        } catch (Throwable $throwable) {
            $this->addFlash('error', $this->translate('No se pudo generar el envío: %error%', [
                '%error%' => $throwable->getMessage(),
            ]));
        }

        return $this->redirectToInfoPackages();
    }

    public function bulkGenerateAction(Request $request): RedirectResponse
    {
        $selectedIds = $this->getSelectedInfoPackageIds($request);

        if ([] === $selectedIds) {
            $this->addFlash('warning', $this->translate('Selecciona al menos un paquete.'));

            return $this->redirectToInfoPackages();
        }

        $result = $this->shipmentGenerationService->generateBulk($selectedIds);

        if (!empty($result['generated'])) {
            $this->addFlash('success', $this->translate('Se generaron %count% envíos.', [
                '%count%' => count($result['generated']),
            ]));
        }

        if (!empty($result['errors'])) {
            foreach ($result['errors'] as $infoPackageId => $message) {
                $this->addFlash('error', $this->translate('Paquete #%id%: %message%', [
                    '%id%' => $infoPackageId,
                    '%message%' => $message,
                ]));
            }
        }

        return $this->redirectToInfoPackages();
    }

    /**
     * @return array<string, mixed>
     */
    private function formatInfoPackageView(InfoPackageView $view): array
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
        $infoPackageIds = $this->getBulkInfoPackageIds($request);

        if (empty($infoPackageIds)) {
            $this->addFlash('warning', $this->translate('No se seleccionaron paquetes para eliminar.'));

            return $this->redirectToRoute('admin_rj_multicarrier_info_packages_index');
        }

        try {
            // TODO: Implement bulk delete command when needed
            $this->addFlash('info', $this->translate('Funcionalidad de eliminación masiva no implementada aún.'));
        } catch (Throwable $exception) {
            $this->addFlash('error', $this->translate('No se pudo completar la eliminación masiva.'));
        }

        return $this->redirectToRoute('admin_rj_multicarrier_info_packages_index');
    }

    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function exportCsvAction(Request $request): Response
    {
        $selectedIds = $this->getSelectedInfoPackageIds($request);

        if (!empty($selectedIds)) {
            if ($this->has('logger')) {
                $this->get('logger')->debug('rj_multicarrier: info_packages exportCsvAction - exporting selected ids', [
                    'selected_ids' => $selectedIds,
                    'request_query' => $request->query->all(),
                    'request_post' => $request->request->all(),
                ]);
            }

            $infoPackages = $this->getQueryBus()->handle(new GetInfoPackagesByIds($selectedIds));
        } else {
            $filters = $this->buildFilters($request);
            $filters->set('limit', 0);
            $filters->set('offset', 0);

            if ($this->has('logger')) {
                $this->get('logger')->debug('rj_multicarrier: info_packages exportCsvAction request', [
                    'request_query' => $request->query->all(),
                    'request_post' => $request->request->all(),
                    'resolved_filters' => $filters->getFilters(),
                ]);
            }

            $infoPackages = $this->getQueryBus()->handle(new GetInfoPackagesForExport($filters->getFilters()));
        }

        $fileName = sprintf('rj_multicarrier_info_packages_%s.csv', date('Ymd_His'));

        return $this->createCsvResponse($infoPackages, $fileName);
    }

    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function exportSelectedCsvAction(Request $request): Response
    {
        $infoPackageIds = $this->getBulkInfoPackageIds($request);

        if (empty($infoPackageIds)) {
            $this->addFlash('warning', $this->translate('Selecciona al menos un paquete para exportar.'));
            return $this->redirectToRoute('admin_rj_multicarrier_info_packages_index');
        }

        /** @var InfoPackageView[] $infoPackages */
        $infoPackages = $this->getQueryBus()->handle(new GetInfoPackagesByIds($infoPackageIds));

        if (empty($infoPackages)) {
            $this->addFlash('warning', $this->translate('No se encontraron paquetes para exportar.'));
            return $this->redirectToRoute('admin_rj_multicarrier_info_packages_index');
        }

        $fileName = sprintf('rj_multicarrier_info_packages_seleccion_%s.csv', date('Ymd_His'));

        // Temporary debug: log selected ids and request payload
        if ($this->has('logger')) {
            $this->get('logger')->debug('rj_multicarrier: info_packages exportSelectedCsvAction request', [
                'request_query' => $request->query->all(),
                'request_post' => $request->request->all(),
                'selected_ids' => $infoPackageIds,
            ]);
        }

        return $this->createCsvResponse($infoPackages, $fileName);
    }

    /**
     * @param iterable<InfoPackageView|array> $infoPackages
     */
    private function createCsvResponse(iterable $infoPackages, string $fileName): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($infoPackages): void {
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
            foreach ($infoPackages as $infoPackage) {
                if (method_exists($infoPackage, 'toCsvRow')) {
                    fputcsv($handle, $infoPackage->toCsvRow());
                } else {
                    fputcsv($handle, is_array($infoPackage) ? $infoPackage : []);
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

    private function getBulkInfoPackageIds(Request $request): array
    {
        $infoPackageIds = $request->request->all('info_package_bulk');

        if (!is_array($infoPackageIds)) {
            return [];
        }

        return array_filter(array_map('intval', $infoPackageIds));
    }

    private function buildFilters(Request $request): InfoPackageFilters
    {
        $defaults = InfoPackageFilters::getDefaults();
        $scopedParameters = $this->extractScopedParameters($request, 'rj_multicarrier_info_package');

        $filterValues = array_merge($defaults, [
            'limit' => $this->getIntParam($request, $scopedParameters, 'limit', $defaults['limit']),
            'offset' => $this->getIntParam($request, $scopedParameters, 'offset', $defaults['offset']),
            'orderBy' => $this->getStringParam($request, $scopedParameters, 'orderBy', (string) $defaults['orderBy']),
            'sortOrder' => $this->getStringParam($request, $scopedParameters, 'sortOrder', (string) $defaults['sortOrder']),
            'filters' => $this->getArrayParam($request, $scopedParameters, 'filters', $defaults['filters']),
        ]);

        $filters = new InfoPackageFilters($filterValues);
        $filters->setNeedsToBePersisted(false);

        return $filters;
    }

    private function getSelectedInfoPackageIds(Request $request): array
    {
        $selected = $request->request->get('rj_multicarrier_info_package_info_package_bulk');

        if (!is_array($selected)) {
            return [];
        }

        return array_values(array_filter(array_map('intval', $selected), static fn(int $id): bool => $id > 0));
    }

    private function redirectToInfoPackages(): RedirectResponse
    {
        return $this->redirect($this->generateUrl('admin_rj_multicarrier_info_packages_index'));
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
}
