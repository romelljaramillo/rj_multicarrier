<?php

/**
 * Grid definition factory for shipments.
 */

declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Grid\Shipment;

use PrestaShop\PrestaShop\Core\Grid\Action\Bulk\BulkActionCollection;
use PrestaShop\PrestaShop\Core\Grid\Action\Bulk\Type\SubmitBulkAction;
use PrestaShop\PrestaShop\Core\Grid\Action\Row\RowActionCollection;
use PrestaShop\PrestaShop\Core\Grid\Action\GridActionCollection;
use PrestaShop\PrestaShop\Core\Grid\Action\GridActionCollectionInterface;
use PrestaShop\PrestaShop\Core\Grid\Action\Row\Type\LinkRowAction;
use PrestaShop\PrestaShop\Core\Grid\Action\Type\SimpleGridAction;
use PrestaShop\PrestaShop\Core\Grid\Action\Type\SubmitGridAction;
use PrestaShop\PrestaShop\Core\Grid\Column\ColumnCollection;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\ActionColumn;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\BulkActionColumn;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\DataColumn;
use PrestaShop\PrestaShop\Core\Grid\Definition\Factory\BulkDeleteActionTrait;
use PrestaShop\PrestaShop\Core\Grid\Definition\Factory\DeleteActionTrait;
use PrestaShop\PrestaShop\Core\Grid\Filter\Filter;
use PrestaShop\PrestaShop\Core\Grid\Filter\FilterCollection;
use PrestaShopBundle\Form\Admin\Type\DateRangeType;
use PrestaShopBundle\Form\Admin\Type\SearchAndResetType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Roanja\Module\RjMulticarrier\Grid\AbstractModuleGridDefinitionFactory;

final class ShipmentGridDefinitionFactory extends AbstractModuleGridDefinitionFactory
{
    use BulkDeleteActionTrait;
    use DeleteActionTrait;

    public const GRID_ID = 'rj_multicarrier_shipment';

    protected function getId(): string
    {
        return self::GRID_ID;
    }

    protected function getName(): string
    {
        return $this->transString('Shipments');
    }

