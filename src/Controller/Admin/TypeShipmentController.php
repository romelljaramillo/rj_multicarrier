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
use Roanja\Module\RjMulticarrier\Entity\Company;
use Roanja\Module\RjMulticarrier\Entity\TypeShipment;
use Roanja\Module\RjMulticarrier\Form\TypeShipmentType;
use Roanja\Module\RjMulticarrier\Grid\TypeShipment\TypeShipmentFilters;
use Roanja\Module\RjMulticarrier\Grid\TypeShipment\TypeShipmentGridDefinitionFactory;
use Roanja\Module\RjMulticarrier\Grid\TypeShipment\TypeShipmentGridFactory;
use Roanja\Module\RjMulticarrier\Repository\CompanyRepository;
use Roanja\Module\RjMulticarrier\Repository\TypeShipmentRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class TypeShipmentController extends FrameworkBundleAdminController
{
    private const TRANSLATION_DOMAIN = 'Modules.RjMulticarrier.Admin';

    public function __construct(
        private readonly CompanyRepository $companyRepository,
        private readonly TypeShipmentRepository $typeShipmentRepository,
        private readonly TypeShipmentGridFactory $gridFactory
    ) {}

    public function indexAction(Request $request): Response
    {
        $companyId = $request->query->getInt('company', 0);
        $typeShipmentId = $request->query->getInt('id', 0);

        $companies = $this->companyRepository->findAllOrdered();
        $company = $this->resolveCompany($companies, $companyId);
        $companyId = $company?->getId() ?? 0;

        $editingTypeShipment = $typeShipmentId > 0
            ? $this->typeShipmentRepository->findOneById($typeShipmentId)
            : null;

        if ($editingTypeShipment instanceof TypeShipment) {
            $company = $editingTypeShipment->getCompany();
            $companyId = $company->getId() ?? 0;
        }

        $filters = $this->buildFilters($request, $companyId);
        $grid = $this->gridFactory->getGrid($filters);

        $formData = $this->buildFormData($company, $editingTypeShipment);
        $companyChoices = $this->buildCompanyChoices($companies);
        $carrierChoices = $this->buildCarrierChoices($editingTypeShipment?->getReferenceCarrierId());

        $form = $this->createForm(TypeShipmentType::class, $formData, [
            'company_choices' => $companyChoices,
            'carrier_choices' => $carrierChoices,
        ]);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $currentCompanyId = (int) ($data['company_id'] ?? 0);
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
        // $this->validateCsrfToken('delete_type_shipment_' . $id, (string) $request->get('_token'));

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
        // $this->validateCsrfToken('toggle_type_shipment_' . $id, (string) $request->get('_token'));

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
     * @param Company[] $companies
     */
    private function resolveCompany(array $companies, int $companyId): ?Company
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

    private function buildFormData(?Company $company, ?TypeShipment $typeShipment): array
    {
        return [
            'id' => $typeShipment?->getId(),
            'company_id' => $typeShipment?->getCompany()->getId() ?? $company?->getId(),
            'name' => $typeShipment?->getName(),
            'business_code' => $typeShipment?->getBusinessCode(),
            'reference_carrier_id' => $typeShipment?->getReferenceCarrierId(),
            'active' => $typeShipment?->isActive() ?? true,
        ];
    }

    /**
     * @param Company[] $companies
     */
    private function buildCompanyChoices(array $companies): array
    {
        $choices = [];
        foreach ($companies as $company) {
            if (!$company instanceof Company || null === $company->getId()) {
                continue;
            }

            $choices[$company->getName()] = $company->getId();
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
            $filterValues['filters']['company_id'] = $companyId;
        }

        $filters = new TypeShipmentFilters($filterValues);
        $filters->setNeedsToBePersisted(false);

        return $filters;
    }

    /**
     * @param Company[] $companies
     */
    private function buildCompanyView(array $companies, int $currentCompanyId): array
    {
        $view = [];
        foreach ($companies as $company) {
            if (!$company instanceof Company || null === $company->getId()) {
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
        return $this->trans($message, self::TRANSLATION_DOMAIN, $parameters);
    }
}
