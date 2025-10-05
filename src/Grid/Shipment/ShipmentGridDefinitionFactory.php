<?php
/**
 * Grid definition factory for shipments.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Grid\Shipment;

use PrestaShop\PrestaShop\Core\Grid\Action\Row\RowActionCollection;
use PrestaShop\PrestaShop\Core\Grid\Action\Row\Type\LinkRowAction;
use PrestaShop\PrestaShop\Core\Grid\Column\ColumnCollection;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\ActionColumn;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\DataColumn;
use PrestaShop\PrestaShop\Core\Grid\Definition\Factory\DeleteActionTrait;
use PrestaShop\PrestaShop\Core\Grid\Filter\Filter;
use PrestaShop\PrestaShop\Core\Grid\Filter\FilterCollection;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Roanja\Module\RjMulticarrier\Grid\AbstractModuleGridDefinitionFactory;

final class ShipmentGridDefinitionFactory extends AbstractModuleGridDefinitionFactory
{
    use DeleteActionTrait;

    protected function getId(): string
    {
        return 'rj_multicarrier_shipment';
    }

    protected function getName(): string
    {
        return $this->transString('Shipments');
    }

    protected function getColumns(): ColumnCollection
    {
        $columns = new ColumnCollection();

        $columns
            ->add((new DataColumn('id_order'))
                ->setName($this->transString('Order ID'))
                ->setOptions([
                    'field' => 'id_order',
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
        $filters = new FilterCollection();

        $filters
            ->add((new Filter('id_order', TextType::class))
                ->setTypeOptions([
                    'attr' => [
                        'placeholder' => $this->transString('Order ID'),
                    ],
                ])
                ->setAssociatedColumn('id_order'))
            ->add((new Filter('company_shortname', TextType::class))
                ->setTypeOptions([
                    'attr' => [
                        'placeholder' => $this->transString('Carrier'),
                    ],
                ])
                ->setAssociatedColumn('company_shortname'))
            ->add((new Filter('reference_order', TextType::class))
                ->setTypeOptions([
                    'attr' => [
                        'placeholder' => $this->transString('Reference'),
                    ],
                ])
                ->setAssociatedColumn('reference_order'))
            ->add((new Filter('num_shipment', TextType::class))
                ->setTypeOptions([
                    'attr' => [
                        'placeholder' => $this->transString('Shipment'),
                    ],
                ])
                ->setAssociatedColumn('num_shipment'))
            ->add((new Filter('product', TextType::class))
                ->setTypeOptions([
                    'attr' => [
                        'placeholder' => $this->transString('Product'),
                    ],
                ])
                ->setAssociatedColumn('product'))
            ->add((new Filter('cash_ondelivery', TextType::class))
                ->setTypeOptions([
                    'attr' => [
                        'placeholder' => $this->transString('COD amount'),
                    ],
                ])
                ->setAssociatedColumn('cash_ondelivery'))
            ->add((new Filter('printed', ChoiceType::class))
                ->setTypeOptions([
                    'choices' => [
                        $this->transString('Yes', [], 'Admin.Global') => 1,
                        $this->transString('No', [], 'Admin.Global') => 0,
                    ],
                    'placeholder' => $this->transString('Any', [], 'Admin.Global'),
                ])
                ->setAssociatedColumn('printed'))
            ->add((new Filter('date_add', TextType::class))
                ->setTypeOptions([
                    'attr' => [
                        'placeholder' => $this->transString('YYYY-MM-DD'),
                    ],
                ])
                ->setAssociatedColumn('date_add'))
            ->add((new Filter('limit', ChoiceType::class))
                ->setTypeOptions([
                    'choices' => [
                        20 => 20,
                        50 => 50,
                        100 => 100,
                    ],
                ])
                ->setAssociatedColumn('id_order'));

        return $filters;
    }

    private function getRowActions(): RowActionCollection
    {
        $rowActions = new RowActionCollection();

        $rowActions
            ->add((new LinkRowAction('print'))
                ->setName($this->transString('Print labels'))
                ->setIcon('print')
                ->setOptions([
                    'route' => 'admin_rj_multicarrier_shipments_print',
                    'route_param_name' => 'id',
                    'route_param_field' => 'id_shipment',
                ]))
            ->add($this->buildDeleteAction(
                'admin_rj_multicarrier_shipments_delete',
                'id',
                'id_shipment',
                'POST',
                [
                    '_token' => 'delete_token',
                ],
                [
                    'confirm_message' => $this->transString('Delete this shipment?'),
                ]
            ));

        return $rowActions;
    }
}
