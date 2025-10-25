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
use Roanja\Module\RjMulticarrier\Domain\TypeShipment\Query\GetTypeShipmentFormOptions;
use Roanja\Module\RjMulticarrier\Domain\TypeShipment\Query\GetTypeShipmentForView;
use Roanja\Module\RjMulticarrier\Domain\TypeShipment\View\TypeShipmentFormOptionsView;
use Roanja\Module\RjMulticarrier\Domain\TypeShipment\View\TypeShipmentView;
use Roanja\Module\RjMulticarrier\Entity\Carrier;
use Roanja\Module\RjMulticarrier\Entity\TypeShipment;
use Roanja\Module\RjMulticarrier\Form\TypeShipmentType;
use Roanja\Module\RjMulticarrier\Grid\TypeShipment\TypeShipmentFilters;
use Roanja\Module\RjMulticarrier\Grid\TypeShipment\TypeShipmentGridDefinitionFactory;
use Roanja\Module\RjMulticarrier\Grid\TypeShipment\TypeShipmentGridFactory;
use Roanja\Module\RjMulticarrier\Grid\TypeShipment\TypeShipmentQueryBuilder;
use Roanja\Module\RjMulticarrier\Form\TypeShipment\TypeShipmentFormOptionsProvider;
use Roanja\Module\RjMulticarrier\Repository\TypeShipmentRepository;
use League\Tactician\Exception\MissingHandlerException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;

final class TypeShipmentController extends FrameworkBundleAdminController
{
    private const TRANSLATION_DOMAIN = 'Modules.RjMulticarrier.Admin';

    public function __construct(
        private readonly TypeShipmentGridFactory $gridFactory,
        private readonly TypeShipmentQueryBuilder $typeShipmentQueryBuilder,
        private readonly TypeShipmentFormOptionsProvider $formOptionsProvider,
        private readonly TypeShipmentRepository $typeShipmentRepository
    ) {
    }

    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function indexAction(Request $request, TypeShipmentFilters $filters): Response
    {
        // Resolve company and editing type shipment ids from query when needed for the form part
        $companyFromQuery = $request->query->getInt('company', 0);

        $filters->setNeedsToBePersisted(false);
        $currentFilters = $filters->getFilters();
        $companyFromFilters = isset($currentFilters['carrier_id']) ? (int) $currentFilters['carrier_id'] : 0;

        $companies = $this->formOptionsProvider->getCompaniesForContext();

        if (empty($companies)) {
            $this->addFlash('warning', $this->l('No hay transportistas disponibles en el contexto actual.'));

            return $this->redirectToRoute('admin_rj_multicarrier_carriers_index');
        }

        $activeCompanyId = $companyFromQuery > 0 ? $companyFromQuery : $companyFromFilters;
        $company = $this->resolveCompany($companies, $activeCompanyId);
        $companyId = $company?->getId() ?? 0;

        if ($companyId > 0) {
            $filters->addFilter([
                'carrier_id' => $companyId,
            ]);
        }

        $grid = $this->gridFactory->getGrid($filters);

        return $this->render('@Modules/rj_multicarrier/views/templates/admin/type_shipment/index.html.twig', [
            'typeShipmentGrid' => $this->presentGrid($grid),
            'layoutTitle' => $this->l('Shipment types'),
            'companies' => $this->buildCompanyView($companies, $companyId),
            'currentCompanyId' => $companyId,
        ]);
    }

    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function viewAction(int $id): JsonResponse
    {
        $typeShipmentView = $this->getTypeShipmentView($id);

        if (null === $typeShipmentView) {
            return $this->json([
                'message' => $this->l('El tipo de envío solicitado ya no existe.'),
            ], Response::HTTP_NOT_FOUND);
        }

        $payload = $this->formatTypeShipmentView($typeShipmentView);

        $formOptionsView = $this->getTypeShipmentFormOptions($id);
        $payload['formOptions'] = $formOptionsView instanceof TypeShipmentFormOptionsView
            ? $formOptionsView->toArray()
            : ['companies' => [], 'referenceCarriers' => []];

        return $this->json($payload);
    }

