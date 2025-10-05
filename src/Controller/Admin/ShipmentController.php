<?php
/**
 * Symfony controller for managing shipments.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Controller\Admin;

use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShop\PrestaShop\Core\Grid\Presenter\GridPresenterInterface;
use Roanja\Module\RjMulticarrier\Domain\Shipment\Command\DeleteShipmentCommand;
use Roanja\Module\RjMulticarrier\Domain\Shipment\Exception\ShipmentException;
use Roanja\Module\RjMulticarrier\Domain\Shipment\Handler\DeleteShipmentHandler;
use Roanja\Module\RjMulticarrier\Grid\Shipment\ShipmentFilters;
use Roanja\Module\RjMulticarrier\Grid\Shipment\ShipmentGridFactory;
use Roanja\Module\RjMulticarrier\Service\Shipment\ShipmentLabelPrinter;
use Symfony\Contracts\Translation\TranslatorInterface;
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
        private readonly GridPresenterInterface $gridPresenter,
        private readonly ShipmentLabelPrinter $labelPrinter,
        private readonly DeleteShipmentHandler $deleteShipmentHandler,
        private readonly TranslatorInterface $translator
    ) {
    }

    public function indexAction(Request $request): Response
    {
        $filters = $this->buildFilters($request);
        $grid = $this->gridFactory->getGrid($filters);
        $gridView = $this->gridPresenter->present($grid);

    return $this->render('@Modules/rj_multicarrier/views/templates/admin/shipment/index.html.twig', [
            'shipmentGrid' => $gridView,
        ]);
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
            $this->deleteShipmentHandler->handle(new DeleteShipmentCommand($id));
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
        $parameters = $request->query->all($scope);

        if (!is_array($parameters) || empty($parameters)) {
            $parameters = $request->request->all($scope);
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
