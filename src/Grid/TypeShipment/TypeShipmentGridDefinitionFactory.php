<?php
/**
 * Grid definition factory for type shipments.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Grid\TypeShipment;

use PrestaShop\PrestaShop\Core\Grid\Action\Row\RowActionCollection;
use PrestaShop\PrestaShop\Core\Grid\Action\Row\Type\LinkRowAction;
use PrestaShop\PrestaShop\Core\Grid\Action\Row\Type\SubmitRowAction;
use PrestaShop\PrestaShop\Core\Grid\Column\ColumnCollection;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\ActionColumn;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\DataColumn;
use PrestaShop\PrestaShop\Core\Grid\Column\Type\Common\ToggleColumn;
use PrestaShop\PrestaShop\Core\Grid\Definition\Factory\DeleteActionTrait;
use PrestaShop\PrestaShop\Core\Grid\Filter\Filter;
use PrestaShop\PrestaShop\Core\Grid\Filter\FilterCollection;
use PrestaShop\PrestaShop\Core\Hook\HookDispatcherInterface;
use PrestaShopBundle\Form\Admin\Type\SearchAndResetType;
use Roanja\Module\RjMulticarrier\Grid\AbstractModuleGridDefinitionFactory;
use Roanja\Module\RjMulticarrier\Repository\CompanyRepository;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

final class TypeShipmentGridDefinitionFactory extends AbstractModuleGridDefinitionFactory
{
    use DeleteActionTrait;

    public const GRID_ID = 'rj_multicarrier_type_shipment';

    public function __construct(
        HookDispatcherInterface $hookDispatcher,
        private readonly CompanyRepository $companyRepository
    ) {
        parent::__construct($hookDispatcher);
    }

    protected function getId(): string
    {
        return self::GRID_ID;
    }

    protected function getName(): string
    {
        return $this->transString('Shipment types');
    }

    protected function getColumns(): ColumnCollection
    {
        $columns = new ColumnCollection();

        $columns
            ->add((new DataColumn('id_type_shipment'))
                ->setName($this->transString('ID'))
                ->setOptions([
                    'field' => 'id_type_shipment',
                ]))
            ->add((new DataColumn('company_name'))
                ->setName($this->transString('Company'))
                ->setOptions([
                    'field' => 'company_name',
                ]))
            ->add((new DataColumn('name'))
                ->setName($this->transString('Name'))
                ->setOptions([
                    'field' => 'name',
                ]))
            ->add((new DataColumn('id_bc'))
                ->setName($this->transString('Business code'))
                ->setOptions([
                    'field' => 'id_bc',
                ]))
            ->add((new DataColumn('id_reference_carrier'))
                ->setName($this->transString('Reference carrier'))
                ->setOptions([
                    'field' => 'id_reference_carrier',
                ]))
            ->add((new ToggleColumn('active'))
                ->setName($this->transString('Active'))
                ->setOptions([
                    'field' => 'active',
                    'primary_field' => 'id_type_shipment',
                    'route' => 'admin_rj_multicarrier_type_shipment_toggle',
                    'route_param_name' => 'id',
                    'extra_route_params' => [
                        'company' => 'id_carrier_company',
                        '_token' => 'toggle_token',
                    ],
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
            ->add((new Filter('id_type_shipment', TextType::class))
                ->setTypeOptions([
                    'attr' => [
                        'placeholder' => $this->transString('ID'),
                    ],
                ])
                ->setAssociatedColumn('id_type_shipment'))
            ->add((new Filter('company_name', TextType::class))
                ->setTypeOptions([
                    'attr' => [
                        'placeholder' => $this->transString('Company'),
                    ],
                ])
                ->setAssociatedColumn('company_name'))
            ->add((new Filter('name', TextType::class))
                ->setTypeOptions([
                    'attr' => [
                        'placeholder' => $this->transString('Name'),
                    ],
                ])
                ->setAssociatedColumn('name'))
            ->add((new Filter('id_bc', TextType::class))
                ->setTypeOptions([
                    'attr' => [
                        'placeholder' => $this->transString('Business code'),
                    ],
                ])
                ->setAssociatedColumn('id_bc'))
            ->add((new Filter('active', ChoiceType::class))
                ->setTypeOptions([
                    'choices' => [
                        $this->transString('Yes', [], 'Admin.Global') => 1,
                        $this->transString('No', [], 'Admin.Global') => 0,
                    ],
                    'placeholder' => $this->transString('Any', [], 'Admin.Global'),
                ])
                ->setAssociatedColumn('active'))
            ->add((new Filter('company_id', ChoiceType::class))
                ->setTypeOptions([
                    'choices' => $this->getCompanyFilterChoices(),
                    'placeholder' => $this->transString('All companies'),
                    'required' => false,
                ])
                ->setAssociatedColumn('company_name'))
            ->add((new Filter('limit', ChoiceType::class))
                ->setTypeOptions([
                    'choices' => [
                        20 => 20,
                        50 => 50,
                        100 => 100,
                    ],
                ])
                ->setAssociatedColumn('id_type_shipment'))
            ->add((new Filter('actions', SearchAndResetType::class))
                ->setTypeOptions([
                    'reset_route' => 'admin_common_reset_search_by_filter_id',
                    'reset_route_params' => [
                        'filterId' => $this->getId(),
                    ],
                    'redirect_route' => 'admin_rj_multicarrier_type_shipment_index',
                ])
                ->setAssociatedColumn('actions'));

        return $filters;
    }

    /**
     * @return RowActionCollection
     */
    private function getRowActions(): RowActionCollection
    {
        $rowActions = new RowActionCollection();

        $rowActions
            ->add((new LinkRowAction('edit'))
                ->setName($this->transString('Edit', [], 'Admin.Actions'))
                ->setIcon('edit')
                ->setOptions([
                    'route' => 'admin_rj_multicarrier_type_shipment_index',
                    'route_param_name' => 'id',
                    'route_param_field' => 'id_type_shipment',
                    'extra_route_params' => [
                        'company' => 'id_carrier_company',
                    ],
                ]))
            ->add($this->buildDeleteAction(
                'admin_rj_multicarrier_type_shipment_delete',
                'id',
                'id_type_shipment',
                'POST',
                [
                    'company' => 'id_carrier_company',
                    '_token' => 'delete_token',
                ],
                [
                    'confirm_message' => $this->transString('Delete this shipment type?'),
                ]
            ));

        return $rowActions;
    }

    private function getCompanyFilterChoices(): array
    {
        $choices = [];

        foreach ($this->companyRepository->findAllOrdered() as $company) {
            if (null === $company->getId()) {
                continue;
            }

            $choices[$company->getName()] = $company->getId();
        }

        return $choices;
    }
}