    /**
     * @AdminSecurity("is_granted('create', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function createAction(Request $request): Response
    {
        $companies = $this->formOptionsProvider->getCompaniesForContext();

        if (empty($companies)) {
            $this->addFlash('warning', $this->l('No hay transportistas disponibles en el contexto actual.'));

            return $this->redirectToRoute('admin_rj_multicarrier_type_shipment_index');
        }

        $requestedCompanyId = $request->query->getInt('company', 0);
        $company = $this->resolveCompany($companies, $requestedCompanyId);
        $companyId = $company?->getId() ?? 0;

        $createOptions = $this->getTypeShipmentFormOptions(null);
        $optionsPayload = $createOptions instanceof TypeShipmentFormOptionsView
            ? $createOptions->toArray()
            : [
                'companies' => $this->formOptionsProvider->buildCompanyChoices($companies, null, false),
                'referenceCarriers' => $this->formOptionsProvider->buildReferenceCarrierChoices(null),
            ];

        $companyChoices = $optionsPayload['companies'];
        $referenceChoices = $optionsPayload['referenceCarriers'];

        $formData = $this->buildNewFormData($companyId);

        $form = $this->createForm(TypeShipmentType::class, $formData, [
            'company_choices' => $companyChoices,
            'carrier_choices' => $referenceChoices,
            'current_carrier_id' => $companyId,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $currentCompanyId = (int) ($data['carrier_id'] ?? 0);
            $referenceCarrierId = $this->normalizeReferenceCarrierId($data['reference_carrier_id'] ?? null);

            $command = new UpsertTypeShipmentCommand(
                null,
                $currentCompanyId,
                (string) $data['name'],
                (string) $data['business_code'],
                $referenceCarrierId,
                (bool) $data['active']
            );

            try {
                $this->getCommandBus()->handle($command);
                $this->addFlash('success', $this->l('Tipo de envío guardado correctamente.'));

                return $this->redirectToRoute('admin_rj_multicarrier_type_shipment_index', [
                    'company' => $currentCompanyId,
                ]);
            } catch (TypeShipmentException $exception) {
                $this->addFlash('error', $exception->getMessage());
            }
        }

        return $this->render('@Modules/rj_multicarrier/views/templates/admin/type_shipment/form.html.twig', [
            'form' => $form->createView(),
            'isEdit' => false,
            'actionParams' => $this->buildActionParams(null, $request, $companyId),
            'returnParams' => $this->buildReturnParams($companyId),
        ]);
    }

    /**
     * @AdminSecurity("is_granted('update', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function editAction(Request $request, int $id): Response
    {
        $typeShipmentView = $this->getTypeShipmentView($id);

        if (null === $typeShipmentView) {
            $this->addFlash('error', $this->l('El tipo de envío solicitado ya no existe.'));

            return $this->redirectToRoute('admin_rj_multicarrier_type_shipment_index');
        }

        $formOptionsView = $this->getTypeShipmentFormOptions($id);
        $optionsPayload = $formOptionsView instanceof TypeShipmentFormOptionsView ? $formOptionsView->toArray() : [
            'companies' => [],
            'referenceCarriers' => [],
        ];

        if (empty($optionsPayload['companies'])) {
            $this->addFlash('error', $this->l('No hay transportistas disponibles en el contexto actual.'));

            return $this->redirectToRoute('admin_rj_multicarrier_type_shipment_index');
        }

        $formData = $this->buildFormDataFromView($typeShipmentView);

        $form = $this->createForm(TypeShipmentType::class, $formData, [
            'company_choices' => $optionsPayload['companies'],
            'carrier_choices' => $optionsPayload['referenceCarriers'],
            'current_carrier_id' => $formData['carrier_id'],
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $currentCompanyId = (int) ($data['carrier_id'] ?? 0);
            $referenceCarrierId = $this->normalizeReferenceCarrierId($data['reference_carrier_id'] ?? null);

            $command = new UpsertTypeShipmentCommand(
                $formData['id'] ?? $id,
                $currentCompanyId,
                (string) $data['name'],
                (string) $data['business_code'],
                $referenceCarrierId,
                (bool) $data['active']
            );

            try {
                $this->getCommandBus()->handle($command);
                $this->addFlash('success', $this->l('Tipo de envío actualizado correctamente.'));

                return $this->redirectToRoute('admin_rj_multicarrier_type_shipment_index', [
                    'company' => $currentCompanyId,
                ]);
            } catch (TypeShipmentException $exception) {
                $this->addFlash('error', $exception->getMessage());
            }
        }

        return $this->render('@Modules/rj_multicarrier/views/templates/admin/type_shipment/form.html.twig', [
            'form' => $form->createView(),
            'isEdit' => true,
            'actionParams' => $this->buildActionParams($id, $request, $formData['carrier_id']),
            'returnParams' => $this->buildReturnParams($formData['carrier_id']),
        ]);
    }

    /**
     * @AdminSecurity("is_granted('delete', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function deleteAction(Request $request, int $id): RedirectResponse
    {
        try {
            $this->getCommandBus()->handle(new DeleteTypeShipmentCommand($id));
            $this->addFlash('success', $this->l('Tipo de envío eliminado correctamente.'));
        } catch (TypeShipmentException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        $companyId = (int) $request->get('company', 0);

        return $this->redirectToRoute('admin_rj_multicarrier_type_shipment_index', [
            'company' => $companyId,
        ]);
    }

    /**
     * @AdminSecurity("is_granted('update', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function toggleAction(Request $request, int $id): RedirectResponse
    {
        try {
            /** @var TypeShipment $typeShipment */
            $typeShipment = $this->getCommandBus()->handle(new ToggleTypeShipmentStatusCommand($id));
            $message = $typeShipment->isActive()
                ? $this->l('Tipo de envío activado.')
                : $this->l('Tipo de envío desactivado.');
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
            $this->addFlash('warning', $this->l('No se seleccionaron registros para eliminar.'));

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
            $this->addFlash('success', $this->l('%count% registros eliminados.', ['%count%' => $deleted]));
        }

        if ($failed > 0) {
            $this->addFlash('warning', $this->l('%count% registros no se pudieron eliminar.', ['%count%' => $failed]));
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
            $this->addFlash('warning', $this->l('No se encontraron registros para exportar.'));

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
            $this->addFlash('warning', $this->l('Selecciona al menos un registro para exportar.'));

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
            $this->addFlash('warning', $this->l('No se encontraron registros para exportar.'));

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

    private function buildFormDataFromView(TypeShipmentView $view): array
    {
        $data = $view->toArray();

        return [
            'id' => $data['id'],
            'carrier_id' => $data['companyId'],
            'name' => $data['name'],
            'business_code' => $data['businessCode'],
            'reference_carrier_id' => $data['referenceCarrierId'],
            'active' => (bool) $data['active'],
        ];
    }

    private function buildNewFormData(int $carrierId): array
    {
        return [
            'id' => null,
            'carrier_id' => $carrierId,
            'name' => '',
            'business_code' => '',
            'reference_carrier_id' => null,
            'active' => true,
        ];
    }

    /**
     * @return array<string, int|string>
     */
    private function buildReturnParams(?int $companyId): array
    {
        $params = [];

        if (null !== $companyId && $companyId > 0) {
            $params['company'] = $companyId;
        }

        return $params;
    }

    /**
     * @return array<string, int|string>
     */
    private function buildActionParams(?int $id, Request $request, ?int $defaultCompanyId = null): array
    {
        if (null !== $id) {
            $params['id'] = $id;
        }

        $company = $request->query->getInt('company', 0);
        if (0 === $company && $request->request->has('company')) {
            $company = (int) $request->request->get('company');
        }
        if (0 === $company && null !== $defaultCompanyId) {
            $company = $defaultCompanyId;
        }
        if ($company > 0) {
            $params['company'] = $company;
        }

        return $params;
    }

    private function getTypeShipmentView(int $id): ?TypeShipmentView
    {
        try {
            /** @var TypeShipmentView|null $view */
            $view = $this->getQueryBus()->handle(new GetTypeShipmentForView($id));
            if ($view instanceof TypeShipmentView) {
                return $view;
            }
        } catch (MissingHandlerException) {
            // fall back to repository below
        }

        $typeShipment = $this->typeShipmentRepository->findOneById($id);

        return $typeShipment instanceof TypeShipment ? TypeShipmentView::fromEntity($typeShipment) : null;
    }

    private function getTypeShipmentFormOptions(?int $id): ?TypeShipmentFormOptionsView
    {
        try {
            /** @var TypeShipmentFormOptionsView|null $options */
            $options = $this->getQueryBus()->handle(new GetTypeShipmentFormOptions($id));
            if ($options instanceof TypeShipmentFormOptionsView) {
                return $options;
            }
        } catch (MissingHandlerException) {
            // fall back to manual provider below
        }

        $referenceCarrierId = null;
        if (null !== $id) {
            $typeShipment = $this->typeShipmentRepository->findOneById($id);
            if ($typeShipment instanceof TypeShipment) {
                $referenceCarrierId = $typeShipment->getReferenceCarrierId();
            }
        }

        $companies = $this->formOptionsProvider->getCompaniesForContext();
        $companyChoices = $this->formOptionsProvider->buildCompanyChoices($companies, null, false);
        $referenceChoices = $this->formOptionsProvider->buildReferenceCarrierChoices($referenceCarrierId);

        return new TypeShipmentFormOptionsView($companyChoices, $referenceChoices);
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

    private function l(string $message, array $parameters = []): string
    {
        return $this->trans($message, self::TRANSLATION_DOMAIN, $parameters);
    }

    /**
     * @return array<string, string[]>
     */
    private function getFormErrors(FormInterface $form): array
    {
        $errors = [];

        foreach ($form->getErrors(true) as $formError) {
            $origin = $formError->getOrigin();
            $fieldName = $origin instanceof FormInterface && $origin->getName() !== ''
                ? $origin->getName()
                : 'form';

            $errors[$fieldName][] = $formError->getMessage();
        }

        return $errors;
    }

    private function normalizeReferenceCarrierId($value): ?int
    {
        if (null === $value || '' === $value) {
            return null;
        }

        $normalized = (int) $value;

        return $normalized > 0 ? $normalized : null;
    }
}
