<?php

/**
 * Grid definition factory for info shipments pending generation.
 */

declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Grid\InfoShipment;

use PrestaShop\PrestaShop\Core\Grid\Action\Bulk\BulkActionCollection;
use PrestaShop\PrestaShop\Core\Grid\Action\Bulk\Type\SubmitBulkAction;
use PrestaShop\PrestaShop\Core\Grid\Action\GridActionCollection;
use PrestaShop\PrestaShop\Core\Grid\Action\GridActionCollectionInterface;
use PrestaShop\PrestaShop\Core\Grid\Action\Type\SimpleGridAction;
use PrestaShop\PrestaShop\Core\Grid\Action\Type\SubmitGridAction;
use PrestaShop\PrestaShop\Core\Grid\Column\ColumnCollection;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\BulkActionColumn;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\DataColumn;
use PrestaShop\PrestaShop\Core\Grid\Definition\Factory\BulkDeleteActionTrait;
use PrestaShop\PrestaShop\Core\Grid\Definition\Factory\DeleteActionTrait;
use PrestaShop\PrestaShop\Core\Grid\Filter\Filter;
use PrestaShop\PrestaShop\Core\Grid\Filter\FilterCollection;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use PrestaShopBundle\Form\Admin\Type\SearchAndResetType;
use Roanja\Module\RjMulticarrier\Grid\AbstractModuleGridDefinitionFactory;
use Roanja\Module\RjMulticarrier\Grid\Column\Type\InlineEditableColumn;
use Roanja\Module\RjMulticarrier\Service\InlineEntry\InlineFieldChoicesProvider;

final class InfoShipmentGridDefinitionFactory extends AbstractModuleGridDefinitionFactory
{
    use BulkDeleteActionTrait;
    use DeleteActionTrait;

    public const GRID_ID = 'rj_multicarrier_info_shipment';

    public function __construct(
        $hookDispatcher,
        private readonly InlineFieldChoicesProvider $inlineFieldChoicesProvider
    ) {
        parent::__construct($hookDispatcher);
    }

    protected function getId(): string
    {
        return self::GRID_ID;
    }

    protected function getName(): string
    {
        return $this->transString('Pending shipments');
    }

