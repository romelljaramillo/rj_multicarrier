<?php
/**
 * Symfony controller for managing info packages pending shipment generation.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Controller\Admin;

use PrestaShop\PrestaShop\Core\Grid\Presenter\GridPresenterInterface;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use Roanja\Module\RjMulticarrier\Domain\Shipment\Exception\ShipmentGenerationException;
use Roanja\Module\RjMulticarrier\Grid\InfoPackage\InfoPackageFilters;
use Roanja\Module\RjMulticarrier\Grid\InfoPackage\InfoPackageGridFactory;
use Roanja\Module\RjMulticarrier\Service\Shipment\ShipmentGenerationService;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
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
    ) {
    }

    public function indexAction(Request $request): Response
    {
        $filters = $this->buildFilters($request);
        $grid = $this->gridFactory->getGrid($filters);
        $gridView = $this->gridPresenter->present($grid);

    return $this->render('@Modules/rj_multicarrier/views/templates/admin/info_package/index.html.twig', [
            'infoPackageGrid' => $gridView,
        ]);
    }

    public function generateAction(Request $request, int $infoPackageId): RedirectResponse
    {
        $token = $this->getTokenFromRequest($request);
        $this->validateCsrfToken('generate_info_package_' . $infoPackageId, $token);

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
        $token = $this->getTokenFromRequest($request);
        $this->validateCsrfToken('bulk_generate_info_packages', $token);

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

        return array_values(array_filter(array_map('intval', $selected), static fn (int $id): bool => $id > 0));
    }

    private function redirectToInfoPackages(): RedirectResponse
    {
        return $this->redirect($this->generateUrl('admin_rj_multicarrier_info_packages_index'));
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

    private function getTokenFromRequest(Request $request): string
    {
        $token = (string) $request->request->get('_token', '');
        if ('' !== $token) {
            return $token;
        }

        return (string) $request->query->get('_token', '');
    }
}
