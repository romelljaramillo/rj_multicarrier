<?php

/**
 * Grid definition factory for carrier logs.
 */

declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Grid\Log;

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

final class LogGridDefinitionFactory extends AbstractModuleGridDefinitionFactory
{
    use BulkDeleteActionTrait;
    use DeleteActionTrait;

    public const GRID_ID = 'rj_multicarrier_log';

    protected function getId(): string
    {
        return self::GRID_ID;
    }

    protected function getName(): string
    {
        return $this->transString('Carrier logs');
    }

    protected function getColumns(): ColumnCollection
    {
        return (new ColumnCollection())
            ->add((new BulkActionColumn('log_bulk'))
                ->setOptions([
                    'bulk_field' => 'id_carrier_log',
                ]))
            ->add((new DataColumn('id_carrier_log'))
                ->setName($this->transString('ID'))
                ->setOptions([
                    'field' => 'id_carrier_log',
                ]))
            ->add((new DataColumn('name'))
                ->setName($this->transString('Name'))
                ->setOptions([
                    'field' => 'name',
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
            ->add((new DataColumn('date_add'))
                ->setName($this->transString('Created at'))
                ->setOptions([
                    'field' => 'date_add',
                ]))
            ->add((new DataColumn('request_preview'))
                ->setName($this->transString('Request'))
                ->setOptions([
                    'field' => 'request_preview',
                    'sortable' => false,
                ]))
            ->add((new DataColumn('response_preview'))
                ->setName($this->transString('Response'))
                ->setOptions([
                    'field' => 'response_preview',
                    'sortable' => false,
                ]))
            ->add((new ActionColumn('actions'))
                ->setName($this->transString('Actions', [], 'Admin.Global'))
                ->setOptions([
                    'actions' => $this->getRowActions(),
                ]));
    }

    protected function getFilters(): FilterCollection
    {
        return (new FilterCollection())
            ->add((new Filter('id_carrier_log', TextType::class))
                ->setTypeOptions([
                    'required' => false,
                    'attr' => [
                        'placeholder' => $this->transString('ID'),
                    ],
                ])
                ->setAssociatedColumn('id_carrier_log'))
            ->add((new Filter('name', TextType::class))
                ->setTypeOptions([
                    'required' => false,
                    'attr' => [
                        'placeholder' => $this->transString('Name'),
                    ],
                ])
                ->setAssociatedColumn('name'))
            ->add((new Filter('id_order', TextType::class))
                ->setTypeOptions([
                    'required' => false,
                    'attr' => [
                        'placeholder' => $this->transString('Order ID'),
                    ],
                ])
                ->setAssociatedColumn('id_order'))
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
                    'redirect_route' => 'admin_rj_multicarrier_logs_index',
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
                'route' => 'admin_rj_multicarrier_logs_view',
                'route_param_name' => 'id',
                'route_param_field' => 'id_carrier_log',
                'attr' => [
                    'class' => 'js-log-view-row-action',
                ],
            ]))
            ->add(
                $this->buildDeleteAction(
                    'admin_rj_multicarrier_logs_delete',
                    'id',
                    'id_carrier_log',
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
                    ->setOptions(['submit_route' => 'admin_rj_multicarrier_logs_export_csv', 'confirm_message' => null])
            );
    }

    protected function getBulkActions(): BulkActionCollection
    {
        $actions = new BulkActionCollection();

        $actions->add((new SubmitBulkAction('export_selected_csv'))
            ->setName($this->transString('Exportar selección (CSV)'))
            ->setOptions([
                'submit_route' => 'admin_rj_multicarrier_logs_export_selected_csv',
            ]));

        $actions->add(
            $this->buildBulkDeleteAction('admin_rj_multicarrier_logs_delete_bulk')
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
