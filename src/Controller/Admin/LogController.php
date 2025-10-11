<?php

/**
 * Symfony controller for carrier logs management.
 */

declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Controller\Admin;

use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Security\Annotation\AdminSecurity;
use Roanja\Module\RjMulticarrier\Domain\Log\Command\BulkDeleteLogEntriesCommand;
use Roanja\Module\RjMulticarrier\Domain\Log\Command\DeleteLogEntryCommand;
use Roanja\Module\RjMulticarrier\Domain\Log\Exception\LogEntryException;
use Roanja\Module\RjMulticarrier\Domain\Log\Exception\LogEntryNotFoundException;
use Roanja\Module\RjMulticarrier\Domain\Log\Query\GetLogEntryForView;
use Roanja\Module\RjMulticarrier\Domain\Log\Query\GetLogsByIds;
use Roanja\Module\RjMulticarrier\Domain\Log\Query\GetLogsForExport;
use Roanja\Module\RjMulticarrier\Domain\Log\View\LogEntryView;
use Roanja\Module\RjMulticarrier\Grid\Log\LogFilters;
use Roanja\Module\RjMulticarrier\Grid\Log\LogGridDefinitionFactory;
use Roanja\Module\RjMulticarrier\Grid\Log\LogGridFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class LogController extends FrameworkBundleAdminController
{
    private const TRANSLATION_DOMAIN = 'Modules.RjMulticarrier.Admin';

    public function indexAction(Request $request, LogFilters $filters): Response
    {
        $filters->setNeedsToBePersisted(false);

        /** @var LogGridFactory $gridFactory */
        $gridFactory = $this->get('rj_multicarrier.grid.factory.log');
        $grid = $gridFactory->getGrid($filters);

        return $this->render('@Modules/rj_multicarrier/views/templates/admin/log/index.html.twig', [
            'layoutTitle' => $this->translate('Logs de envíos'),
            'grid' => $this->presentGrid($grid),
        ]);
    }

    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function viewAction(int $id): JsonResponse
    {
        /** @var LogEntryView|null $log */
        $log = $this->getQueryBus()->handle(new GetLogEntryForView($id));

        if (null === $log) {
            return $this->json([
                'message' => $this->translate('El registro solicitado ya no existe.'),
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json($log->toArray());
    }

    /**
     * @AdminSecurity("is_granted('delete', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function deleteAction(Request $request, int $id): RedirectResponse
    {
        try {
            $this->getCommandBus()->handle(new DeleteLogEntryCommand($id));
            $this->addFlash('success', $this->translate('El registro se eliminó correctamente.'));
        } catch (LogEntryNotFoundException $exception) {
            $this->addFlash('warning', $this->translate('El registro ya no existe.'));
        } catch (LogEntryException | Throwable $exception) {
            $this->addFlash('error', $this->translate('No se pudo eliminar el registro seleccionado.'));
        }

        return $this->redirectAfterDelete($request);
    }

    /**
     * @AdminSecurity("is_granted('delete', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function deleteBulkAction(Request $request): RedirectResponse
    {
        $logIds = $this->getBulkLogIds($request);

        if (empty($logIds)) {
            $this->addFlash('warning', $this->translate('No se seleccionaron registros para eliminar.'));

            return $this->redirectToRoute('admin_rj_multicarrier_logs_index');
        }

        try {
            $this->getCommandBus()->handle(new BulkDeleteLogEntriesCommand($logIds));
            $this->addFlash('success', $this->translate('%count% registros eliminados.', ['%count%' => count($logIds)]));
        } catch (LogEntryException | Throwable $exception) {
            $this->addFlash('error', $this->translate('No se pudo completar la eliminación masiva.'));
        }

        return $this->redirectAfterDelete($request);
    }

    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function exportCsvAction(Request $request): Response
    {
        $filters = $this->buildFilters($request);
        $filters->set('limit', 0);
        $filters->set('offset', 0);

        /** @var LogEntryView[] $logs */
        $logs = $this->getQueryBus()->handle(new GetLogsForExport($filters->getFilters()));

        $fileName = sprintf('rj_multicarrier_logs_%s.csv', date('Ymd_His'));

        return $this->createCsvResponse($logs, $fileName);
    }

    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function exportSelectedCsvAction(Request $request): Response
    {
        $logIds = $this->getBulkLogIds($request);

        if (empty($logIds)) {
            $this->addFlash('warning', $this->translate('Selecciona al menos un registro para exportar.'));

            return $this->redirectToRoute('admin_rj_multicarrier_logs_index');
        }

        /** @var LogEntryView[] $logs */
        $logs = $this->getQueryBus()->handle(new GetLogsByIds($logIds));

        if (empty($logs)) {
            $this->addFlash('warning', $this->translate('No se encontraron registros para exportar.'));

            return $this->redirectToRoute('admin_rj_multicarrier_logs_index');
        }

        $fileName = sprintf('rj_multicarrier_logs_seleccion_%s.csv', date('Ymd_His'));

        return $this->createCsvResponse($logs, $fileName);
    }

    private function buildFilters(Request $request): LogFilters
    {
        $defaults = LogFilters::getDefaults();

        $scopedParameters = $this->extractScopedParameters($request, LogGridDefinitionFactory::GRID_ID);

        $filterValues = array_merge($defaults, [
            'limit' => $this->getIntParam($request, $scopedParameters, 'limit', $defaults['limit']),
            'offset' => $this->getIntParam($request, $scopedParameters, 'offset', $defaults['offset']),
            'orderBy' => $this->getStringParam($request, $scopedParameters, 'orderBy', (string) $defaults['orderBy']),
            'sortOrder' => $this->getStringParam($request, $scopedParameters, 'sortOrder', (string) $defaults['sortOrder']),
            'filters' => $this->getArrayParam($request, $scopedParameters, 'filters', $defaults['filters']),
        ]);

        $filters = new LogFilters($filterValues);
        $filters->setNeedsToBePersisted(false);

        return $filters;
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
    private function getBulkLogIds(Request $request): array
    {
        $gridId = LogGridDefinitionFactory::GRID_ID;
        $columnName = $gridId . '_log_bulk';

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

    private function redirectAfterDelete(Request $request): RedirectResponse
    {
        $redirectUrl = $request->query->get('redirectUrl');

        if (is_string($redirectUrl) && '' !== trim($redirectUrl)) {
            return $this->redirect($redirectUrl);
        }

        return $this->redirectToRoute('admin_rj_multicarrier_logs_index');
    }

    /**
     * @param iterable<LogEntryView> $logs
     */
    private function createCsvResponse(iterable $logs, string $fileName): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($logs): void {
            $handle = fopen('php://output', 'wb');

            fputcsv($handle, [
                'ID',
                'Nombre',
                'Pedido',
                'Fecha creación',
                'Fecha actualización',
                'Request',
                'Response',
            ]);

            foreach ($logs as $log) {
                fputcsv($handle, $log->toCsvRow());
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
}
