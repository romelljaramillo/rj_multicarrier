<?php
/**
 * CRUD controller for InfoShop entities.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Controller\Admin;

use Doctrine\DBAL\Query\QueryBuilder;
use InvalidArgumentException;
use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Security\Annotation\AdminSecurity;
use Roanja\Module\RjMulticarrier\Domain\InfoShop\Command\DeleteInfoShopCommand;
use Roanja\Module\RjMulticarrier\Domain\InfoShop\Command\ToggleInfoShopStatusCommand;
use Roanja\Module\RjMulticarrier\Domain\InfoShop\Command\UpsertInfoShopCommand;
use Roanja\Module\RjMulticarrier\Domain\InfoShop\Exception\InfoShopNotFoundException;
use Roanja\Module\RjMulticarrier\Domain\InfoShop\Query\GetInfoShopForContext;
use Roanja\Module\RjMulticarrier\Domain\InfoShop\Query\GetInfoShopForEdit;
use Roanja\Module\RjMulticarrier\Domain\InfoShop\View\InfoShopDetailView;
use Roanja\Module\RjMulticarrier\Domain\InfoShop\View\InfoShopView;
use Roanja\Module\RjMulticarrier\Domain\Configuration\Command\SaveExtraConfigurationCommand;
use Roanja\Module\RjMulticarrier\Form\ExtraConfigType;
use Roanja\Module\RjMulticarrier\Form\InfoShopType;
use Roanja\Module\RjMulticarrier\Grid\InfoShop\InfoShopFilters;
use Roanja\Module\RjMulticarrier\Grid\InfoShop\InfoShopGridDefinitionFactory;
use Roanja\Module\RjMulticarrier\Grid\InfoShop\InfoShopGridFactory;
use Roanja\Module\RjMulticarrier\Grid\InfoShop\InfoShopQueryBuilder;
use Roanja\Module\RjMulticarrier\Service\Configuration\ConfigurationManager;
use Shop;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

class InfoShopController extends FrameworkBundleAdminController
{
    private const TRANSLATION_DOMAIN = 'Modules.RjMulticarrier.Admin';

    public function __construct(
        private readonly ConfigurationManager $configurationManager,
        private readonly TranslatorInterface $translator,
        private readonly InfoShopGridFactory $infoShopGridFactory
    ) {
    }

    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function indexAction(Request $request, InfoShopFilters $filters): Response
    {
        $filters->setNeedsToBePersisted(false);
        $grid = $this->infoShopGridFactory->getGrid($filters);

        return $this->render('@Modules/rj_multicarrier/views/templates/admin/info_shop/index.html.twig', [
            'infoShopGrid' => $this->presentGrid($grid),
            'createUrl' => $this->generateUrl('admin_rj_multicarrier_info_shop_create'),
            'extraConfigUrl' => $this->generateUrl('admin_rj_multicarrier_info_shop_extra'),
        ]);
    }

    /**
     * @AdminSecurity("is_granted('create', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function createAction(Request $request): Response
    {
        /** @var InfoShopView $contextView */
        $contextView = $this->getQueryBus()->handle(new GetInfoShopForContext());
        $defaults = $contextView->getFormData();
        $shopId = $contextView->getShopId();

        $defaults['id_infoshop'] = null;
        if (!isset($defaults['shop_association']) || !is_array($defaults['shop_association'])) {
            $defaults['shop_association'] = $this->getContextShopIds();
        }

        $form = $this->createForm(InfoShopType::class, $defaults, [
            'country_choices' => $this->configurationManager->getCountryChoices(),
            'is_multistore_active' => Shop::isFeatureActive(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            return $this->persistInfoShop($form->getData(), $shopId, null);
        }

        return $this->render('@Modules/rj_multicarrier/views/templates/admin/info_shop/form.html.twig', [
            'form' => $form->createView(),
            'action' => 'create',
            'infoShop' => null,
        ]);
    }

    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function editAction(Request $request, int $id): Response
    {
        try {
            /** @var InfoShopDetailView $detail */
            $detail = $this->getQueryBus()->handle(new GetInfoShopForEdit($id));
        } catch (InfoShopNotFoundException $exception) {
            $this->addFlash('error', $this->l('El remitente solicitado no existe.'));

            return $this->redirectToRoute('admin_rj_multicarrier_info_shop_index');
        }

        $data = $detail->toArray();
        $shopAssociation = [];
        if (isset($data['shop_association']) && is_array($data['shop_association'])) {
            $shopAssociation = $data['shop_association'];
        } elseif (isset($data['shops']) && is_array($data['shops'])) {
            $shopAssociation = $data['shops'];
            $data['shop_association'] = $shopAssociation;
        }

        $shopId = $shopAssociation[0] ?? $this->resolveShopId();

        $form = $this->createForm(InfoShopType::class, $data, [
            'country_choices' => $this->configurationManager->getCountryChoices(),
            'is_multistore_active' => Shop::isFeatureActive(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            return $this->persistInfoShop($form->getData(), $shopId, $id);
        }

        return $this->render('@Modules/rj_multicarrier/views/templates/admin/info_shop/form.html.twig', [
            'form' => $form->createView(),
            'action' => 'edit',
            'infoShop' => $data,
        ]);
    }

    /**
     * @AdminSecurity("is_granted('delete', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function deleteAction(Request $request, int $id): RedirectResponse
    {
        try {
            $this->getCommandBus()->handle(new DeleteInfoShopCommand($id));
            $this->addFlash('success', $this->l('Remitente eliminado correctamente.'));
        } catch (InfoShopNotFoundException $exception) {
            $this->addFlash('warning', $this->l('El remitente ya no existe.'));
        } catch (Throwable $exception) {
            $this->addFlash('error', $this->l('No se pudo eliminar el remitente.'));
        }

        return $this->redirectToRoute('admin_rj_multicarrier_info_shop_index');
    }

    /**
     * @AdminSecurity("is_granted('update', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function toggleAction(Request $request, int $id): RedirectResponse
    {
        try {
            /** @var \Roanja\Module\RjMulticarrier\Entity\InfoShop $infoShop */
            $infoShop = $this->getCommandBus()->handle(new ToggleInfoShopStatusCommand($id));
            $message = $infoShop->isActive()
                ? $this->l('Remitente activado correctamente.')
                : $this->l('Remitente desactivado correctamente.');
            $this->addFlash('success', $message);
        } catch (InfoShopNotFoundException) {
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
        $infoShopIds = $this->getBulkInfoShopIds($request);

        if (empty($infoShopIds)) {
            $this->addFlash('warning', $this->l('No se seleccionaron remitentes.'));

            return $this->redirectAfterAction($request);
        }

        $deleted = 0;

        foreach ($infoShopIds as $infoShopId) {
            try {
                $this->getCommandBus()->handle(new DeleteInfoShopCommand($infoShopId));
                ++$deleted;
            } catch (InfoShopNotFoundException) {
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
            $this->getInfoShopQueryBuilder()->getSearchQueryBuilder($filters)
        );

        $fileName = sprintf('rj_multicarrier_infoshops_%s.csv', date('Ymd_His'));

        return $this->createCsvResponse($rows, $fileName);
    }

    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function exportSelectedCsvAction(Request $request): Response
    {
        $infoShopIds = $this->getBulkInfoShopIds($request);

        if (empty($infoShopIds)) {
            $this->addFlash('warning', $this->l('Selecciona al menos un remitente para exportar.'));

            return $this->redirectAfterAction($request);
        }

        $filters = new InfoShopFilters([
            'limit' => 0,
            'offset' => 0,
            'orderBy' => 'id_infoshop',
            'sortOrder' => 'ASC',
            'filters' => [
                'ids' => $infoShopIds,
            ],
        ]);
        $filters->setNeedsToBePersisted(false);

        $rows = $this->fetchAll(
            $this->getInfoShopQueryBuilder()->getSearchQueryBuilder($filters)
        );

        if (empty($rows)) {
            $this->addFlash('warning', $this->l('No se encontraron remitentes para exportar.'));

            return $this->redirectAfterAction($request);
        }

        $fileName = sprintf('rj_multicarrier_infoshops_seleccion_%s.csv', date('Ymd_His'));

        return $this->createCsvResponse($rows, $fileName);
    }

    /**
     * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))", message="Access denied.")
     */
    public function extraConfigAction(Request $request): Response
    {
        $defaults = $this->configurationManager->getExtraConfigDefaults();
        $currentModule = $defaults['RJ_MODULE_CONTRAREEMBOLSO'] ?? null;
        $moduleChoices = $this->configurationManager->getCashOnDeliveryModuleChoices($currentModule);

        $form = $this->createForm(ExtraConfigType::class, $defaults, [
            'module_choices' => $moduleChoices,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            try {
                $this->getCommandBus()->handle(new SaveExtraConfigurationCommand(
                    isset($data['RJ_ETIQUETA_TRANSP_PREFIX']) ? trim((string) $data['RJ_ETIQUETA_TRANSP_PREFIX']) : '',
                    (string) ($data['RJ_MODULE_CONTRAREEMBOLSO'] ?? '')
                ));

                $this->addFlash('success', $this->l('Configuración adicional guardada.'));

                return $this->redirectToRoute('admin_rj_multicarrier_info_shop_extra');
            } catch (Throwable $exception) {
                $this->addFlash('error', $this->l('No se pudo guardar la configuración adicional.'));
            }
        }

        return $this->render('@Modules/rj_multicarrier/views/templates/admin/info_shop/extra.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    private function persistInfoShop(array $data, int $shopId, ?int $infoShopId): RedirectResponse
    {
        try {
            if (0 === $shopId) {
                $shopId = $this->resolveShopId();
            }

            if (0 === $shopId) {
                throw new \RuntimeException('No se pudo determinar la tienda en contexto.');
            }

            $shopIds = $this->resolveShopAssociation($data, $shopId);

            if (empty($shopIds)) {
                throw new InvalidArgumentException('shop_association_empty');
            }

            $command = new UpsertInfoShopCommand(
                $infoShopId,
                (string) $data['firstname'],
                (string) $data['lastname'],
                $this->nullableString($data['company'] ?? null),
                $this->nullableString($data['additionalname'] ?? null),
                (int) $data['id_country'],
                (string) $data['state'],
                (string) $data['city'],
                (string) $data['street'],
                (string) $data['number'],
                (string) $data['postcode'],
                $this->nullableString($data['additionaladdress'] ?? null),
                isset($data['isbusiness']) ? (bool) $data['isbusiness'] : null,
                $this->nullableString($data['email'] ?? null),
                (string) $data['phone'],
                $this->nullableString($data['vatnumber'] ?? null),
                $shopIds
            );

            $this->getCommandBus()->handle($command);

            $message = null === $infoShopId
                ? $this->l('Remitente creado correctamente.')
                : $this->l('Remitente actualizado correctamente.');
            $this->addFlash('success', $message);
        } catch (InvalidArgumentException $exception) {
            $this->addFlash('error', $this->l('Selecciona al menos una tienda.'));
        } catch (Throwable $exception) {
            $this->addFlash('error', $this->l('No se pudo guardar el remitente.'));  
        }

        return $this->redirectToRoute('admin_rj_multicarrier_info_shop_index');
    }

    private function buildFilters(Request $request): InfoShopFilters
    {
        $defaults = InfoShopFilters::getDefaults();

        $scopedParameters = $this->extractScopedParameters($request, InfoShopGridDefinitionFactory::GRID_ID);

        $filterValues = array_merge($defaults, [
            'limit' => $this->getIntParam($request, $scopedParameters, 'limit', (int) $defaults['limit']),
            'offset' => $this->getIntParam($request, $scopedParameters, 'offset', (int) $defaults['offset']),
            'orderBy' => $this->getStringParam($request, $scopedParameters, 'orderBy', (string) $defaults['orderBy']),
            'sortOrder' => $this->getStringParam($request, $scopedParameters, 'sortOrder', (string) $defaults['sortOrder']),
            'filters' => $this->getArrayParam($request, $scopedParameters, 'filters', (array) $defaults['filters']),
        ]);

        $filters = new InfoShopFilters($filterValues);
        $filters->setNeedsToBePersisted(false);

        return $filters;
    }

    /**
     * @return array<int>
     */
    private function getBulkInfoShopIds(Request $request): array
    {
        $gridId = InfoShopGridDefinitionFactory::GRID_ID;
        $columnName = $gridId . '_infoshop_bulk';

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

        return $this->redirectToRoute('admin_rj_multicarrier_info_shop_index');
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
                    $row['id_infoshop'] ?? '',
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

    private function getInfoShopQueryBuilder(): InfoShopQueryBuilder
    {
        /** @var InfoShopQueryBuilder $queryBuilder */
        $queryBuilder = $this->get('Roanja\Module\RjMulticarrier\Grid\InfoShop\InfoShopQueryBuilder');

        return $queryBuilder;
    }

    /**
     * @return int[]
     */
    private function resolveShopAssociation(array $data, int $fallbackShopId): array
    {
        $shopIds = [];

        if (isset($data['shop_association'])) {
            $shopIds = $this->normalizeShopIds($data['shop_association']);
        }

        if (empty($shopIds) && $fallbackShopId > 0) {
            $shopIds = [$fallbackShopId];
        }

        if (empty($shopIds)) {
            $shopIds = $this->getContextShopIds();
        }

        return $shopIds;
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

    private function nullableString(mixed $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $trimmed = trim((string) $value);

        return '' === $trimmed ? null : $trimmed;
    }
}
