<?php
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Grid\Company;

use PrestaShop\PrestaShop\Core\Grid\Column\ColumnCollection;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\DataColumn;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\ActionColumn;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\BulkActionColumn;
use PrestaShop\PrestaShop\Core\Grid\Action\Row\RowActionCollection;
use PrestaShop\PrestaShop\Core\Grid\Action\Row\Type\LinkRowAction;
use PrestaShop\PrestaShop\Core\Grid\Definition\Factory\BulkDeleteActionTrait;
use PrestaShop\PrestaShop\Core\Grid\Definition\Factory\DeleteActionTrait;
use PrestaShop\PrestaShop\Core\Grid\Filter\Filter;
use PrestaShop\PrestaShop\Core\Grid\Filter\FilterCollection;
use PrestaShopBundle\Form\Admin\Type\SearchAndResetType;
use PrestaShop\PrestaShop\Core\Grid\Action\Bulk\Type\SubmitBulkAction;
use PrestaShop\PrestaShop\Core\Grid\Action\GridActionCollection;
use PrestaShop\PrestaShop\Core\Grid\Action\GridActionCollectionInterface;
use PrestaShop\PrestaShop\Core\Grid\Action\Type\SimpleGridAction;
use PrestaShop\PrestaShop\Core\Grid\Action\Type\SubmitGridAction;
use Roanja\Module\RjMulticarrier\Grid\AbstractModuleGridDefinitionFactory;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;

final class CompanyGridDefinitionFactory extends AbstractModuleGridDefinitionFactory
{
    use BulkDeleteActionTrait;
    use DeleteActionTrait;
    public const GRID_ID = 'rj_multicarrier_company';

    protected function getId(): string
    {
        return self::GRID_ID;
    }

    protected function getName(): string
    {
        return $this->transString('Carrier companies');
    }

    protected function getColumns(): ColumnCollection
    {
        $columns = new ColumnCollection();

        $columns
            ->add((new BulkActionColumn('company_bulk'))
                ->setOptions([
                    'bulk_field' => 'id_carrier_company',
                ]))
            ->add((new DataColumn('id_carrier_company'))
                ->setName($this->transString('ID'))
                ->setOptions(['field' => 'id_carrier_company']))
            ->add((new DataColumn('name'))
                ->setName($this->transString('Name'))
                ->setOptions(['field' => 'name']))
            ->add((new DataColumn('icon'))
                ->setName($this->transString('Icon'))
                ->setOptions(['field' => 'icon']))
            ->add((new DataColumn('shortname'))
                ->setName($this->transString('Short name'))
                ->setOptions(['field' => 'shortname']))
            ->add((new ActionColumn('actions'))
                ->setName($this->transString('Actions', [], 'Admin.Global'))
                ->setOptions([
                    'actions' => $this->getRowActions(),
                ]));

        return $columns;
    }

    protected function getFilters(): \PrestaShop\PrestaShop\Core\Grid\Filter\FilterCollection
    {
        return (new FilterCollection())
            ->add((new Filter('id_carrier_company', TextType::class))
                ->setTypeOptions([
                    'required' => false,
                    'attr' => [
                        'placeholder' => $this->transString('ID'),
                    ],
                ])
                ->setAssociatedColumn('id_carrier_company'))
            ->add((new Filter('name', TextType::class))
                ->setTypeOptions([
                    'required' => false,
                    'attr' => [
                        'placeholder' => $this->transString('Name'),
                    ],
                ])
                ->setAssociatedColumn('name'))
            ->add((new Filter('shortname', TextType::class))
                ->setTypeOptions([
                    'required' => false,
                    'attr' => [
                        'placeholder' => $this->transString('Short name'),
                    ],
                ])
                ->setAssociatedColumn('shortname'))
            ->add((new Filter('actions', SearchAndResetType::class))
                ->setTypeOptions([
                    'reset_route' => 'admin_common_reset_search_by_filter_id',
                    'reset_route_params' => [
                        'filterId' => self::GRID_ID,
                    ],
                    'redirect_route' => 'admin_rj_multicarrier_companies_index',
                ])
                ->setAssociatedColumn('actions'));
    }

    private function getRowActions(): RowActionCollection
    {
        $rowActions = new RowActionCollection();

        $rowActions->add((new LinkRowAction('view'))
            ->setName($this->transString('View', [], 'Admin.Actions'))
            ->setIcon('visibility')
            ->setOptions([
                'route' => 'admin_rj_multicarrier_companies_view',
                'route_param_name' => 'id',
                'route_param_field' => 'id_carrier_company',
                'attr' => ['class' => 'js-company-view-row-action'],
            ]));

        $rowActions->add((new LinkRowAction('configure'))
            ->setName($this->transString('Settings', [], 'Admin.Actions'))
            ->setIcon('settings')
            ->setOptions([
                'route' => 'admin_rj_multicarrier_companies_configuration',
                'route_param_name' => 'id',
                'route_param_field' => 'id_carrier_company',
            ]));

        $rowActions->add((new LinkRowAction('edit'))
            ->setName($this->transString('Edit', [], 'Admin.Actions'))
            ->setIcon('edit')
            ->setOptions([
                'route' => 'admin_rj_multicarrier_companies_edit',
                'route_param_name' => 'id',
                'route_param_field' => 'id_carrier_company',
            ]));

        $rowActions->add(
            $this->buildDeleteAction(
                'admin_rj_multicarrier_companies_delete',
                'id',
                'id_carrier_company',
                Request::METHOD_DELETE
            )
        );

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
                    ->setOptions(['submit_route' => 'admin_rj_multicarrier_companies_export_csv', 'confirm_message' => null])
            );
    }

    protected function getBulkActions(): \PrestaShop\PrestaShop\Core\Grid\Action\Bulk\BulkActionCollection
    {
        $actions = new \PrestaShop\PrestaShop\Core\Grid\Action\Bulk\BulkActionCollection();

        $actions->add((new SubmitBulkAction('export_selected_csv'))
            ->setName($this->transString('Export selection (CSV)'))
            ->setOptions([
                'submit_route' => 'admin_rj_multicarrier_companies_export_selected_csv',
            ]));

        $actions->add(
            $this->buildBulkDeleteAction('admin_rj_multicarrier_companies_delete_bulk')
        );

        return $actions;
    }
}
