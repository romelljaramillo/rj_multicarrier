<?php
/**
 * Controlador Symfony para gestionar reglas de validación de transportistas.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Controller\Admin;

use PrestaShopBundle\Controller\Admin\FrameworkBundleAdminController;
use PrestaShopBundle\Security\Annotation\AdminSecurity;
use Roanja\Module\RjMulticarrier\Domain\ValidationRule\Command\BulkDeleteValidationRuleCommand;
use Roanja\Module\RjMulticarrier\Domain\ValidationRule\Command\DeleteValidationRuleCommand;
use Roanja\Module\RjMulticarrier\Domain\ValidationRule\Command\ToggleValidationRuleStatusCommand;
use Roanja\Module\RjMulticarrier\Domain\ValidationRule\Command\UpsertValidationRuleCommand;
use Roanja\Module\RjMulticarrier\Domain\ValidationRule\Exception\ValidationRuleException;
use Roanja\Module\RjMulticarrier\Domain\ValidationRule\Exception\ValidationRuleNotFoundException;
use Roanja\Module\RjMulticarrier\Entity\ValidationRule;
use Roanja\Module\RjMulticarrier\Form\ValidationRule\ValidationRuleFormOptionsProvider;
use Roanja\Module\RjMulticarrier\Form\ValidationRule\ValidationRuleType;
use Roanja\Module\RjMulticarrier\Grid\ValidationRule\ValidationRuleFilters;
use Roanja\Module\RjMulticarrier\Grid\ValidationRule\ValidationRuleGridDefinitionFactory;
use Roanja\Module\RjMulticarrier\Grid\ValidationRule\ValidationRuleGridFactory;
use Roanja\Module\RjMulticarrier\Repository\ValidationRuleRepository;
use Roanja\Module\RjMulticarrier\Service\Carrier\ValidationRuleReferenceNormalizer;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

final class ValidationRuleController extends FrameworkBundleAdminController
{
	private const TRANSLATION_DOMAIN = 'Modules.RjMulticarrier.Admin';

	public function __construct(
		private readonly ValidationRuleGridFactory $gridFactory,
		private readonly ValidationRuleFormOptionsProvider $formOptionsProvider,
		private readonly ValidationRuleRepository $validationRuleRepository,
		private readonly ValidationRuleReferenceNormalizer $validationRuleReferenceNormalizer
	) {
	}

	/**
	 * @AdminSecurity("is_granted('read', request.get('_legacy_controller'))", message="Access denied.")
	 */
	public function indexAction(Request $request, ValidationRuleFilters $filters): Response
	{
		$filters->setNeedsToBePersisted(false);

		$grid = $this->gridFactory->getGrid($filters);

		return $this->render('@Modules/rj_multicarrier/views/templates/admin/validation_rules/index.html.twig', [
			'validationRuleGrid' => $this->presentGrid($grid),
			'canManageRules' => $this->isSingleShopContext(),
			'currentShopName' => $this->getCurrentShopName(),
		]);
	}

	/**
	 * @AdminSecurity("is_granted('create', request.get('_legacy_controller'))", message="Access denied.")
	 */
	public function createAction(Request $request): Response
	{
		if (null !== ($redirect = $this->guardSingleShopContext())) {
			return $redirect;
		}

		$form = $this->createValidationRuleForm($this->getDefaultFormData());
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			$this->validateWeights($form);

			if ($form->isValid() && $this->processUpsertCommand($form)) {
				$this->addFlash('success', $this->trans('Regla de validación creada correctamente.', self::TRANSLATION_DOMAIN));

				return $this->redirectToRoute('admin_rj_multicarrier_validation_rule_index');
			}
		}

		return $this->render('@Modules/rj_multicarrier/views/templates/admin/validation_rules/form.html.twig', [
			'form' => $form->createView(),
			'isEdit' => false,
			'currentShopName' => $this->getCurrentShopName(),
		]);
	}

	/**
	 * @AdminSecurity("is_granted('update', request.get('_legacy_controller'))", message="Access denied.")
	 */
	public function editAction(Request $request, int $id): Response
	{
		if (null !== ($redirect = $this->guardSingleShopContext())) {
			return $redirect;
		}

		$rule = $this->validationRuleRepository->findOneById($id);

		if (!$rule instanceof ValidationRule) {
			$this->addFlash('error', $this->trans('La regla solicitada no existe o ya fue eliminada.', self::TRANSLATION_DOMAIN));

			return $this->redirectToRoute('admin_rj_multicarrier_validation_rule_index');
		}

		if (null !== ($redirect = $this->guardRuleForCurrentShop($rule))) {
			return $redirect;
		}

		$form = $this->createValidationRuleForm($this->buildFormDataFromEntity($rule));
		$form->handleRequest($request);

		if ($form->isSubmitted() && $form->isValid()) {
			$this->validateWeights($form);

			if ($form->isValid() && $this->processUpsertCommand($form, $rule->getId())) {
				$this->addFlash('success', $this->trans('Regla de validación actualizada correctamente.', self::TRANSLATION_DOMAIN));

				return $this->redirectToRoute('admin_rj_multicarrier_validation_rule_index');
			}
		}

		return $this->render('@Modules/rj_multicarrier/views/templates/admin/validation_rules/form.html.twig', [
			'form' => $form->createView(),
			'isEdit' => true,
			'ruleId' => $id,
			'currentShopName' => $this->getCurrentShopName(),
		]);
	}

	/**
	 * @AdminSecurity("is_granted('delete', request.get('_legacy_controller'))", message="Access denied.")
	 */
	public function deleteAction(Request $request, int $id): RedirectResponse
	{
		if (null !== ($redirect = $this->guardSingleShopContext())) {
			return $redirect;
		}

		$rule = $this->validationRuleRepository->findOneById($id);

		if (!$rule instanceof ValidationRule) {
			$this->addFlash('error', $this->trans('La regla solicitada no existe o ya fue eliminada.', self::TRANSLATION_DOMAIN));

			return $this->redirectToRoute('admin_rj_multicarrier_validation_rule_index');
		}

		if (null !== ($redirect = $this->guardRuleForCurrentShop($rule))) {
			return $redirect;
		}

		try {
			$this->getCommandBus()->handle(new DeleteValidationRuleCommand($id));
			$this->addFlash('success', $this->trans('Regla eliminada correctamente.', self::TRANSLATION_DOMAIN));
		} catch (ValidationRuleException $exception) {
			$this->addFlash('error', $exception->getMessage());
		} catch (\Throwable $exception) {
			$this->addFlash('error', $this->trans('No se pudo eliminar la regla seleccionada.', self::TRANSLATION_DOMAIN));
		}

		return $this->redirectToRoute('admin_rj_multicarrier_validation_rule_index');
	}

	/**
	 * @AdminSecurity("is_granted('delete', request.get('_legacy_controller'))", message="Access denied.")
	 */
	public function deleteBulkAction(Request $request): RedirectResponse
	{
		if (null !== ($redirect = $this->guardSingleShopContext())) {
			return $redirect;
		}

		$ids = $this->getBulkValidationRuleIds($request);

		if (empty($ids)) {
			$this->addFlash('warning', $this->trans('Selecciona al menos una regla para eliminar.', self::TRANSLATION_DOMAIN));

			return $this->redirectToRoute('admin_rj_multicarrier_validation_rule_index');
		}

		$shopId = $this->getCurrentShopId();

		if (null === $shopId) {
			return $this->guardSingleShopContext() ?? $this->redirectToRoute('admin_rj_multicarrier_validation_rule_index');
		}

		$allowedIds = [];

		foreach ($ids as $ruleId) {
			$rule = $this->validationRuleRepository->findOneById($ruleId);

			if (!$rule instanceof ValidationRule) {
				continue;
			}

			$ruleShopId = $rule->getShopId();

			if (null === $ruleShopId || $ruleShopId === $shopId) {
				$allowedIds[] = $ruleId;
			}
		}

		if (empty($allowedIds)) {
			$this->addFlash('error', $this->trans('No tienes permiso para eliminar las reglas seleccionadas en este contexto.', self::TRANSLATION_DOMAIN));

			return $this->redirectToRoute('admin_rj_multicarrier_validation_rule_index');
		}

		try {
			/** @var int $deleted */
			$deleted = $this->getCommandBus()->handle(new BulkDeleteValidationRuleCommand($allowedIds));

			if ($deleted > 0) {
				$this->addFlash('success', $this->trans('%count% reglas eliminadas.', self::TRANSLATION_DOMAIN, ['%count%' => $deleted]));
			} else {
				$this->addFlash('warning', $this->trans('No se eliminó ninguna regla.', self::TRANSLATION_DOMAIN));
			}
		} catch (\Throwable $exception) {
			$this->addFlash('error', $this->trans('No se pudo completar la eliminación masiva.', self::TRANSLATION_DOMAIN));
		}

		return $this->redirectToRoute('admin_rj_multicarrier_validation_rule_index');
	}

	/**
	 * @AdminSecurity("is_granted('update', request.get('_legacy_controller'))", message="Access denied.")
	 */
	public function toggleAction(Request $request, int $id): RedirectResponse
	{
		if (null !== ($redirect = $this->guardSingleShopContext())) {
			return $redirect;
		}

		$rule = $this->validationRuleRepository->findOneById($id);

		if (!$rule instanceof ValidationRule) {
			$this->addFlash('error', $this->trans('La regla solicitada no existe o ya fue eliminada.', self::TRANSLATION_DOMAIN));

			return $this->redirectToRoute('admin_rj_multicarrier_validation_rule_index');
		}

		if (null !== ($redirect = $this->guardRuleForCurrentShop($rule))) {
			return $redirect;
		}

		try {
			/** @var ValidationRule $updatedRule */
			$updatedRule = $this->getCommandBus()->handle(new ToggleValidationRuleStatusCommand($id));
			$message = $updatedRule->isActive()
				? $this->trans('Regla activada.', self::TRANSLATION_DOMAIN)
				: $this->trans('Regla desactivada.', self::TRANSLATION_DOMAIN);
			$this->addFlash('success', $message);
		} catch (ValidationRuleNotFoundException $exception) {
			$this->addFlash('error', $exception->getMessage());
		} catch (\Throwable $exception) {
			$this->addFlash('error', $this->trans('No se pudo cambiar el estado de la regla.', self::TRANSLATION_DOMAIN));
		}

		return $this->redirectToRoute('admin_rj_multicarrier_validation_rule_index');
	}

	private function createValidationRuleForm(array $data): FormInterface
	{
		$this->validationRuleReferenceNormalizer->normalizeAll();

		return $this->createForm(ValidationRuleType::class, $data, [
			'carrier_choices' => $this->formOptionsProvider->getCarrierChoices(),
			'product_choices' => $this->formOptionsProvider->getProductChoices(),
			'category_choices' => $this->formOptionsProvider->getCategoryChoices(),
			'zone_choices' => $this->formOptionsProvider->getZoneChoices(),
			'country_choices' => $this->formOptionsProvider->getCountryChoices(),
		]);
	}

    private function processUpsertCommand(FormInterface $form, ?int $ruleId = null): bool
    {
        $data = $form->getData();

        if (!is_array($data)) {
            return false;
        }

        $shopId = $this->getCurrentShopId();

        if (null === $shopId) {
            $form->addError(new FormError($this->trans('Selecciona una tienda concreta en el selector multitienda para gestionar reglas.', self::TRANSLATION_DOMAIN)));

            return false;
        }

        $productIds = $this->extractIds($data['product_ids'] ?? []);
        $categoryIds = $this->extractIds($data['category_ids'] ?? []);
        $zoneIds = $this->extractIds($data['zone_ids'] ?? []);
        $countryIds = $this->extractIds($data['country_ids'] ?? []);

        $command = new UpsertValidationRuleCommand(
            $ruleId,
            (string) ($data['name'] ?? ''),
            (int) ($data['priority'] ?? 0),
            (bool) ($data['active'] ?? false),
            $shopId,
            $productIds,
            $categoryIds,
            $zoneIds,
            $countryIds,
            $this->parseFloatValue($data['min_weight'] ?? null),
            $this->parseFloatValue($data['max_weight'] ?? null),
            $this->normalizeIdArray($data['allow_ids'] ?? []),
            $this->normalizeIdArray($data['deny_ids'] ?? []),
            $this->normalizeIdArray($data['add_ids'] ?? [])
        );

        try {
            $this->getCommandBus()->handle($command);

            return true;
        } catch (ValidationRuleException $exception) {
            $form->addError(new FormError($exception->getMessage()));
        } catch (\Throwable $exception) {
            $form->addError(new FormError($this->trans('No se pudo guardar la regla de validación.', self::TRANSLATION_DOMAIN)));
        }

        return false;
    }

    private function validateWeights(FormInterface $form): void
    {
        $min = $this->parseFloatValue($form->get('min_weight')->getData());
        $max = $this->parseFloatValue($form->get('max_weight')->getData());

        if (null !== $min && null !== $max && $min > $max) {
            $form->get('max_weight')->addError(new FormError($this->trans('El peso máximo debe ser mayor o igual al peso mínimo.', self::TRANSLATION_DOMAIN)));
        }
    }

    private function buildFormDataFromEntity(ValidationRule $rule): array
    {
        return [
            'id' => $rule->getId(),
            'name' => $rule->getName(),
            'priority' => $rule->getPriority(),
            'active' => $rule->isActive(),
            'product_ids' => $rule->getProductIds(),
            'category_ids' => $rule->getCategoryIds(),
            'zone_ids' => $rule->getZoneIds(),
            'country_ids' => $rule->getCountryIds(),
            'min_weight' => $rule->getMinWeight(),
            'max_weight' => $rule->getMaxWeight(),
            'allow_ids' => $rule->getAllowIds(),
            'deny_ids' => $rule->getDenyIds(),
            'add_ids' => $rule->getAddIds(),
        ];
    }

    private function getDefaultFormData(): array
    {
        return [
            'id' => null,
            'name' => '',
            'priority' => 0,
            'active' => true,
            'product_ids' => [],
            'category_ids' => [],
            'zone_ids' => [],
            'country_ids' => [],
            'min_weight' => null,
            'max_weight' => null,
            'allow_ids' => [],
            'deny_ids' => [],
            'add_ids' => [],
        ];
    }

    private function extractIds($value): array
    {
        if (is_array($value)) {
            return $this->normalizeIdArray($value);
        }

        if (is_string($value)) {
            return $this->parseIdList($value);
        }

        return [];
    }

    private function parseIdList(string $input): array
    {
        $trimmed = trim($input);

        if ('' === $trimmed) {
            return [];
        }

        $parts = preg_split('/[\s,;]+/', $trimmed, -1, PREG_SPLIT_NO_EMPTY);

        if (false === $parts) {
            return [];
        }

        $ids = [];

        foreach ($parts as $part) {
            $value = (int) $part;

            if ($value > 0) {
                $ids[] = $value;
            }
        }

        return array_values(array_unique($ids));
    }

    private function normalizeIdArray($value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $ids = [];

        foreach ($value as $item) {
            $id = (int) $item;

            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    private function parseFloatValue($value): ?float
    {
        if (null === $value || '' === $value) {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }

	private function guardSingleShopContext(): ?RedirectResponse
	{
		if ($this->isSingleShopContext()) {
			return null;
		}

		$this->addFlash('warning', $this->trans('Selecciona una tienda concreta en el selector multitienda para gestionar reglas.', self::TRANSLATION_DOMAIN));

		return $this->redirectToRoute('admin_rj_multicarrier_validation_rule_index');
	}

	private function guardRuleForCurrentShop(ValidationRule $rule): ?RedirectResponse
	{
		$currentShopId = $this->getCurrentShopId();

		if (null === $currentShopId) {
			return $this->guardSingleShopContext();
		}

		$ruleShopId = $rule->getShopId();

		if (null !== $ruleShopId && $ruleShopId !== $currentShopId) {
			$this->addFlash('error', $this->trans('Esta regla pertenece a otra tienda. Cambia el contexto multitienda para editarla.', self::TRANSLATION_DOMAIN));

			return $this->redirectToRoute('admin_rj_multicarrier_validation_rule_index');
		}

		return null;
	}

	private function isSingleShopContext(): bool
	{
		$shopClass = 'Shop';

		if (!class_exists($shopClass) || !method_exists($shopClass, 'getContext')) {
			return true;
		}

		$contextValue = (int) call_user_func([$shopClass, 'getContext']);
		$shopContextConst = defined($shopClass . '::CONTEXT_SHOP')
			? (int) constant($shopClass . '::CONTEXT_SHOP')
			: $contextValue;

		if ($contextValue !== $shopContextConst) {
			return false;
		}

		return null !== $this->getCurrentShopId();
	}

	private function getCurrentShopId(): ?int
	{
		$shopClass = 'Shop';

		if (!class_exists($shopClass) || !method_exists($shopClass, 'getContextShopID')) {
			return null;
		}

		$shopId = (int) call_user_func([$shopClass, 'getContextShopID']);

		return $shopId > 0 ? $shopId : null;
	}

	private function getCurrentShopName(): ?string
	{
		$contextClass = 'Context';

		if (!class_exists($contextClass) || !method_exists($contextClass, 'getContext')) {
			return null;
		}

		$context = call_user_func([$contextClass, 'getContext']);

		if (isset($context->shop->name) && '' !== (string) $context->shop->name) {
			return (string) $context->shop->name;
		}

		return null;
	}

	private function getBulkValidationRuleIds(Request $request): array
	{
		$gridId = ValidationRuleGridDefinitionFactory::GRID_ID;
		$columnName = $gridId . '_validation_rule_bulk';

		$collected = [];

		$gridPayload = $request->request->get($gridId, []);
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

		$ids = [];

		foreach ($collected as $value) {
			$id = (int) $value;
			if ($id > 0) {
				$ids[] = $id;
			}
		}

		return array_values(array_unique($ids));
	}
}

