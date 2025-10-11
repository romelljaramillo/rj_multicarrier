<?php

/**
 * Grid definition factory for info packages pending shipment generation.
 */

declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Grid\InfoPackage;

use PrestaShop\PrestaShop\Core\Grid\Action\Bulk\BulkActionCollection;
use PrestaShop\PrestaShop\Core\Grid\Action\Bulk\Type\SubmitBulkAction;
use PrestaShop\PrestaShop\Core\Grid\Action\GridActionCollection;
use PrestaShop\PrestaShop\Core\Grid\Action\GridActionCollectionInterface;
use PrestaShop\PrestaShop\Core\Grid\Action\Row\RowActionCollection;
use PrestaShop\PrestaShop\Core\Grid\Action\Row\Type\LinkRowAction;
use PrestaShop\PrestaShop\Core\Grid\Action\Row\Type\SubmitRowAction;
use PrestaShop\PrestaShop\Core\Grid\Action\Type\SimpleGridAction;
use PrestaShop\PrestaShop\Core\Grid\Action\Type\SubmitGridAction;
use PrestaShop\PrestaShop\Core\Grid\Column\ColumnCollection;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\ActionColumn;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\DataColumn;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\BulkActionColumn;
use PrestaShop\PrestaShop\Core\Grid\Definition\Factory\BulkDeleteActionTrait;
use PrestaShop\PrestaShop\Core\Grid\Definition\Factory\DeleteActionTrait;
use PrestaShop\PrestaShop\Core\Grid\Filter\Filter;
use PrestaShop\PrestaShop\Core\Grid\Filter\FilterCollection;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use PrestaShopBundle\Form\Admin\Type\SearchAndResetType;
use Roanja\Module\RjMulticarrier\Grid\AbstractModuleGridDefinitionFactory;

final class InfoPackageGridDefinitionFactory extends AbstractModuleGridDefinitionFactory
{
    use BulkDeleteActionTrait;
    use DeleteActionTrait;

    public const GRID_ID = 'rj_multicarrier_info_package';

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
        $columns = new ColumnCollection();

        $columns
            ->add((new BulkActionColumn('info_package_bulk'))
                ->setOptions([
                    'bulk_field' => 'id_infopackage',
                ]))
            ->add((new DataColumn('id_infopackage'))
                ->setName($this->transString('Package ID'))
                ->setOptions([
                    'field' => 'id_infopackage',
                ]))
            ->add((new DataColumn('id_order'))
                ->setName($this->transString('Order ID'))
                ->setOptions([
                    'field' => 'id_order',
                ]))
            ->add((new DataColumn('reference_order'))
                ->setName($this->transString('Order reference'))
                ->setOptions([
                    'field' => 'reference_order',
                ]))
            ->add((new DataColumn('carrier_name'))
                ->setName($this->transString('Carrier'))
                ->setOptions([
                    'field' => 'carrier_name',
                ]))
            ->add((new DataColumn('company_shortname'))
                ->setName($this->transString('Company'))
                ->setOptions([
                    'field' => 'company_shortname',
                ]))
            ->add((new DataColumn('cash_ondelivery'))
                ->setName($this->transString('Cash on delivery'))
                ->setOptions([
                    'field' => 'cash_ondelivery',
                ]))
            ->add((new DataColumn('quantity'))
                ->setName($this->transString('Packages'))
                ->setOptions([
                    'field' => 'quantity',
                ]))
            ->add((new DataColumn('weight'))
                ->setName($this->transString('Weight'))
                ->setOptions([
                    'field' => 'weight',
                ]))
            ->add((new DataColumn('date_add'))
                ->setName($this->transString('Created at'))
                ->setOptions([
                    'field' => 'date_add',
                ]))
            ->add((new ActionColumn('actions'))
                ->setName($this->transString('Actions', [], 'Admin.Global'))
                ->setOptions([
                    'actions' => $this->getRowActions(),
                ]));

        return $columns;
    }

    protected function getFilters(): FilterCollection
    {
        $filters = new FilterCollection();

        $filters
            ->add((new Filter('id_infopackage', TextType::class))
                ->setTypeOptions([
                    'attr' => [
                        'placeholder' => $this->transString('Package ID'),
                    ],
                ])
                ->setAssociatedColumn('id_infopackage'))
            ->add((new Filter('id_order', TextType::class))
                ->setTypeOptions([
                    'attr' => [
                        'placeholder' => $this->transString('Order ID'),
                    ],
                ])
                ->setAssociatedColumn('id_order'))
            ->add((new Filter('reference_order', TextType::class))
                ->setTypeOptions([
                    'attr' => [
                        'placeholder' => $this->transString('Reference'),
                    ],
                ])
                ->setAssociatedColumn('reference_order'))
            ->add((new Filter('carrier_name', TextType::class))
                ->setTypeOptions([
                    'attr' => [
                        'placeholder' => $this->transString('Carrier'),
                    ],
                ])
                ->setAssociatedColumn('carrier_name'))
            ->add((new Filter('company_shortname', TextType::class))
                ->setTypeOptions([
                    'attr' => [
                        'placeholder' => $this->transString('Company'),
                    ],
                ])
                ->setAssociatedColumn('company_shortname'))
            ->add((new Filter('cash_ondelivery', TextType::class))
                ->setTypeOptions([
                    'attr' => [
                        'placeholder' => $this->transString('COD amount'),
                    ],
                ])
                ->setAssociatedColumn('cash_ondelivery'))
            ->add((new Filter('date_add', TextType::class))
                ->setTypeOptions([
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
                    'redirect_route' => 'admin_rj_multicarrier_info_packages_index',
                ])
                ->setAssociatedColumn('actions'));

        return $filters;
    }

    private function getRowActions(): RowActionCollection
    {
        $rowActions = new RowActionCollection();

        $rowActions
            ->add((new LinkRowAction('view'))
                ->setName($this->transString('View', [], 'Admin.Actions'))
                ->setIcon('visibility')
                ->setOptions([
                    'route' => 'admin_rj_multicarrier_info_packages_view',
                    'route_param_name' => 'id',
                    'route_param_field' => 'id_infopackage',
                    'attr' => [
                        'class' => 'js-info-package-view-row-action',
                    ],
                ]))
            ->add((new SubmitRowAction('generate'))
                ->setName($this->transString('Generate shipment'))
                ->setIcon('local_shipping')
                ->setOptions([
                    'route' => 'admin_rj_multicarrier_info_packages_generate',
                    'route_param_name' => 'infoPackageId',
                    'route_param_field' => 'id_infopackage',
                    'confirm_message' => $this->transString('Generate shipment for this package?'),
                ]));

        return $rowActions;
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
                    ->setOptions(['submit_route' => 'admin_rj_multicarrier_info_packages_export_csv', 'confirm_message' => null])
            );
    }

    protected function getBulkActions(): BulkActionCollection
    {
        $actions = new BulkActionCollection();

        $actions->add((new SubmitBulkAction('export_selected_csv'))
            ->setName($this->transString('Exportar selección (CSV)'))
            ->setOptions([
                'submit_route' => 'admin_rj_multicarrier_info_packages_export_selected_csv',
            ]));

        $actions->add((new SubmitBulkAction('generate'))
            ->setName($this->transString('Generate shipments'))
            ->setOptions([
                'submit_route' => 'admin_rj_multicarrier_info_packages_bulk_generate',
                'confirm_message' => $this->transString('Generate shipments for the selected packages?'),
            ]));

        $actions->add(
            $this->buildBulkDeleteAction('admin_rj_multicarrier_info_packages_delete_bulk')
        );

        return $actions;
    }
}
