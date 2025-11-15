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
		private readonly ValidationRuleRepository $validationRuleRepository
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
		]);
	}

	/**
	 * @AdminSecurity("is_granted('create', request.get('_legacy_controller'))", message="Access denied.")
	 */
	public function createAction(Request $request): Response
	{
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
		]);
	}

	/**
	 * @AdminSecurity("is_granted('update', request.get('_legacy_controller'))", message="Access denied.")
	 */
	public function editAction(Request $request, int $id): Response
	{
		$rule = $this->validationRuleRepository->findOneById($id);

		if (!$rule instanceof ValidationRule) {
			$this->addFlash('error', $this->trans('La regla solicitada no existe o ya fue eliminada.', self::TRANSLATION_DOMAIN));

			return $this->redirectToRoute('admin_rj_multicarrier_validation_rule_index');
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
		]);
	}

	/**
	 * @AdminSecurity("is_granted('delete', request.get('_legacy_controller'))", message="Access denied.")
	 */
	public function deleteAction(Request $request, int $id): RedirectResponse
	{
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
		$ids = $this->getBulkValidationRuleIds($request);

		if (empty($ids)) {
			$this->addFlash('warning', $this->trans('Selecciona al menos una regla para eliminar.', self::TRANSLATION_DOMAIN));

			return $this->redirectToRoute('admin_rj_multicarrier_validation_rule_index');
		}

		try {
			/** @var int $deleted */
			$deleted = $this->getCommandBus()->handle(new BulkDeleteValidationRuleCommand($ids));

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
		try {
			/** @var ValidationRule $rule */
			$rule = $this->getCommandBus()->handle(new ToggleValidationRuleStatusCommand($id));
			$message = $rule->isActive()
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
		return $this->createForm(ValidationRuleType::class, $data, [
			'scope_choices' => $this->formOptionsProvider->getScopeChoices(),
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

		[$shopGroupId, $shopId] = $this->parseScopeValue((string) ($data['scope'] ?? 'global'));

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
			$shopGroupId,
			$productIds,
			$categoryIds,
			$zoneIds,
			$countryIds,
			$this->parseFloatValue($data['min_weight'] ?? null),
			$this->parseFloatValue($data['max_weight'] ?? null),
			$this->normalizeIdArray($data['allow_ids'] ?? []),
			$this->normalizeIdArray($data['deny_ids'] ?? []),
			$this->normalizeIdArray($data['add_ids'] ?? []),
			$this->normalizeIdArray($data['prefer_ids'] ?? [])
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
			'scope' => $this->buildScopeValue($rule->getShopGroupId(), $rule->getShopId()),
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
			'prefer_ids' => $rule->getPreferIds(),
		];
	}

	private function getDefaultFormData(): array
	{
		return [
			'id' => null,
			'name' => '',
			'priority' => 0,
			'scope' => 'global',
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
			'prefer_ids' => [],
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

	private function parseScopeValue(string $scope): array
	{
		if (str_starts_with($scope, 'group-')) {
			$id = (int) substr($scope, 6);

			return [$id > 0 ? $id : null, null];
		}

		if (str_starts_with($scope, 'shop-')) {
			$id = (int) substr($scope, 5);

			return [null, $id > 0 ? $id : null];
		}

		return [null, null];
	}

	private function buildScopeValue(?int $shopGroupId, ?int $shopId): string
	{
		if (null !== $shopId && $shopId > 0) {
			return sprintf('shop-%d', $shopId);
		}

		if (null !== $shopGroupId && $shopGroupId > 0) {
			return sprintf('group-%d', $shopGroupId);
		}

		return 'global';
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