    protected function getColumns(): ColumnCollection
    {
        $typeChoices = $this->inlineFieldChoicesProvider->getTypeShipmentChoices();
        $employeeChoices = $this->inlineFieldChoicesProvider->getEmployeeChoices();

        $columns = new ColumnCollection();

        $columns
            ->add((new BulkActionColumn('info_shipment_bulk'))
                ->setOptions([
                    'bulk_field' => 'id_info_shipment',
                ]))
            ->add((new DataColumn('package_id'))
                ->setName($this->transString('ID paquete'))
                ->setOptions([
                    'field' => 'id_info_shipment',
                    'sortable' => true,
                ]))
            ->add((new InlineEditableColumn('order_context'))
                ->setName($this->transString('Pedido'))
                ->setOptions([
                    'field' => 'id_order',
                    'sortable' => true,
                    'template' => '@Modules/rj_multicarrier/views/templates/admin/info_shipment/grid/cell/order_info.html.twig',
                ]))
            ->add((new InlineEditableColumn('reference_order'))
                ->setName($this->transString('Referencia'))
                ->setOptions([
                    'field' => 'reference_order',
                    'sortable' => true,
                    'template' => '@Modules/rj_multicarrier/views/templates/admin/info_shipment/grid/cell/reference_field.html.twig',
                ]))
            ->add((new InlineEditableColumn('type_shipment'))
                ->setName($this->transString('Tipo de envío'))
                ->setOptions([
                    'field' => 'id_type_shipment',
                    'sortable' => false,
                    'template' => '@Modules/rj_multicarrier/views/templates/admin/info_shipment/grid/cell/type_select.html.twig',
                    'type_choices' => $typeChoices,
                ]))
            ->add((new InlineEditableColumn('quantity'))
                ->setName($this->transString('Bultos'))
                ->setOptions([
                    'field' => 'quantity',
                    'sortable' => false,
                    'template' => '@Modules/rj_multicarrier/views/templates/admin/info_shipment/grid/cell/input_field.html.twig',
                    'input' => [
                        'field' => 'rj_quantity',
                        'type' => 'number',
                        'min' => 1,
                        'step' => 1,
                    ],
                ]))
            ->add((new InlineEditableColumn('weight'))
                ->setName($this->transString('Peso'))
                ->setOptions([
                    'field' => 'weight',
                    'sortable' => false,
                    'template' => '@Modules/rj_multicarrier/views/templates/admin/info_shipment/grid/cell/input_field.html.twig',
                    'input' => [
                        'field' => 'rj_weight',
                        'type' => 'number',
                        'step' => '0.01',
                    ],
                ]))
            ->add((new InlineEditableColumn('dimensions'))
                ->setName($this->transString('Dimensiones (cm)'))
                ->setOptions([
                    'field' => 'length',
                    'sortable' => false,
                    'template' => '@Modules/rj_multicarrier/views/templates/admin/info_shipment/grid/cell/dimensions_group.html.twig',
                ]))
            ->add((new InlineEditableColumn('cash_ondelivery'))
                ->setName($this->transString('Contra reembolso'))
                ->setOptions([
                    'field' => 'cash_ondelivery',
                    'sortable' => false,
                    'template' => '@Modules/rj_multicarrier/views/templates/admin/info_shipment/grid/cell/input_field.html.twig',
                    'input' => [
                        'field' => 'rj_cash_ondelivery',
                        'type' => 'number',
                        'step' => '0.01',
                    ],
                ]))
            ->add((new InlineEditableColumn('hour_from'))
                ->setName($this->transString('Hora desde'))
                ->setOptions([
                    'field' => 'hour_from',
                    'sortable' => false,
                    'template' => '@Modules/rj_multicarrier/views/templates/admin/info_shipment/grid/cell/input_field.html.twig',
                    'input' => [
                        'field' => 'rj_hour_from',
                        'type' => 'time',
                    ],
                ]))
            ->add((new InlineEditableColumn('hour_until'))
                ->setName($this->transString('Hora hasta'))
                ->setOptions([
                    'field' => 'hour_until',
                    'sortable' => false,
                    'template' => '@Modules/rj_multicarrier/views/templates/admin/info_shipment/grid/cell/input_field.html.twig',
                    'input' => [
                        'field' => 'rj_hour_until',
                        'type' => 'time',
                    ],
                ]))
            ->add((new InlineEditableColumn('retorno'))
                ->setName($this->transString('Retorno'))
                ->setOptions([
                    'field' => 'retorno',
                    'sortable' => false,
                    'template' => '@Modules/rj_multicarrier/views/templates/admin/info_shipment/grid/cell/select_field.html.twig',
                    'select' => [
                        'field' => 'rj_retorno',
                        'choices' => [
                            ['value' => 0, 'label' => $this->transString('Sin retorno')],
                            ['value' => 1, 'label' => $this->transString('Retorno obligatorio')],
                            ['value' => 2, 'label' => $this->transString('Retorno opcional')],
                        ],
                    ],
                ]))
            ->add((new InlineEditableColumn('rcs'))
                ->setName($this->transString('RCS'))
                ->setOptions([
                    'field' => 'rcs',
                    'sortable' => false,
                    'template' => '@Modules/rj_multicarrier/views/templates/admin/info_shipment/grid/cell/checkbox_field.html.twig',
                    'checkbox' => [
                        'field' => 'rj_rcs',
                    ],
                ]))
            ->add((new InlineEditableColumn('employee'))
                ->setName($this->transString('Empleado'))
                ->setOptions([
                    'field' => 'employee_name',
                    'sortable' => false,
                    'template' => '@Modules/rj_multicarrier/views/templates/admin/info_shipment/grid/cell/employee_field.html.twig',
                    'employee_choices' => $employeeChoices,
                ]))
            ->add((new InlineEditableColumn('message'))
                ->setName($this->transString('Mensaje'))
                ->setOptions([
                    'field' => 'message',
                    'sortable' => false,
                    'template' => '@Modules/rj_multicarrier/views/templates/admin/info_shipment/grid/cell/textarea_field.html.twig',
                ]))
            ->add((new DataColumn('date_add'))
                ->setName($this->transString('Creado el'))
                ->setOptions([
                    'field' => 'date_add',
                    'sortable' => true,
                ]))
            ->add((new InlineEditableColumn('inline_actions'))
                ->setName($this->transString('Acciones', [], 'Admin.Global'))
                ->setOptions([
                    'field' => 'id_info_shipment',
                    'sortable' => false,
                    'template' => '@Modules/rj_multicarrier/views/templates/admin/info_shipment/grid/cell/actions.html.twig',
                ]));

        return $columns;
    }

