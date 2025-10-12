<?php
/**
 * CRUD controller for Configuration entities.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Controller\Admin;

use Doctrine\DBAL\Query\QueryBuilder;
use Context;
use Module;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Security\Annotation\AdminSecurity;
use Roanja\Module\RjMulticarrier\Domain\Configuration\Command\CreateConfigurationCommand;
use Roanja\Module\RjMulticarrier\Domain\Configuration\Command\DeleteConfigurationCommand;
use Roanja\Module\RjMulticarrier\Domain\Configuration\Command\ToggleConfigurationStatusCommand;
use Roanja\Module\RjMulticarrier\Domain\Configuration\Command\UpdateConfigurationCommand;
use Roanja\Module\RjMulticarrier\Domain\Configuration\Exception\ConfigurationNotFoundException;
use Roanja\Module\RjMulticarrier\Domain\Configuration\Exception\InvalidConfigurationDataException;
use Roanja\Module\RjMulticarrier\Domain\Configuration\Query\GetConfigurationForContext;
use Roanja\Module\RjMulticarrier\Domain\Configuration\Query\GetConfigurationForEdit;
use Roanja\Module\RjMulticarrier\Domain\Configuration\View\ConfigurationDetailView;
use Roanja\Module\RjMulticarrier\Domain\Configuration\View\ConfigurationView;
use Roanja\Module\RjMulticarrier\Form\ConfigurationType;
use Roanja\Module\RjMulticarrier\Grid\Configuration\ConfigurationFilters;
use Roanja\Module\RjMulticarrier\Grid\Configuration\ConfigurationGridDefinitionFactory;
use Roanja\Module\RjMulticarrier\Grid\Configuration\ConfigurationGridFactory;
use Roanja\Module\RjMulticarrier\Grid\Configuration\ConfigurationQueryBuilder;
use Shop;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Throwable;

class ConfigurationController extends FrameworkBundleAdminController
{
    private const TRANSLATION_DOMAIN = 'Modules.RjMulticarrier.Admin';

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly ConfigurationGridFactory $configurationGridFactory
    ) {
    }

    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function indexAction(Request $request, ConfigurationFilters $filters): Response
    {
        $filters->setNeedsToBePersisted(false);
        $grid = $this->configurationGridFactory->getGrid($filters);

        return $this->render('@Modules/rj_multicarrier/views/templates/admin/configuration_shop/index.html.twig', [
            'ConfigurationGrid' => $this->presentGrid($grid),
            'createUrl' => $this->generateUrl('admin_rj_multicarrier_configuration_shop_create'),
        ]);
    }

    /**
     * @AdminSecurity("is_granted('create', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function createAction(Request $request): Response
    {
        /** @var ConfigurationView $contextView */
        $contextView = $this->getQueryBus()->handle(new GetConfigurationForContext());
        $shopId = $contextView->getShopId();

        $contextShopIds = $this->getContextShopIds();
        $command = CreateConfigurationCommand::fromContext($contextView, $contextShopIds);
        $moduleChoices = $this->getCashOnDeliveryModuleChoices($command->RJ_MODULE_CONTRAREEMBOLSO);

        $form = $this->createForm(ConfigurationType::class, $command, [
            'country_choices' => $this->getCountryChoices(),
            'cod_module_choices' => $moduleChoices,
            'is_multistore_active' => Shop::isFeatureActive(),
            'data_class' => CreateConfigurationCommand::class,
            'action' => $this->generateUrl('admin_rj_multicarrier_configuration_shop_create'),
            'method' => Request::METHOD_POST,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                /** @var CreateConfigurationCommand $submittedCommand */
                $submittedCommand = $form->getData();
                $submittedCommand->ensureShopAssociation($shopId, $contextShopIds);

                $this->getCommandBus()->handle($submittedCommand);

                $this->addFlash('success', $this->l('Remitente creado correctamente.'));

                return $this->redirectToRoute('admin_rj_multicarrier_configuration_shop_index');
            } catch (InvalidConfigurationDataException $exception) {
                $this->attachViolationsToForm($form, $exception);
            } catch (Throwable $exception) {
                $this->addFlash('error', $this->l('No se pudo guardar el remitente.'));
            }
        }

        return $this->render('@Modules/rj_multicarrier/views/templates/admin/configuration_shop/form.html.twig', [
            'form' => $form->createView(),
            'action' => 'create',
            'Configuration' => null,
        ]);
    }

    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function editAction(Request $request, int $id): Response
    {
        try {
            /** @var ConfigurationDetailView $detail */
            $detail = $this->getQueryBus()->handle(new GetConfigurationForEdit($id));
        } catch (ConfigurationNotFoundException $exception) {
            $this->addFlash('error', $this->l('El remitente solicitado no existe.'));
            return $this->redirectToRoute('admin_rj_multicarrier_configuration_shop_index');
        }

        $command = UpdateConfigurationCommand::fromConfiguration($detail);
        $moduleChoices = $this->getCashOnDeliveryModuleChoices($command->RJ_MODULE_CONTRAREEMBOLSO);

        $form = $this->createForm(ConfigurationType::class, $command, [
            'country_choices' => $this->getCountryChoices(),
            'cod_module_choices' => $moduleChoices,
            'is_multistore_active' => Shop::isFeatureActive(),
            'data_class' => UpdateConfigurationCommand::class,
            'action' => $this->generateUrl('admin_rj_multicarrier_configuration_shop_edit', ['id' => $id]),
            'method' => Request::METHOD_POST,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                /** @var UpdateConfigurationCommand $submittedCommand */
                $submittedCommand = $form->getData();

                $this->getCommandBus()->handle($submittedCommand);

                $this->addFlash('success', $this->l('Remitente actualizado correctamente.'));

                return $this->redirectToRoute('admin_rj_multicarrier_configuration_shop_index');
            } catch (InvalidConfigurationDataException $exception) {
                $this->attachViolationsToForm($form, $exception);
            } catch (ConfigurationNotFoundException) {
                $this->addFlash('warning', $this->l('El remitente solicitado no existe.'));

                return $this->redirectToRoute('admin_rj_multicarrier_configuration_shop_index');
            } catch (Throwable $exception) {
                $this->addFlash('error', $this->l('No se pudo guardar el remitente.'));
            }
        }

        return $this->render('@Modules/rj_multicarrier/views/templates/admin/configuration_shop/form.html.twig', [
            'form' => $form->createView(),
            'action' => 'edit',
            'Configuration' => $detail,
        ]);
    }

    /**
     * @AdminSecurity("is_granted('delete', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function deleteAction(Request $request, int $id): RedirectResponse
    {
        try {
            $this->getCommandBus()->handle(new DeleteConfigurationCommand($id));
            $this->addFlash('success', $this->l('Remitente eliminado correctamente.'));
        } catch (ConfigurationNotFoundException $exception) {
            $this->addFlash('warning', $this->l('El remitente ya no existe.'));
        } catch (Throwable $exception) {
            $this->addFlash('error', $this->l('No se pudo eliminar el remitente.'));
        }

        return $this->redirectToRoute('admin_rj_multicarrier_configuration_shop_index');
    }

    /**
     * @AdminSecurity("is_granted('update', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function toggleAction(Request $request, int $id): RedirectResponse
    {
        try {
            /** @var \Roanja\Module\RjMulticarrier\Entity\Configuration $Configuration */
            $Configuration = $this->getCommandBus()->handle(new ToggleConfigurationStatusCommand($id));
            $message = $Configuration->isActive()
                ? $this->l('Remitente activado correctamente.')
                : $this->l('Remitente desactivado correctamente.');
            $this->addFlash('success', $message);
        } catch (ConfigurationNotFoundException) {
            $this->addFlash('warning', $this->l('El remitente solicitado no existe.'));
        } catch (Throwable $exception) {
            $this->addFlash('error', $this->l('No se pudo actualizar el estado del remitente.'));
        }

        return $this->redirectAfterAction($request);
    }

    /**
     * @AdminSecurity("is_granted('delete', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function deleteBulkAction(Request $request): RedirectResponse
    {
        $ConfigurationIds = $this->getBulkConfigurationIds($request);

        if (empty($ConfigurationIds)) {
            $this->addFlash('warning', $this->l('No se seleccionaron remitentes.'));

            return $this->redirectAfterAction($request);
        }

        $deleted = 0;

        foreach ($ConfigurationIds as $ConfigurationId) {
            try {
                $this->getCommandBus()->handle(new DeleteConfigurationCommand($ConfigurationId));
                ++$deleted;
            } catch (ConfigurationNotFoundException) {
                continue;
            } catch (Throwable $exception) {
                $this->addFlash('error', $this->l('No se pudo completar la eliminación masiva.'));

                return $this->redirectAfterAction($request);
            }
        }

        if (0 === $deleted) {
            $this->addFlash('warning', $this->l('No se eliminaron remitentes.'));
        } else {
            $this->addFlash('success', $this->l('%count% remitentes eliminados.', ['%count%' => $deleted]));
        }

        return $this->redirectAfterAction($request);
    }

    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function exportCsvAction(Request $request): Response
    {
        $filters = $this->buildFilters($request);
        $filters->set('limit', 0);
        $filters->set('offset', 0);
        $filters->setNeedsToBePersisted(false);

        $rows = $this->fetchAll(
            $this->getConfigurationQueryBuilder()->getSearchQueryBuilder($filters)
        );

        $fileName = sprintf('rj_multicarrier_configurations_%s.csv', date('Ymd_His'));

        return $this->createCsvResponse($rows, $fileName);
    }

    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function exportSelectedCsvAction(Request $request): Response
    {
        $ConfigurationIds = $this->getBulkConfigurationIds($request);

        if (empty($ConfigurationIds)) {
            $this->addFlash('warning', $this->l('Selecciona al menos un remitente para exportar.'));

            return $this->redirectAfterAction($request);
        }

        $filters = new ConfigurationFilters([
            'limit' => 0,
            'offset' => 0,
            'orderBy' => 'id_configuration',
            'sortOrder' => 'ASC',
            'filters' => [
                'ids' => $ConfigurationIds,
            ],
        ]);
        $filters->setNeedsToBePersisted(false);

        $rows = $this->fetchAll(
            $this->getConfigurationQueryBuilder()->getSearchQueryBuilder($filters)
        );

        if (empty($rows)) {
            $this->addFlash('warning', $this->l('No se encontraron remitentes para exportar.'));

            return $this->redirectAfterAction($request);
        }

        $fileName = sprintf('rj_multicarrier_configurations_seleccion_%s.csv', date('Ymd_His'));

        return $this->createCsvResponse($rows, $fileName);
    }

    private function attachViolationsToForm(FormInterface $form, InvalidConfigurationDataException $exception): void
    {
        $this->addViolationsToForm($form, $exception->getViolations());
        $this->addFlash('error', $this->l('Revisa los datos del remitente antes de guardar.'));
    }

    private function addViolationsToForm(FormInterface $form, ConstraintViolationListInterface $violations): void
    {
        /** @var ConstraintViolationInterface $violation */
        foreach ($violations as $violation) {
            $form->addError(new FormError($violation->getMessage()));
        }
    }

    private function buildFilters(Request $request): ConfigurationFilters
    {
        $defaults = ConfigurationFilters::getDefaults();

        $scopedParameters = $this->extractScopedParameters($request, ConfigurationGridDefinitionFactory::GRID_ID);

        $filterValues = array_merge($defaults, [
            'limit' => $this->getIntParam($request, $scopedParameters, 'limit', (int) $defaults['limit']),
            'offset' => $this->getIntParam($request, $scopedParameters, 'offset', (int) $defaults['offset']),
            'orderBy' => $this->getStringParam($request, $scopedParameters, 'orderBy', (string) $defaults['orderBy']),
            'sortOrder' => $this->getStringParam($request, $scopedParameters, 'sortOrder', (string) $defaults['sortOrder']),
            'filters' => $this->getArrayParam($request, $scopedParameters, 'filters', (array) $defaults['filters']),
        ]);

        $filters = new ConfigurationFilters($filterValues);
        $filters->setNeedsToBePersisted(false);

        return $filters;
    }

    /**
     * @return array<int>
     */
    private function getBulkConfigurationIds(Request $request): array
    {
        $gridId = ConfigurationGridDefinitionFactory::GRID_ID;
        $columnName = $gridId . '_configuration_bulk';

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

        $collected = array_map(static fn ($value): int => (int) $value, $collected);

        return array_values(array_unique($collected));
    }

    private function redirectAfterAction(Request $request): RedirectResponse
    {
        $redirectUrl = $request->query->get('redirectUrl');
        if (is_string($redirectUrl) && '' !== trim($redirectUrl)) {
            return $this->redirect($redirectUrl);
        }

        $redirectUrl = $request->request->get('redirectUrl');
        if (is_string($redirectUrl) && '' !== trim($redirectUrl)) {
            return $this->redirect($redirectUrl);
        }

        return $this->redirectToRoute('admin_rj_multicarrier_configuration_shop_index');
    }

    private function extractScopedParameters(Request $request, string $scope): array
    {
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

    /**
     * @return array<mixed>
     */
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

    private function getCountryChoices(): array
    {
        /** @var \PrestaShop\PrestaShop\Adapter\Country\CountryDataProvider $countryDataProvider */
        $countryDataProvider = $this->container->get('prestashop.adapter.data_provider.country');

        $countries = $countryDataProvider->getCountries($this->getContextLanguageId(), true);

        $choices = [];
        foreach ($countries as $country) {
            if (!isset($country['name'], $country['id_country'])) {
                continue;
            }

            $choices[(string) $country['name']] = (int) $country['id_country'];
        }

        ksort($choices, SORT_STRING | SORT_FLAG_CASE);

        return $choices;
    }

    private function getContextLanguageId(): int
    {
        $context = Context::getContext();

        if (isset($context->language->id)) {
            return (int) $context->language->id;
        }

        return 1;
    }

    private function getCashOnDeliveryModuleChoices(?string $currentModule): array
    {
        $modules = Module::getPaymentModules();
        $choices = [];

        foreach ($modules as $module) {
            $name = isset($module['name']) ? (string) $module['name'] : '';
            if ('' === $name) {
                continue;
            }

            $displayName = isset($module['displayName']) ? (string) $module['displayName'] : $name;
            $choices[$displayName] = $name;
        }

        if (null !== $currentModule && '' !== $currentModule && !in_array($currentModule, $choices, true)) {
            $choices[$currentModule] = $currentModule;
        }

        ksort($choices, SORT_STRING | SORT_FLAG_CASE);

        return $choices;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function createCsvResponse(array $rows, string $fileName): StreamedResponse
    {
        $yes = $this->l('Sí');
        $no = $this->l('No');

        $response = new StreamedResponse(function () use ($rows, $yes, $no): void {
            $handle = fopen('php://output', 'wb');

            fputcsv($handle, [
                'ID',
                'Nombre',
                'Apellidos',
                'Empresa',
                'Teléfono',
                'Email',
                'Activo',
                'Tiendas',
                'Fecha creación',
                'Fecha actualización',
            ]);

            foreach ($rows as $row) {
                $activeValue = isset($row['active']) ? ((int) $row['active'] === 1 ? $yes : $no) : '';

                fputcsv($handle, [
                    $row['id_configuration'] ?? '',
                    $row['firstname'] ?? '',
                    $row['lastname'] ?? '',
                    $row['company'] ?? '',
                    $row['phone'] ?? '',
                    $row['email'] ?? '',
                    $activeValue,
                    $row['shops'] ?? '',
                    $row['date_add'] ?? '',
                    $row['date_upd'] ?? '',
                ]);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set(
            'Content-Disposition',
            $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $fileName)
        );

        return $response;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchAll(QueryBuilder $qb): array
    {
        if (method_exists($qb, 'executeQuery')) {
            $result = $qb->executeQuery();
        } else {
            $result = $qb->execute();
        }

        if (method_exists($result, 'fetchAllAssociative')) {
            return $result->fetchAllAssociative();
        }

        if (method_exists($result, 'fetchAll')) {
            return $result->fetchAll(\PDO::FETCH_ASSOC);
        }

        return [];
    }

    private function getConfigurationQueryBuilder(): ConfigurationQueryBuilder
    {
        /** @var ConfigurationQueryBuilder $queryBuilder */
        $queryBuilder = $this->get('Roanja\Module\RjMulticarrier\Grid\Configuration\ConfigurationQueryBuilder');

        return $queryBuilder;
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
        if (class_exists('\\Context')) {
            $context = call_user_func(['\\Context', 'getContext']);
            if (isset($context->shop->id)) {
                return (int) $context->shop->id;
            }
        }

        return 0;
    }

    private function l(string $message, array $parameters = []): string
    {
        return $this->translator->trans($message, $parameters, self::TRANSLATION_DOMAIN);
    }

}
