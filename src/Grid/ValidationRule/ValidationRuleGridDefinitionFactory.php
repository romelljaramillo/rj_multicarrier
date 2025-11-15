<?php
/**
 * Definición del grid de reglas de validación.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Grid\ValidationRule;

use PrestaShop\PrestaShop\Core\Grid\Action\Bulk\BulkActionCollection;
use PrestaShop\PrestaShop\Core\Grid\Action\Bulk\Type\SubmitBulkAction;
use PrestaShop\PrestaShop\Core\Grid\Action\GridActionCollection;
use PrestaShop\PrestaShop\Core\Grid\Action\GridActionCollectionInterface;
use PrestaShop\PrestaShop\Core\Grid\Action\Row\RowActionCollection;
use PrestaShop\PrestaShop\Core\Grid\Action\Row\Type\LinkRowAction;
use PrestaShop\PrestaShop\Core\Grid\Column\ColumnCollection;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\ActionColumn;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\BulkActionColumn;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\DataColumn;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\ToggleColumn;
use PrestaShop\PrestaShop\Core\Grid\Definition\Factory\BulkDeleteActionTrait;
use PrestaShop\PrestaShop\Core\Grid\Definition\Factory\DeleteActionTrait;
use PrestaShop\PrestaShop\Core\Grid\Filter\Filter;
use PrestaShop\PrestaShop\Core\Grid\Filter\FilterCollection;
use PrestaShop\PrestaShop\Core\Hook\HookDispatcherInterface;
use PrestaShop\PrestaShop\Core\Grid\Action\Type\SimpleGridAction;
use PrestaShopBundle\Form\Admin\Type\SearchAndResetType;
use Roanja\Module\RjMulticarrier\Form\ValidationRule\ValidationRuleFormOptionsProvider;
use Roanja\Module\RjMulticarrier\Grid\AbstractModuleGridDefinitionFactory;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;

final class ValidationRuleGridDefinitionFactory extends AbstractModuleGridDefinitionFactory
{
    use BulkDeleteActionTrait;
    use DeleteActionTrait;

    public const GRID_ID = 'rj_multicarrier_validation_rule';

    public function __construct(
        HookDispatcherInterface $hookDispatcher,
        private readonly ValidationRuleFormOptionsProvider $formOptionsProvider
    ) {
        parent::__construct($hookDispatcher);
    }

    protected function getId(): string
    {
        return self::GRID_ID;
    }

    protected function getName(): string
    {
        return $this->transString('Reglas de validación');
    }

    protected function getColumns(): ColumnCollection
    {
        $columns = new ColumnCollection();

        $columns
            ->add((new BulkActionColumn('validation_rule_bulk'))
                ->setOptions([
                    'bulk_field' => 'id_validation_rule',
                ]))
            ->add((new DataColumn('id_validation_rule'))
                ->setName($this->transString('ID'))
                ->setOptions([
                    'field' => 'id_validation_rule',
                ]))
            ->add((new DataColumn('name'))
                ->setName($this->transString('Nombre'))
                ->setOptions([
                    'field' => 'name',
                ]))
            ->add((new DataColumn('priority'))
                ->setName($this->transString('Prioridad'))
                ->setOptions([
                    'field' => 'priority',
                ]))
            ->add((new DataColumn('scope_label'))
                ->setName($this->transString('Ámbito'))
                ->setOptions([
                    'field' => 'scope_label',
                ]))
            ->add((new ToggleColumn('active'))
                ->setName($this->transString('Activa'))
                ->setOptions([
                    'field' => 'active',
                    'primary_field' => 'id_validation_rule',
                    'route' => 'admin_rj_multicarrier_validation_rule_toggle',
                    'route_param_name' => 'id',
                ]))
            ->add((new DataColumn('updated_at'))
                ->setName($this->transString('Actualizada el'))
                ->setOptions([
                    'field' => 'updated_at',
                ]))
            ->add((new ActionColumn('actions'))
                ->setName($this->transString('Acciones', [], 'Admin.Global'))
                ->setOptions([
                    'actions' => $this->getRowActions(),
                ]));

        return $columns;
    }

    protected function getFilters(): FilterCollection
    {
        $filters = new FilterCollection();

        $filters
            ->add((new Filter('id_validation_rule', TextType::class))
                ->setAssociatedColumn('id_validation_rule')
                ->setTypeOptions([
                    'required' => false,
                    'attr' => [
                        'placeholder' => $this->transString('ID'),
                    ],
                ]))
            ->add((new Filter('name', TextType::class))
                ->setAssociatedColumn('name')
                ->setTypeOptions([
                    'required' => false,
                    'attr' => [
                        'placeholder' => $this->transString('Nombre'),
                    ],
                ]))
            ->add((new Filter('priority', TextType::class))
                ->setAssociatedColumn('priority')
                ->setTypeOptions([
                    'required' => false,
                    'attr' => [
                        'placeholder' => $this->transString('Prioridad'),
                    ],
                ]))
            ->add((new Filter('active', ChoiceType::class))
                ->setAssociatedColumn('active')
                ->setTypeOptions([
                    'required' => false,
                    'choices' => [
                        $this->transString('Sí', [], 'Admin.Global') => 1,
                        $this->transString('No', [], 'Admin.Global') => 0,
                    ],
                    'placeholder' => $this->transString('Cualquiera', [], 'Admin.Global'),
                ]))
            ->add((new Filter('scope', ChoiceType::class))
                ->setAssociatedColumn('scope_label')
                ->setTypeOptions([
                    'required' => false,
                    'choices' => $this->formOptionsProvider->getScopeChoices(),
                    'placeholder' => $this->transString('Cualquier ámbito'),
                ]))
            ->add((new Filter('actions', SearchAndResetType::class))
                ->setTypeOptions([
                    'reset_route' => 'admin_common_reset_search_by_filter_id',
                    'reset_route_params' => [
                        'filterId' => $this->getId(),
                    ],
                    'redirect_route' => 'admin_rj_multicarrier_validation_rule_index',
                ])
                ->setAssociatedColumn('actions'));

        return $filters;
    }

    protected function getGridActions(): GridActionCollectionInterface
    {
        return (new GridActionCollection())
            ->add((new SimpleGridAction('common_refresh_list'))
                ->setName($this->transString('Refresh list', [], 'Admin.Actions'))
                ->setIcon('refresh'))
            ->add((new SimpleGridAction('common_show_query'))
                ->setName($this->transString('Show SQL query', [], 'Admin.Actions'))
                ->setIcon('code'))
            ->add((new SimpleGridAction('common_export_sql_manager'))
                ->setName($this->transString('Export to SQL Manager', [], 'Admin.Actions'))
                ->setIcon('storage'));
    }

    protected function getBulkActions(): BulkActionCollection
    {
        $actions = new BulkActionCollection();

        $actions->add(
            $this->buildBulkDeleteAction('admin_rj_multicarrier_validation_rule_delete_bulk')
        );

        return $actions;
    }

    /**
     * @return RowActionCollection
     */
    private function getRowActions(): RowActionCollection
    {
        $rowActions = new RowActionCollection();

        $rowActions
            ->add((new LinkRowAction('edit'))
                ->setName($this->transString('Editar', [], 'Admin.Actions'))
                ->setIcon('edit')
                ->setOptions([
                    'route' => 'admin_rj_multicarrier_validation_rule_edit',
                    'route_param_name' => 'id',
                    'route_param_field' => 'id_validation_rule',
                ]))
            ->add($this->buildDeleteAction(
                'admin_rj_multicarrier_validation_rule_delete',
                'id',
                'id_validation_rule',
                Request::METHOD_DELETE,
                [],
                [
                    'confirm_message' => $this->transString('¿Eliminar esta regla de validación?'),
                ]
            ));

        return $rowActions;
    }

}