    protected function getFilters(): FilterCollection
    {
        $filters = new FilterCollection();

        $filters
            ->add((new Filter('reference_order', TextType::class))
                ->setTypeOptions([
                    'required' => false,
                    'attr' => [
                        'placeholder' => $this->transString('Reference'),
                    ],
                ])
                ->setAssociatedColumn('reference_order'))
            ->add((new Filter('id_info_shipment', TextType::class))
                ->setTypeOptions([
                    'required' => false,
                    'attr' => [
                        'placeholder' => $this->transString('Package ID'),
                    ],
                ])
                ->setAssociatedColumn('package_id'))
            ->add((new Filter('id_order', TextType::class))
                ->setTypeOptions([
                    'required' => false,
                    'attr' => [
                        'placeholder' => $this->transString('Order ID'),
                    ],
                ])
                ->setAssociatedColumn('order_context'))
            ->add((new Filter('cash_ondelivery', TextType::class))
                ->setTypeOptions([
                    'required' => false,
                    'attr' => [
                        'placeholder' => $this->transString('COD amount'),
                    ],
                ])
                ->setAssociatedColumn('cash_ondelivery'))
            ->add((new Filter('date_add', TextType::class))
                ->setTypeOptions([
                    'required' => false,
                    'attr' => [
                        'placeholder' => $this->transString('YYYY-MM-DD'),
                    ],
                ])
                ->setAssociatedColumn('date_add'))
            ->add((new Filter('actions', SearchAndResetType::class))
                ->setTypeOptions([
                    'reset_route' => 'admin_common_reset_search_by_filter_id',
                    'reset_route_params' => [
                        'filterId' => self::GRID_ID,
                    ],
                    'redirect_route' => 'admin_rj_multicarrier_info_shipments_index',
                ])
                ->setAssociatedColumn('inline_actions'));

        return $filters;
    }


    protected function getGridActions(): GridActionCollectionInterface
    {
        return (new GridActionCollection())
            ->add((new SimpleGridAction('common_refresh_list'))
                    ->setName($this->transString('Refresh list', [], 'Admin.Actions'))
                    ->setIcon('refresh')
            )
            ->add(
                (new SimpleGridAction('common_show_query'))
                    ->setName($this->transString('Show SQL query', [], 'Admin.Actions'))
                    ->setIcon('code')
            )
            ->add((new SimpleGridAction('common_export_sql_manager'))
                    ->setName($this->transString('Export to SQL Manager', [], 'Admin.Actions'))
                    ->setIcon('storage')
            )
            ->add(
                (new SubmitGridAction('export_csv'))
                    ->setName($this->transString('Export CSV'))
                    ->setIcon('download')
                    ->setOptions(['submit_route' => 'admin_rj_multicarrier_info_shipments_export_csv', 'confirm_message' => null])
            );
    }

    protected function getBulkActions(): BulkActionCollection
    {
        $actions = new BulkActionCollection();

        $actions->add((new SubmitBulkAction('export_selected_csv'))
            ->setName($this->transString('Exportar selección (CSV)'))
            ->setOptions([
                'submit_route' => 'admin_rj_multicarrier_info_shipments_export_selected_csv',
            ]));

        $actions->add((new SubmitBulkAction('generate'))
            ->setName($this->transString('Generate shipments'))
            ->setOptions([
                'submit_route' => 'admin_rj_multicarrier_info_shipments_bulk_generate',
                'confirm_message' => $this->transString('Generate shipments for the selected packages?'),
            ]));

        $actions->add(
            $this->buildBulkDeleteAction('admin_rj_multicarrier_info_shipments_delete_bulk')
        );

        return $actions;
    }
}
