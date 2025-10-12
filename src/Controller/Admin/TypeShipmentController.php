<?php

/**
 * Symfony controller for managing carrier type shipments.
 */

declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Controller\Admin;

use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Security\Annotation\AdminSecurity;
use Roanja\Module\RjMulticarrier\Domain\TypeShipment\Command\DeleteTypeShipmentCommand;
use Roanja\Module\RjMulticarrier\Domain\TypeShipment\Command\ToggleTypeShipmentStatusCommand;
use Roanja\Module\RjMulticarrier\Domain\TypeShipment\Command\UpsertTypeShipmentCommand;
use Roanja\Module\RjMulticarrier\Domain\TypeShipment\Exception\TypeShipmentException;
use Roanja\Module\RjMulticarrier\Domain\TypeShipment\Query\GetTypeShipmentForView;
use Roanja\Module\RjMulticarrier\Domain\TypeShipment\View\TypeShipmentView;
use Roanja\Module\RjMulticarrier\Entity\Carrier;
use Roanja\Module\RjMulticarrier\Entity\TypeShipment;
use Roanja\Module\RjMulticarrier\Form\TypeShipmentType;
use Roanja\Module\RjMulticarrier\Grid\TypeShipment\TypeShipmentFilters;
use Roanja\Module\RjMulticarrier\Grid\TypeShipment\TypeShipmentGridDefinitionFactory;
use Roanja\Module\RjMulticarrier\Grid\TypeShipment\TypeShipmentGridFactory;
use Roanja\Module\RjMulticarrier\Grid\TypeShipment\TypeShipmentQueryBuilder;
use Roanja\Module\RjMulticarrier\Repository\CarrierRepository;
use Roanja\Module\RjMulticarrier\Repository\TypeShipmentRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Shop;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class TypeShipmentController extends FrameworkBundleAdminController
{
    private const TRANSLATION_DOMAIN = 'Modules.RjMulticarrier.Admin';

    public function __construct(
        private readonly CarrierRepository $carrierRepository,
        private readonly TypeShipmentRepository $typeShipmentRepository,
        private readonly TypeShipmentGridFactory $gridFactory,
        private readonly TypeShipmentQueryBuilder $typeShipmentQueryBuilder
    ) {}

    public function indexAction(Request $request, \Roanja\Module\RjMulticarrier\Grid\TypeShipment\TypeShipmentFilters $filters): Response
    {
        // Resolve company and editing type shipment ids from query when needed for the form part
        $companyFromQuery = $request->query->getInt('company', 0);
        $typeShipmentId = $request->query->getInt('id', 0);

        $filters->setNeedsToBePersisted(false);
        $currentFilters = $filters->getFilters();
        $companyFromFilters = isset($currentFilters['carrier_id']) ? (int) $currentFilters['carrier_id'] : 0;

        $companies = $this->filterCarriersByContext($this->carrierRepository->findAllOrdered());

        if (empty($companies)) {
            $this->addFlash('warning', $this->translate('No hay transportistas disponibles en el contexto actual.'));

            return $this->redirectToRoute('admin_rj_multicarrier_carriers_index');
        }

        $activeCompanyId = $companyFromQuery > 0 ? $companyFromQuery : $companyFromFilters;
        $company = $this->resolveCompany($companies, $activeCompanyId);
        $companyId = $company?->getId() ?? 0;

        $editingTypeShipment = $typeShipmentId > 0
            ? $this->typeShipmentRepository->findOneById($typeShipmentId)
            : null;

        if ($editingTypeShipment instanceof TypeShipment) {
            $company = $editingTypeShipment->getCarrier();
            $companyId = $company->getId() ?? 0;
        }

        if ($companyId > 0) {
            $filters->addFilter([
                'carrier_id' => $companyId,
            ]);
        }

        $grid = $this->gridFactory->getGrid($filters);

        $formData = $this->buildFormData($company, $editingTypeShipment);
        $companyChoices = $this->buildCompanyChoices($companies, $companyId);
        $carrierChoices = $this->buildCarrierChoices($editingTypeShipment?->getReferenceCarrierId());

        $formActionParameters = [];
        if ($companyId > 0) {
            $formActionParameters['company'] = $companyId;
        }

        $form = $this->createForm(TypeShipmentType::class, $formData, [
            'company_choices' => $companyChoices,
            'carrier_choices' => $carrierChoices,
            'current_carrier_id' => $companyId,
            'action' => $this->generateUrl('admin_rj_multicarrier_type_shipment_index', $formActionParameters),
            'method' => Request::METHOD_POST,
        ]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $currentCompanyId = (int) ($data['carrier_id'] ?? 0);
            $typeShipmentUniqueId = $data['id'] ? (int) $data['id'] : null;

            $command = new UpsertTypeShipmentCommand(
                $typeShipmentUniqueId,
                $currentCompanyId,
                (string) $data['name'],
                (string) $data['business_code'],
                isset($data['reference_carrier_id']) && '' !== $data['reference_carrier_id']
                    ? (int) $data['reference_carrier_id']
                    : null,
                (bool) $data['active']
            );

            try {
                $this->getCommandBus()->handle($command);
                $this->addFlash('success', $this->translate('Tipo de envío guardado correctamente.'));

                return $this->redirectToRoute('admin_rj_multicarrier_type_shipment_index', [
                    'company' => $currentCompanyId,
                ]);
            } catch (TypeShipmentException $exception) {
                $this->addFlash('error', $exception->getMessage());
            }
        }

        return $this->render('@Modules/rj_multicarrier/views/templates/admin/type_shipment/index.html.twig', [
            'form' => $form->createView(),
            'typeShipmentGrid' => $this->presentGrid($grid),
            'layoutTitle' => $this->translate('Shipment types'),
            'companies' => $this->buildCompanyView($companies, $companyId),
            'editingTypeShipment' => $editingTypeShipment,
            'currentCompanyId' => $companyId,
        ]);
    }

    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function viewAction(int $id): JsonResponse
    {
        /** @var TypeShipmentView|null $typeShipment */
        $typeShipment = $this->getQueryBus()->handle(new GetTypeShipmentForView($id));

        if (null === $typeShipment) {
            return $this->json([
                'message' => $this->translate('El tipo de envío solicitado ya no existe.'),
            ], Response::HTTP_NOT_FOUND);
        }

        return $this->json($this->formatTypeShipmentView($typeShipment));
    }

    public function deleteAction(Request $request, int $id): RedirectResponse
    {
        $this->validateCsrfToken('delete_type_shipment_' . $id, (string) $request->request->get('_token'));

        try {
            $this->getCommandBus()->handle(new DeleteTypeShipmentCommand($id));
            $this->addFlash('success', $this->translate('Tipo de envío eliminado correctamente.'));
        } catch (TypeShipmentException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        $companyId = (int) $request->get('company', 0);

        return $this->redirectToRoute('admin_rj_multicarrier_type_shipment_index', [
            'company' => $companyId,
        ]);
    }

    public function toggleAction(Request $request, int $id): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('toggle_type_shipment_' . $id, (string) $request->request->get('_token'))) {
            $this->addFlash('error', $this->translate('Token CSRF inválido.'));

            return $this->redirectToRoute('admin_rj_multicarrier_type_shipment_index', [
                'company' => (int) $request->get('company', 0),
            ]);
        }

        try {
            /** @var TypeShipment $typeShipment */
            $typeShipment = $this->getCommandBus()->handle(new ToggleTypeShipmentStatusCommand($id));
            $message = $typeShipment->isActive()
                ? $this->translate('Tipo de envío activado.')
                : $this->translate('Tipo de envío desactivado.');
            $this->addFlash('success', $message);
        } catch (TypeShipmentException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('admin_rj_multicarrier_type_shipment_index', [
            'company' => (int) $request->get('company', 0),
        ]);
    }

    /**
     * @AdminSecurity("is_granted('delete', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function deleteBulkAction(Request $request): RedirectResponse
    {
        $typeShipmentIds = $this->getBulkTypeShipmentIds($request);
        $companyId = $this->resolveCompanyIdForRedirect($request);

        if (empty($typeShipmentIds)) {
            $this->addFlash('warning', $this->translate('No se seleccionaron registros para eliminar.'));

            return $this->redirectToRoute('admin_rj_multicarrier_type_shipment_index', [
                'company' => $companyId,
            ]);
        }

        $deleted = 0;
        $failed = 0;

        foreach ($typeShipmentIds as $id) {
            try {
                $this->getCommandBus()->handle(new DeleteTypeShipmentCommand($id));
                ++$deleted;
            } catch (TypeShipmentException $exception) {
                ++$failed;
            }
        }

        if ($deleted > 0) {
            $this->addFlash('success', $this->translate('%count% registros eliminados.', ['%count%' => $deleted]));
        }

        if ($failed > 0) {
            $this->addFlash('warning', $this->translate('%count% registros no se pudieron eliminar.', ['%count%' => $failed]));
        }

        return $this->redirectToRoute('admin_rj_multicarrier_type_shipment_index', [
            'company' => $companyId,
        ]);
    }

    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function exportCsvAction(Request $request): Response
    {
        $companyId = $this->resolveCompanyIdForRedirect($request);
        $filters = $this->buildFilters($request, $companyId);
        $filters->set('limit', 0);
        $filters->set('offset', 0);

        $rows = $this->fetchTypeShipments($filters);

        if (empty($rows)) {
            $this->addFlash('warning', $this->translate('No se encontraron registros para exportar.'));

            return $this->redirectToRoute('admin_rj_multicarrier_type_shipment_index', [
                'company' => $companyId,
            ]);
        }

        $fileName = sprintf('rj_multicarrier_type_shipments_%s.csv', date('Ymd_His'));

        return $this->createTypeShipmentCsvResponse($rows, $fileName);
    }

    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function exportSelectedCsvAction(Request $request): Response
    {
        $typeShipmentIds = $this->getBulkTypeShipmentIds($request);
        $companyId = $this->resolveCompanyIdForRedirect($request);

        if (empty($typeShipmentIds)) {
            $this->addFlash('warning', $this->translate('Selecciona al menos un registro para exportar.'));

            return $this->redirectToRoute('admin_rj_multicarrier_type_shipment_index', [
                'company' => $companyId,
            ]);
        }

        $filters = TypeShipmentFilters::buildDefaults();
        $filters->setNeedsToBePersisted(false);
        $filters->set('limit', 0);
        $filters->set('offset', 0);

        if ($companyId > 0) {
            $filters->addFilter([
                'carrier_id' => $companyId,
            ]);
        }

        $rows = $this->fetchTypeShipments($filters, $typeShipmentIds);

        if (empty($rows)) {
            $this->addFlash('warning', $this->translate('No se encontraron registros para exportar.'));

            return $this->redirectToRoute('admin_rj_multicarrier_type_shipment_index', [
                'company' => $companyId,
            ]);
        }

        $fileName = sprintf('rj_multicarrier_type_shipments_seleccion_%s.csv', date('Ymd_His'));

        return $this->createTypeShipmentCsvResponse($rows, $fileName);
    }

    /**
     * @return array<string, mixed>
     */
    private function formatTypeShipmentView(TypeShipmentView $view): array
    {
        $data = $view->toArray();

        return [
            'id' => $data['id'],
            'companyId' => $data['companyId'],
            'companyName' => $data['companyName'],
            'companyShortName' => $data['companyShortName'],
            'name' => $data['name'],
            'businessCode' => $data['businessCode'],
            'referenceCarrierId' => $data['referenceCarrierId'],
            'active' => (bool) $data['active'],
            'createdAt' => $data['createdAt'],
            'updatedAt' => $data['updatedAt'],
        ];
    }

    /**
     * @param Carrier[] $companies
     */
    private function resolveCompany(array $companies, int $companyId): ?Carrier
    {
        if (empty($companies)) {
            return null;
        }

        if ($companyId > 0) {
            foreach ($companies as $company) {
                if ($company->getId() === $companyId) {
                    return $company;
                }
            }
        }

        return $companies[0];
    }

    private function buildFormData(?Carrier $company, ?TypeShipment $typeShipment): array
    {
        return [
            'id' => $typeShipment?->getId(),
            'carrier_id' => $typeShipment?->getCarrier()->getId() ?? $company?->getId(),
            'name' => $typeShipment?->getName(),
            'business_code' => $typeShipment?->getBusinessCode(),
            'reference_carrier_id' => $typeShipment?->getReferenceCarrierId(),
            'active' => $typeShipment?->isActive() ?? true,
        ];
    }

    /**
     * @param Carrier[] $companies
     */
    private function buildCompanyChoices(array $companies, ?int $currentCompanyId): array
    {
        $choices = [];
        foreach ($companies as $company) {
            if (!$company instanceof Carrier || null === $company->getId()) {
                continue;
            }

            $choices[$company->getName()] = $company->getId();
        }

        if (null !== $currentCompanyId) {
            $filtered = array_filter($choices, static fn (int $id): bool => $id === $currentCompanyId);
            if (!empty($filtered)) {
                return $filtered;
            }
        }

        ksort($choices, SORT_NATURAL | SORT_FLAG_CASE);

        return $choices;
    }

    /**
     * @return array<string, int>
     */
    private function buildCarrierChoices(?int $currentReferenceCarrierId): array
    {
        $languageId = 1;
        if (class_exists('\\Context')) {
            $contextClass = '\\Context';
            $context = $contextClass::getContext();
            $language = $context->language ?? null;
            if ($language && isset($language->id)) {
                $languageId = (int) $language->id;
            }
        }

        $carriers = (array) call_user_func(['Carrier', 'getCarriers'], $languageId);
        $choices = [];

        $assignedReferences = $this->typeShipmentRepository->findAllActiveReferenceCarrierIds();
        $assignedReferences = array_flip($assignedReferences);

        foreach ($carriers as $carrier) {
            if (!isset($carrier['id_reference'])) {
                continue;
            }

            $referenceId = (int) $carrier['id_reference'];

            if ($referenceId !== $currentReferenceCarrierId && isset($assignedReferences[$referenceId])) {
                continue;
            }

            $carrierName = isset($carrier['name']) ? (string) $carrier['name'] : sprintf('Carrier #%d', $referenceId);
            $choices[$carrierName] = $referenceId;
        }

        ksort($choices, SORT_NATURAL | SORT_FLAG_CASE);

        if (null !== $currentReferenceCarrierId && !in_array($currentReferenceCarrierId, $choices, true)) {
            $fallbackName = $this->resolveCarrierNameByReference($currentReferenceCarrierId);
            if (null !== $fallbackName) {
                $choices[$fallbackName] = $currentReferenceCarrierId;
            }
        }

        return $choices;
    }

    private function resolveCarrierNameByReference(int $referenceId): ?string
    {
        $carrier = call_user_func(['Carrier', 'getCarrierByReference'], $referenceId);

        if (is_array($carrier) && isset($carrier['name'])) {
            return (string) $carrier['name'];
        }

        return null;
    }

    private function buildFilters(Request $request, int $companyId): TypeShipmentFilters
    {
        $defaults = TypeShipmentFilters::getDefaults();

        $scopedParameters = $this->extractScopedParameters($request, TypeShipmentGridDefinitionFactory::GRID_ID);

        $filterValues = array_merge($defaults, [
            'limit' => $this->getIntParam($request, $scopedParameters, 'limit', $defaults['limit']),
            'offset' => $this->getIntParam($request, $scopedParameters, 'offset', $defaults['offset']),
            'orderBy' => $this->getStringParam($request, $scopedParameters, 'orderBy', (string) $defaults['orderBy']),
            'sortOrder' => $this->getStringParam($request, $scopedParameters, 'sortOrder', (string) $defaults['sortOrder']),
            'filters' => $this->getArrayParam($request, $scopedParameters, 'filters', $defaults['filters']),
        ]);

        if ($companyId > 0) {
            $filterValues['filters']['carrier_id'] = $companyId;
        }

        $filters = new TypeShipmentFilters($filterValues);
        $filters->setNeedsToBePersisted(false);

        return $filters;
    }

    /**
     * @param Carrier[] $companies
     */
    private function buildCompanyView(array $companies, int $currentCompanyId): array
    {
        $view = [];
        foreach ($companies as $company) {
            if (!$company instanceof Carrier || null === $company->getId()) {
                continue;
            }

            $view[] = [
                'id' => $company->getId(),
                'name' => $company->getName(),
                'short_name' => $company->getShortName(),
                'selected' => $company->getId() === $currentCompanyId,
            ];
        }

        return $view;
    }

    /**
     * @param Carrier[] $carriers
     *
     * @return Carrier[]
     */
    private function filterCarriersByContext(array $carriers): array
    {
        $shopIds = $this->getContextShopIds();

        if (empty($shopIds)) {
            return array_values($carriers);
        }

        $filtered = array_filter($carriers, static function (Carrier $carrier) use ($shopIds): bool {
            if (!$carrier instanceof Carrier || null === $carrier->getId()) {
                return false;
            }

            $carrierShopIds = $carrier->getShopIds();
            if (empty($carrierShopIds)) {
                return true;
            }

            return count(array_intersect($shopIds, $carrierShopIds)) > 0;
        });

        return array_values($filtered);
    }

    /**
     * @return int[]
     */
    private function getContextShopIds(): array
    {
        if (!Shop::isFeatureActive()) {
            return $this->normalizeShopIds($this->resolveShopId());
        }

        $shopIds = Shop::getContextListShopID();

        if (empty($shopIds)) {
            $contextShopId = Shop::getContextShopID(true);
            if (null !== $contextShopId) {
                $shopIds = [$contextShopId];
            }
        }

        return $this->normalizeShopIds($shopIds);
    }

    private function resolveShopId(): int
    {
        $shopId = Shop::getContextShopID(true);

        return null !== $shopId ? (int) $shopId : 0;
    }

    /**
     * @param mixed $value
     *
     * @return int[]
     */
    private function normalizeShopIds($value): array
    {
        if (null === $value) {
            return [];
        }

        if (!is_array($value)) {
            $value = [$value];
        }

        $ids = array_map('intval', $value);
        $ids = array_filter($ids, static fn (int $id): bool => $id > 0);

        return array_values(array_unique($ids));
    }

    private function createTypeShipmentCsvResponse(array $rows, string $fileName): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($rows): void {
            $handle = fopen('php://output', 'wb');

            fputcsv($handle, [
                'ID',
                'Carrier',
                'Name',
                'Business code',
                'Reference carrier',
                'Active',
            ]);

            foreach ($rows as $row) {
                $active = (isset($row['active']) && (int) $row['active'] === 1) ? '1' : '0';

                fputcsv($handle, [
                    $row['id_type_shipment'] ?? '',
                    $row['company_name'] ?? '',
                    $row['name'] ?? '',
                    $row['id_bc'] ?? '',
                    $row['id_reference_carrier'] ?? '',
                    $active,
                ]);
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

    private function fetchTypeShipments(TypeShipmentFilters $filters, ?array $ids = null): array
    {
        $qb = $this->typeShipmentQueryBuilder->getSearchQueryBuilder($filters);

        if (!empty($ids)) {
            $qb->andWhere('ts.id_type_shipment IN (:ids)')
                ->setParameter('ids', $ids, \Doctrine\DBAL\Connection::PARAM_INT_ARRAY);
        }

        return $this->fetchAllAssociative($qb);
    }

    private function fetchAllAssociative(\Doctrine\DBAL\Query\QueryBuilder $qb): array
    {
        $statement = $qb->execute();

        if (is_object($statement) && method_exists($statement, 'fetchAllAssociative')) {
            return $statement->fetchAllAssociative();
        }

        if (is_object($statement) && method_exists($statement, 'fetchAll')) {
            return $statement->fetchAll(\PDO::FETCH_ASSOC);
        }

        return [];
    }

    /**
     * @return array<int>
     */
    private function getBulkTypeShipmentIds(Request $request): array
    {
        $gridId = TypeShipmentGridDefinitionFactory::GRID_ID;
        $columnName = $gridId . '_type_shipment_bulk';

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

    private function resolveCompanyIdForRedirect(Request $request): int
    {
        $companyId = (int) $request->get('company', 0);

        $scoped = $this->extractScopedParameters($request, TypeShipmentGridDefinitionFactory::GRID_ID);
        if (isset($scoped['filters']['carrier_id'])) {
            $companyId = (int) $scoped['filters']['carrier_id'];
        }

        return $companyId;
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
}
