<?php
/**
 * Grid definition factory for Configuration listing.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Grid\Configuration;

use PrestaShop\PrestaShop\Core\Grid\Action\Bulk\BulkActionCollection;
use PrestaShop\PrestaShop\Core\Grid\Action\Bulk\Type\SubmitBulkAction;
use PrestaShop\PrestaShop\Core\Grid\Action\GridActionCollection;
use PrestaShop\PrestaShop\Core\Grid\Action\GridActionCollectionInterface;
use PrestaShop\PrestaShop\Core\Grid\Action\Row\RowActionCollection;
use PrestaShop\PrestaShop\Core\Grid\Action\Row\Type\LinkRowAction;
use PrestaShop\PrestaShop\Core\Grid\Action\Type\SimpleGridAction;
use PrestaShop\PrestaShop\Core\Grid\Action\Type\SubmitGridAction;
use PrestaShop\PrestaShop\Core\Grid\Column\ColumnCollection;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\ActionColumn;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\BulkActionColumn;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\DataColumn;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\DateTimeColumn;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\ToggleColumn;
use PrestaShop\PrestaShop\Core\Grid\Definition\Factory\BulkDeleteActionTrait;
use PrestaShop\PrestaShop\Core\Grid\Definition\Factory\DeleteActionTrait;
use PrestaShop\PrestaShop\Core\Grid\Filter\Filter;
use PrestaShop\PrestaShop\Core\Grid\Filter\FilterCollection;
use PrestaShopBundle\Form\Admin\Type\DateRangeType;
use PrestaShopBundle\Form\Admin\Type\SearchAndResetType;
use Roanja\Module\RjMulticarrier\Grid\AbstractModuleGridDefinitionFactory;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;

final class ConfigurationGridDefinitionFactory extends AbstractModuleGridDefinitionFactory
{
    use BulkDeleteActionTrait;
    use DeleteActionTrait;

    public const GRID_ID = 'rj_multicarrier_configuration_shop';

    protected function getId(): string
    {
        return self::GRID_ID;
    }

    protected function getName(): string
    {
        return $this->transString('Remitentes');
    }

    protected function getColumns(): ColumnCollection
    {
        return (new ColumnCollection())
            ->add((new BulkActionColumn('configuration_bulk'))
                ->setOptions([
                    'bulk_field' => 'id_configuration',
                ]))
            ->add((new DataColumn('id_configuration'))
                ->setName($this->transString('ID'))
                ->setOptions([
                    'field' => 'id_configuration',
                ]))
            ->add((new DataColumn('firstname'))
                ->setName($this->transString('Nombre'))
                ->setOptions([
                    'field' => 'firstname',
                ]))
            ->add((new DataColumn('lastname'))
                ->setName($this->transString('Apellidos'))
                ->setOptions([
                    'field' => 'lastname',
                ]))
            ->add((new DataColumn('company'))
                ->setName($this->transString('Empresa'))
                ->setOptions([
                    'field' => 'company',
                ]))
            ->add((new DataColumn('phone'))
                ->setName($this->transString('Teléfono'))
                ->setOptions([
                    'field' => 'phone',
                ]))
            ->add((new DataColumn('email'))
                ->setName($this->transString('Email'))
                ->setOptions([
                    'field' => 'email',
                ]))
            ->add((new DataColumn('shops'))
                ->setName($this->transString('Tiendas'))
                ->setOptions([
                    'field' => 'shops',
                    'sortable' => false,
                ]))
            ->add((new ToggleColumn('active'))
                ->setName($this->transString('Activo'))
                ->setOptions([
                    'field' => 'active',
                    'primary_field' => 'id_configuration',
                    'route' => 'admin_rj_multicarrier_configuration_shop_toggle',
                    'route_param_name' => 'id',
                    'extra_route_params' => [
                        ],
                ]))
            ->add((new DateTimeColumn('date_add'))
                ->setName($this->transString('Creado'))
                ->setOptions([
                    'field' => 'date_add',
                ]))
            ->add((new ActionColumn('actions'))
                ->setName($this->transString('Acciones', [], 'Admin.Global'))
                ->setOptions([
                    'actions' => $this->getRowActions(),
                ]));
    }

    protected function getFilters(): FilterCollection
    {
        return (new FilterCollection())
            ->add((new Filter('id_configuration', TextType::class))
                ->setTypeOptions([
                    'required' => false,
                ])
                ->setAssociatedColumn('id_configuration'))
            ->add((new Filter('firstname', TextType::class))
                ->setTypeOptions([
                    'required' => false,
                ])
                ->setAssociatedColumn('firstname'))
            ->add((new Filter('lastname', TextType::class))
                ->setTypeOptions([
                    'required' => false,
                ])
                ->setAssociatedColumn('lastname'))
            ->add((new Filter('company', TextType::class))
                ->setTypeOptions([
                    'required' => false,
                ])
                ->setAssociatedColumn('company'))
            ->add((new Filter('phone', TextType::class))
                ->setTypeOptions([
                    'required' => false,
                ])
                ->setAssociatedColumn('phone'))
            ->add((new Filter('email', TextType::class))
                ->setTypeOptions([
                    'required' => false,
                ])
                ->setAssociatedColumn('email'))
            ->add((new Filter('shops', TextType::class))
                ->setTypeOptions([
                    'required' => false,
                ])
                ->setAssociatedColumn('shops'))
            ->add((new Filter('active', ChoiceType::class))
                ->setTypeOptions([
                    'required' => false,
                    'placeholder' => $this->transString('Todos', [], 'Admin.Global'),
                    'choices' => [
                        $this->transString('Sí', [], 'Admin.Actions') => 1,
                        $this->transString('No', [], 'Admin.Actions') => 0,
                    ],
                ])
                ->setAssociatedColumn('active'))
            ->add((new Filter('date_add', DateRangeType::class))
                ->setTypeOptions([
                    'required' => false,
                ])
                ->setAssociatedColumn('date_add'))
            ->add((new Filter('actions', SearchAndResetType::class))
                ->setTypeOptions([
                    'reset_route' => 'admin_common_reset_search_by_filter_id',
                    'reset_route_params' => [
                        'filterId' => self::GRID_ID,
                    ],
                    'redirect_route' => 'admin_rj_multicarrier_configuration_shop_index',
                ])
                ->setAssociatedColumn('actions'));
    }

    protected function getGridActions(): GridActionCollectionInterface
    {
        return (new GridActionCollection())
            ->add((new SimpleGridAction('common_refresh_list'))
                ->setName($this->transString('Actualizar', [], 'Admin.Actions'))
                ->setIcon('refresh'))
            ->add((new SimpleGridAction('common_show_query'))
                ->setName($this->transString('Mostrar consulta SQL', [], 'Admin.Actions'))
                ->setIcon('code'))
            ->add((new SimpleGridAction('common_export_sql_manager'))
                ->setName($this->transString('Exportar a gestor SQL', [], 'Admin.Actions'))
                ->setIcon('storage'))
            ->add((new SubmitGridAction('export_csv'))
                ->setName($this->transString('Exportar CSV', [], 'Admin.Actions'))
                ->setIcon('download')
                ->setOptions([
                    'submit_route' => 'admin_rj_multicarrier_configuration_shop_export_csv',
                    'confirm_message' => null,
                ]));
    }

    protected function getBulkActions(): BulkActionCollection
    {
        $bulkActions = new BulkActionCollection();

        $bulkActions->add((new SubmitBulkAction('export_selected_csv'))
            ->setName($this->transString('Exportar selección (CSV)', [], 'Admin.Actions'))
            ->setOptions([
                'submit_route' => 'admin_rj_multicarrier_configuration_shop_export_selected_csv',
            ]));

        $bulkActions->add(
            $this->buildBulkDeleteAction('admin_rj_multicarrier_configuration_shop_delete_bulk')
        );

        return $bulkActions;
    }

    private function getRowActions(): RowActionCollection
    {
        $actions = new RowActionCollection();

        $actions->add((new LinkRowAction('edit'))
            ->setName($this->transString('Editar', [], 'Admin.Actions'))
            ->setIcon('edit')
            ->setOptions([
                'route' => 'admin_rj_multicarrier_configuration_shop_edit',
                'route_param_name' => 'id',
                'route_param_field' => 'id_configuration',
            ]));

        $actions->add(
            $this->buildDeleteAction(
                'admin_rj_multicarrier_configuration_shop_delete',
                'id',
                'id_configuration',
                Request::METHOD_DELETE
            )
        );

        return $actions;
    }
}