    protected function getColumns(): ColumnCollection
    {
        $columns = new ColumnCollection();

        $columns
            ->add((new BulkActionColumn('shipment_bulk'))
                ->setOptions([
                    'bulk_field' => 'id_shipment',
                ]))
            ->add((new DataColumn('id_shipment'))
                ->setName($this->transString('ID'))
                ->setOptions([
                    'field' => 'id_shipment',
                ]))
            ->add((new DataColumn('id_order'))
                ->setName($this->transString('Order ID'))
                ->setOptions([
                    'field' => 'id_order',
                ]))
            ->add((new DataColumn('shop_name'))
                ->setName($this->transString('Shop', [], 'Admin.Global'))
                ->setOptions([
                    'field' => 'shop_name',
                ]))
            ->add((new DataColumn('company_shortname'))
                ->setName($this->transString('Carrier'))
                ->setOptions([
                    'field' => 'company_shortname',
                ]))
            ->add((new DataColumn('reference_order'))
                ->setName($this->transString('Order reference'))
                ->setOptions([
                    'field' => 'reference_order',
                ]))
            ->add((new DataColumn('num_shipment'))
                ->setName($this->transString('Shipment number'))
                ->setOptions([
                    'field' => 'num_shipment',
                ]))
            ->add((new DataColumn('product'))
                ->setName($this->transString('Product'))
                ->setOptions([
                    'field' => 'product',
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
            ->add((new DataColumn('printed'))
                ->setName($this->transString('Printed'))
                ->setOptions([
                    'field' => 'printed',
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
        return (new FilterCollection())
            ->add((new Filter('id_shipment', TextType::class))
                ->setTypeOptions([
                    'required' => false,
                    'attr' => [
                        'placeholder' => $this->transString('ID'),
                    ],
                ])
                ->setAssociatedColumn('id_shipment'))
            ->add((new Filter('id_order', TextType::class))
                ->setTypeOptions([
                    'required' => false,
                    'attr' => [
                        'placeholder' => $this->transString('Order ID'),
                    ],
                ])
                ->setAssociatedColumn('id_order'))
            ->add((new Filter('company_shortname', TextType::class))
                ->setTypeOptions([
                    'required' => false,
                    'attr' => [
                        'placeholder' => $this->transString('Carrier'),
                    ],
                ])
                ->setAssociatedColumn('company_shortname'))
            ->add((new Filter('reference_order', TextType::class))
                ->setTypeOptions([
                    'required' => false,
                    'attr' => [
                        'placeholder' => $this->transString('Reference'),
                    ],
                ])
                ->setAssociatedColumn('reference_order'))
            ->add((new Filter('num_shipment', TextType::class))
                ->setTypeOptions([
                    'required' => false,
                    'attr' => [
                        'placeholder' => $this->transString('Shipment'),
                    ],
                ])
                ->setAssociatedColumn('num_shipment'))
            ->add((new Filter('product', TextType::class))
                ->setTypeOptions([
                    'required' => false,
                    'attr' => [
                        'placeholder' => $this->transString('Product'),
                    ],
                ])
                ->setAssociatedColumn('product'))
            ->add((new Filter('cash_ondelivery', TextType::class))
                ->setTypeOptions([
                    'required' => false,
                    'attr' => [
                        'placeholder' => $this->transString('COD amount'),
                    ],
                ])
                ->setAssociatedColumn('cash_ondelivery'))
            ->add((new Filter('printed', ChoiceType::class))
                ->setTypeOptions([
                    'required' => false,
                    'choices' => [
                        $this->transString('Yes', [], 'Admin.Global') => 1,
                        $this->transString('No', [], 'Admin.Global') => 0,
                    ],
                    'placeholder' => $this->transString('Any', [], 'Admin.Global'),
                ])
                ->setAssociatedColumn('printed'))
            ->add((new Filter('id_shop', ChoiceType::class))
                ->setTypeOptions([
                    'required' => false,
                    'choices' => $this->getShopChoices(),
                    'placeholder' => $this->transString('All shops', [], 'Admin.Global'),
                ])
                ->setAssociatedColumn('shop_name'))
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
                    'redirect_route' => 'admin_rj_multicarrier_shipments_index',
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
                    'route' => 'admin_rj_multicarrier_shipments_view',
                    'route_param_name' => 'id',
                    'route_param_field' => 'id_shipment',
                    'attr' => [
                        'class' => 'js-shipment-view-row-action',
                    ],
                ]))
            ->add((new LinkRowAction('print'))
                ->setName($this->transString('Print labels'))
                ->setIcon('print')
                ->setOptions([
                    'route' => 'admin_rj_multicarrier_shipments_print',
                    'route_param_name' => 'id',
                    'route_param_field' => 'id_shipment',
                    'attr' => [
                        'target' => '_blank',
                        'rel' => 'noopener noreferrer',
                    ],
                ]))
            ->add(
                $this->buildDeleteAction(
                    'admin_rj_multicarrier_shipments_delete',
                    'id',
                    'id_shipment',
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
                    ->setOptions(['submit_route' => 'admin_rj_multicarrier_shipments_export_csv', 'confirm_message' => null])
            );
    }

    protected function getBulkActions(): BulkActionCollection
    {
        $actions = new BulkActionCollection();

        $actions->add((new SubmitBulkAction('export_selected_csv'))
            ->setName($this->transString('Exportar selección (CSV)'))
            ->setOptions([
                'submit_route' => 'admin_rj_multicarrier_shipments_export_selected_csv',
            ]));

        $actions->add(
            $this->buildBulkDeleteAction('admin_rj_multicarrier_shipments_delete_bulk')
        );

        return $actions;
    }

    /**
     * @return array<string, int>
     */
    private function getShopChoices(): array
    {
        $choices = [];

        foreach ($this->getAvailableShops() as $shop) {
            if (isset($shop['id_shop'], $shop['name'])) {
                $choices[$shop['name']] = (int) $shop['id_shop'];
            }
        }

        return $choices;
    }

    /**
     * @return array<int, array{id_shop:int,name:string}>
     */
    private function getAvailableShops(): array
    {
        $shopClass = $this->resolveShopClass();

        if ('' === $shopClass || !\is_callable([$shopClass, 'isFeatureActive']) || !\call_user_func([$shopClass, 'isFeatureActive'])) {
            $context = null;
            if (\is_callable(['\\Context', 'getContext'])) {
                $context = \call_user_func(['\\Context', 'getContext']);
            }
            if (isset($context->shop->id, $context->shop->name)) {
                return [[
                    'id_shop' => (int) $context->shop->id,
                    'name' => (string) $context->shop->name,
                ]];
            }

            return [];
        }

        $shops = \call_user_func([$shopClass, 'getShops'], false);

        return \is_array($shops) ? $shops : [];
    }

    private function resolveShopClass(): string
    {
        if (class_exists('\\Shop')) {
            return '\\Shop';
        }

        if (class_exists('\\ShopCore')) {
            return '\\ShopCore';
        }

        return '';
    }
}
