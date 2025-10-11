<?php
/**
 * Carrier company configuration and shipment orchestration.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Carrier;

use Carrier;
use Configuration;
use Context;
use DateTimeInterface;
use Db;
use Doctrine\ORM\EntityManagerInterface;
use HelperForm;
use HelperList;
use InvalidArgumentException;
use Language;
use Module;
use Order;
use PrestaShop\PrestaShop\Adapter\SymfonyContainer;
use Roanja\Module\RjMulticarrier\Domain\InfoPackage\Command\UpsertInfoPackageCommand;
use Roanja\Module\RjMulticarrier\Domain\InfoPackage\Handler\UpsertInfoPackageHandler;
use Roanja\Module\RjMulticarrier\Domain\InfoShop\Command\UpsertInfoShopCommand;
use Roanja\Module\RjMulticarrier\Domain\InfoShop\Handler\UpsertInfoShopHandler;
use Roanja\Module\RjMulticarrier\Domain\Log\Command\CreateLogEntryCommand;
use Roanja\Module\RjMulticarrier\Domain\Log\Handler\CreateLogEntryHandler;
use Roanja\Module\RjMulticarrier\Domain\Shipment\Command\CreateShipmentCommand;
use Roanja\Module\RjMulticarrier\Domain\Shipment\Handler\CreateShipmentHandler;
use Roanja\Module\RjMulticarrier\Domain\Shipment\Command\GenerateShipmentCommand;
use Roanja\Module\RjMulticarrier\Domain\Shipment\Handler\GenerateShipmentHandler;
use Roanja\Module\RjMulticarrier\Domain\TypeShipment\Command\DeleteTypeShipmentCommand;
use Roanja\Module\RjMulticarrier\Domain\TypeShipment\Command\ToggleTypeShipmentStatusCommand;
use Roanja\Module\RjMulticarrier\Domain\TypeShipment\Command\UpsertTypeShipmentCommand;
use Roanja\Module\RjMulticarrier\Domain\TypeShipment\Exception\TypeShipmentException;
use Roanja\Module\RjMulticarrier\Domain\TypeShipment\Handler\DeleteTypeShipmentHandler;
use Roanja\Module\RjMulticarrier\Domain\TypeShipment\Handler\ToggleTypeShipmentStatusHandler;
use Roanja\Module\RjMulticarrier\Domain\TypeShipment\Handler\UpsertTypeShipmentHandler as UpsertTypeShipmentHandlerDomain;
use Roanja\Module\RjMulticarrier\Entity\Company;
use Roanja\Module\RjMulticarrier\Entity\InfoPackage as InfoPackageEntity;
use Roanja\Module\RjMulticarrier\Entity\Label as LabelEntity;
use Roanja\Module\RjMulticarrier\Entity\LogEntry as LogEntryEntity;
use Roanja\Module\RjMulticarrier\Entity\Shipment as ShipmentEntity;
use Roanja\Module\RjMulticarrier\Entity\TypeShipment;
use Roanja\Module\RjMulticarrier\Pdf\RjPDF;
use Roanja\Module\RjMulticarrier\Support\Common;
use Roanja\Module\RjMulticarrier\Repository\CompanyRepository;
use Roanja\Module\RjMulticarrier\Repository\TypeShipmentRepository;
use RuntimeException;
use Shop;
use Tools;
use Validate;

/**
 * Class CarrierCompany.
 */
class CarrierCompany extends Module
{
    /** @var string Nombre unico de transportista */
    public $name_carrier = 'name carrier';

    /** @var string Nombre corto del transportista siglas ejemp: CEX Correo Express */
    public $shortname = 'DEF';
    public $display_pdf = 'S';
    public $label_type = 'B2X_Generic_A4_Third';
    public $show_create_label = false;

    /** @var array Campos de configuración */
    protected $fields_config = [];

    protected $fields_config_info_extra = [
        [
            'name' => 'RJ_ETIQUETA_TRANSP_PREFIX',
            'require' => false,
            'type' => 'string',
        ],
        [
            'name' => 'RJ_MODULE_CONTRAREEMBOLSO',
            'require' => true,
            'type' => 'float',
        ],
    ];

    /** @var array Campos del formulario configuración */
    public $fields_form;

    public $fields_form_extra;

    public $context;
    public $_html;

    private static ?UpsertInfoPackageHandler $upsertInfoPackageHandler = null;

    private ?CreateShipmentHandler $createShipmentHandler = null;

    private ?GenerateShipmentHandler $generateShipmentHandler = null;

    private ?EntityManagerInterface $entityManager = null;

    private static ?CreateLogEntryHandler $createLogEntryHandler = null;

    private static ?UpsertTypeShipmentHandlerDomain $upsertTypeShipmentHandler = null;

    private static ?ToggleTypeShipmentStatusHandler $toggleTypeShipmentStatusHandler = null;

    private static ?DeleteTypeShipmentHandler $deleteTypeShipmentHandler = null;

    private static ?CompanyRepository $companyRepository = null;

    private static ?TypeShipmentRepository $typeShipmentRepository = null;

    private static ?UpsertInfoShopHandler $upsertInfoShopHandlerService = null;

    public function __construct()
    {
        $this->module = 'rj_multicarrier';
        $this->name = 'rj_multicarrier';
        parent::__construct();
    }

    public function renderConfig()
    {
        if (
            Tools::isSubmit('add_Type_shipment_' . $this->shortname)
            || (Tools::isSubmit('update_type_shipment_' . $this->shortname)
                && Tools::isSubmit('id_type_shipment')
                && self::typeShipmentExists((int) Tools::getValue('id_type_shipment')))
        ) {
            $this->_html .= $this->renderFormTypeShipment();
        } else {
            $this->_postProcess();

            $this->_html .= $this->renderFormConfig();
            $this->_html .= $this->viewAddTypeShipment();
            $this->_html .= $this->renderListTypeShipment();
        }

        return $this->_html;
    }

    public function viewAddTypeShipment()
    {
        $add = 'add_Type_shipment_' . $this->shortname;
        $this->context->smarty->assign([
            'link' => $this->context->link->getAdminLink('AdminModules', true, [], [
                'configure' => $this->module,
                'tab_module' => $this->tab,
                'tab_form' => $this->shortname,
                $add => '1',
            ]),
            'company' => $this->shortname,
        ]);

        return $this->display($this->_path, '/views/templates/hook/create-type-shipment.tpl');
    }

    public function getConfigFieldsExtra()
    {
        return $this->fields_config_info_extra;
    }

    public function getFieldsFormConfigExtra()
    {
        $modulesPay = self::getModulesPay();
        $modules_array[] = [
            'id' => '',
            'name' => '',
        ];
        foreach ($modulesPay as $module) {
            $modules_array[] = [
                'id' => $module['name'],
                'name' => $module['name'],
            ];
        }

        return [
            [
                'type' => 'text',
                'label' => $this->l('Prefix etiqueta'),
                'name' => 'RJ_ETIQUETA_TRANSP_PREFIX',
                'class' => 'fixed-width-lg',
            ],
            [
                'type' => 'select',
                'label' => $this->l('Module contrareembolso'),
                'name' => 'RJ_MODULE_CONTRAREEMBOLSO',
                'options' => [
                    'query' => $modules_array,
                    'id' => 'id',
                    'name' => 'name',
                ],
            ],
        ];
    }

    /**
     * Valida que los carriers seleccionados hayan sido configurados.
     */
    public function validationConfiguration()
    {
        $id_shop = Context::getContext()->shop->id;
        $id_shop_group = Context::getContext()->shop->id_shop_group;
        $warning = [];

        // valida los campos de configuaración de los carrier company
        foreach ($this->fields_config as $value) {
            if ($value['require'] && !Configuration::get($value['name'], null, $id_shop_group, $id_shop)) {
                $warning[] = $this->getTranslator()->trans('Required data module configuration!. ', [], 'Modules.RjMulticarrier.Admin')
                    . $value['name'];
            }
        }

        // valida los campos de configuaración extra de los carrier company
        foreach ($this->fields_config_info_extra as $value) {
            if ($value['require'] && !Configuration::get($value['name'], null, $id_shop_group, $id_shop)) {
                $warning[] = $this->getTranslator()->trans('Required data the module extra configuration!. ', [], 'Modules.RjMulticarrier.Admin')
                    . $value['name'];
            }
        }

        if (count($warning)) {
            return $warning;
        }

        return false;
    }

    protected function _postProcess(): void
    {
        if (Tools::isSubmit('submitConfigTypeShipment' . $this->shortname)) {
            $this->_postProcessTypeShipment();
        } elseif (Tools::isSubmit('submitConfig' . $this->shortname)) {
            $res = true;
            $shop_context = Shop::getContext();

            $shop_groups_list = [];
            $shops = Shop::getContextListShopID();

            foreach ($shops as $shop_id) {
                $shop_group_id = (int) Shop::getGroupFromShop($shop_id, true);

                if (!in_array($shop_group_id, $shop_groups_list)) {
                    $shop_groups_list[] = $shop_group_id;
                }

                foreach ($this->fields_config as $field) {
                    if ($field['type'] === 'password') {
                        if (Tools::getValue($field['name'])) {
                            $res &= Configuration::updateValue(
                                $field['name'],
                                Common::encrypt('encrypt', Tools::getValue($field['name'])),
                                false,
                                $shop_group_id,
                                $shop_id
                            );
                        }
                    } else {
                        $res &= Configuration::updateValue(
                            $field['name'],
                            Tools::getValue($field['name']),
                            false,
                            $shop_group_id,
                            $shop_id
                        );
                    }
                }
            }

            switch ($shop_context) {
                case Shop::CONTEXT_ALL:
                    foreach ($this->fields_config as $field) {
                        if ($field['type'] === 'password') {
                            if (Tools::getValue($field['name'])) {
                                $res &= Configuration::updateValue(
                                    $field['name'],
                                    Common::encrypt('encrypt', Tools::getValue($field['name']))
                                );
                            }
                        } else {
                            $res &= Configuration::updateValue($field['name'], Tools::getValue($field['name']));
                        }
                    }

                    if (count($shop_groups_list)) {
                        foreach ($shop_groups_list as $shop_group_id) {
                            foreach ($this->fields_config as $field) {
                                if ($field['type'] === 'password') {
                                    if (Tools::getValue($field['name'])) {
                                        $res &= Configuration::updateValue(
                                            $field['name'],
                                            Common::encrypt('encrypt', Tools::getValue($field['name'])),
                                            false,
                                            $shop_group_id
                                        );
                                    }
                                } else {
                                    $res &= Configuration::updateValue(
                                        $field['name'],
                                        Tools::getValue($field['name']),
                                        false,
                                        $shop_group_id
                                    );
                                }
                            }
                        }
                    }
                    break;
                case Shop::CONTEXT_GROUP:
                    if (count($shop_groups_list)) {
                        foreach ($shop_groups_list as $shop_group_id) {
                            foreach ($this->fields_config as $field) {
                                if ($field['type'] === 'password') {
                                    if (Tools::getValue($field['name'])) {
                                        $res &= Configuration::updateValue(
                                            $field['name'],
                                            Common::encrypt('encrypt', Tools::getValue($field['name'])),
                                            false,
                                            $shop_group_id
                                        );
                                    }
                                } else {
                                    $res &= Configuration::updateValue(
                                        $field['name'],
                                        Tools::getValue($field['name']),
                                        false,
                                        $shop_group_id
                                    );
                                }
                            }
                        }
                    }
                    break;
            }

            if (!$res) {
                $this->_html .= $this->displayError($this->l('The Configuration could not be added.'));
            } else {
                Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules', true, [], [
                    'configure' => $this->name,
                    'tab_module' => $this->tab,
                    'conf' => 6,
                    'module_name' => $this->name,
                    'tab_form' => $this->shortname,
                ]));
            }
        } elseif (Tools::isSubmit('status_type_shipment_' . $this->shortname)) {
            $typeShipmentId = (int) Tools::getValue('id_type_shipment');

            try {
                self::getToggleTypeShipmentStatusHandler()->handle(
                    new ToggleTypeShipmentStatusCommand($typeShipmentId)
                );

                Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules', true, [], [
                    'configure' => $this->name,
                    'tab_module' => $this->tab,
                    'conf' => 4,
                    'module_name' => $this->name,
                    'tab_form' => $this->shortname,
                ]));
            } catch (TypeShipmentException $exception) {
                $this->_html .= $this->displayError($exception->getMessage());
            }
        } elseif (Tools::isSubmit('delete_type_shipment_' . $this->shortname)) {
            $typeShipmentId = (int) Tools::getValue('id_type_shipment');

            try {
                self::getDeleteTypeShipmentHandler()->handle(new DeleteTypeShipmentCommand($typeShipmentId));

                Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules', true, [], [
                    'configure' => $this->name,
                    'tab_module' => $this->tab,
                    'conf' => 1,
                    'module_name' => $this->name,
                    'tab_form' => $this->shortname,
                ]));
            } catch (TypeShipmentException $exception) {
                $this->_html .= $this->displayError($exception->getMessage());
            }
        }
    }

    protected function _postProcessTypeShipment(): void
    {
        $typeShipmentIdRaw = Tools::getValue('id_type_shipment');
        $typeShipmentId = $typeShipmentIdRaw !== null && $typeShipmentIdRaw !== '' ? (int) $typeShipmentIdRaw : null;

        $companyId = (int) Tools::getValue('id_carrier_company');
        $name = (string) Tools::getValue('name');
        $businessCode = (string) Tools::getValue('id_bc');

        $referenceRaw = Tools::getValue('id_reference_carrier');
        $referenceCarrierId = $referenceRaw !== null && $referenceRaw !== '' ? (int) $referenceRaw : null;

        $active = (bool) Tools::getValue('active');

        try {
            self::getUpsertTypeShipmentHandler()->handle(
                new UpsertTypeShipmentCommand(
                    $typeShipmentId,
                    $companyId,
                    $name,
                    $businessCode,
                    $referenceCarrierId,
                    $active
                )
            );
        } catch (TypeShipmentException $exception) {
            $this->_html .= $this->displayError($exception->getMessage());

            return;
        }

        $redirectParams = [
            'configure' => $this->name,
            'tab_module' => $this->tab,
            'module_name' => $this->name,
            'tab_form' => $this->shortname,
        ];

        $redirectParams['conf'] = $typeShipmentId === null ? 3 : 6;

        Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules', true, [], $redirectParams));
    }

    /**
     * Devuelve shortname company a partir del id_reference_carrier.
     */
    public static function getInfoCompanyByIdReferenceCarrier($id_reference_carrier)
    {
        $typeShipment = self::getTypeShipmentRepository()->findActiveByReferenceCarrier((int) $id_reference_carrier);

        if ($typeShipment instanceof TypeShipment) {
            return self::mapCompanyToArray($typeShipment->getCompany());
        }

        $companies = self::getAllCarrierCompanies();

        return $companies[0] ?? [];
    }

    public function renderFormConfig()
    {
        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG')
            ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG')
            : 0;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitConfig' . $this->shortname;
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->module . '&tab_module=' . $this->tab . '&module_name=' . $this->module;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $helper->generateForm([$this->fields_form]);
    }

    /**
     * Devuelve el listado de type shipment.
     */
    private function renderListTypeShipment()
    {
        $carrierCompany = self::getCarrierCompanyByShortname($this->shortname);

        if (!$carrierCompany) {
            return null;
        }

        $carrierTypeShipments = self::getTypeShipmentsForCompany((int) $carrierCompany['id_carrier_company']);

        if (!$carrierTypeShipments) {
            return null;
        }

        $fields_list = [
            'id_type_shipment' => [
                'title' => $this->l('Id'),
                'width' => 140,
                'type' => 'text',
            ],
            'name' => [
                'title' => $this->l('Name'),
                'width' => 140,
                'type' => 'text',
            ],
            'carrier_company' => [
                'title' => $this->l('Company'),
                'width' => 140,
                'type' => 'text',
            ],
            'shortname' => [
                'title' => $this->l('shortname'),
                'width' => 140,
                'type' => 'text',
            ],
            'id_bc' => [
                'title' => $this->l('id bc'),
                'width' => 140,
                'type' => 'text',
            ],
            'reference_carrier' => [
                'title' => $this->l('reference carrier'),
                'width' => 140,
                'type' => 'text',
            ],
            'active' => [
                'title' => $this->l('active'),
                'active' => 'status',
                'type' => 'bool',
            ],
        ];

        $helper_list = new HelperList();
        $helper_list->module = $this;
        $helper_list->title = $this->trans('Type shipment', [], 'Modules.rj_multicarrier.Admin');
        $helper_list->shopLinkType = '';
        $helper_list->no_link = false;
        $helper_list->show_toolbar = true;
        $helper_list->simple_header = true;
        $helper_list->identifier = 'id_type_shipment';
        $helper_list->table = '_type_shipment_' . $this->shortname;
        $helper_list->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_form=' . $this->shortname;
        $helper_list->token = Tools::getAdminTokenLite('AdminModules');
        $helper_list->actions = ['edit', 'delete'];

        $helper_list->listTotal = count($carrierTypeShipments);

        return $helper_list->generateList($carrierTypeShipments, $fields_list);
    }

    public function renderFormTypeShipment()
    {
        $carriers = Carrier::getCarriers((int) $this->context->language->id);
        $fieldsValuesTypeShipment = $this->getConfigFieldsValuesTypeShipment();

        $carrier_array[] = [
            'id' => '',
            'name' => '',
        ];

        foreach ($carriers as $carrier) {
            if (
                $fieldsValuesTypeShipment['id_reference_carrier'] == $carrier['id_reference']
                || !self::typeShipmentExistsByReference((int) $carrier['id_reference'])
            ) {
                $carrier_array[] = [
                    'id' => $carrier['id_reference'],
                    'name' => $carrier['name'],
                ];
            }
        }

        $carrier_company = self::getCarrierCompanyByShortname($this->shortname);

        if ($carrier_company) {
            $company_array[] = [
                'id' => $carrier_company['id_carrier_company'],
                'name' => $carrier_company['name'],
            ];
        } else {
            $company_array = [];
        }

        $fields_form = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Type shipment relations'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->l('Name'),
                        'name' => 'name',
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Select Company'),
                        'name' => 'id_carrier_company',
                        'options' => [
                            'query' => $company_array,
                            'id' => 'id',
                            'name' => 'name',
                        ],
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('id bc'),
                        'name' => 'id_bc',
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Select carrier'),
                        'name' => 'id_reference_carrier',
                        'desc' => $this->l('Solo se veran transportistas los que no han sido asignados'),
                        'options' => [
                            'query' => $carrier_array,
                            'id' => 'id',
                            'name' => 'name',
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->getTranslator()->trans('Enabled', [], 'Admin.Global'),
                        'name' => 'active',
                        'is_bool' => true,
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->getTranslator()->trans('Yes', [], 'Admin.Global'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->getTranslator()->trans('No', [], 'Admin.Global'),
                            ],
                        ],
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                ],
            ],
        ];

        if (
            Tools::isSubmit('id_type_shipment')
            && self::typeShipmentExists((int) Tools::getValue('id_type_shipment'))
        ) {
            $fields_form['form']['input'][] = ['type' => 'hidden', 'name' => 'id_type_shipment'];
        }

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->show_cancel_button = true;
        $helper->table = $this->table;
        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG')
            ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG')
            : 0;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitConfigTypeShipment' . $this->shortname;
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->module . '&tab_module=' . $this->tab . '&tab_form=' . $this->shortname;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = [
            'fields_value' => $fieldsValuesTypeShipment,
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $helper->generateForm([$fields_form]);
    }

    public function getConfigFieldsValuesTypeShipment(): array
    {
        $fields = [];
        $typeShipmentData = null;

        if (
            Tools::isSubmit('id_type_shipment')
            && self::typeShipmentExists((int) Tools::getValue('id_type_shipment'))
        ) {
            $typeShipmentData = self::getTypeShipmentDataById((int) Tools::getValue('id_type_shipment'));

            if (null !== $typeShipmentData) {
                $fields['id_type_shipment'] = (int) Tools::getValue('id_type_shipment', $typeShipmentData['id_type_shipment']);
            }
        }

        $defaults = [
            'id_carrier_company' => $typeShipmentData['id_carrier_company'] ?? null,
            'name' => $typeShipmentData['name'] ?? '',
            'id_bc' => $typeShipmentData['id_bc'] ?? '',
            'id_reference_carrier' => $typeShipmentData['id_reference_carrier'] ?? null,
            'active' => isset($typeShipmentData['active']) ? (bool) $typeShipmentData['active'] : true,
        ];

        foreach ($defaults as $key => $value) {
            $fields[$key] = Tools::getValue($key, $value);
        }

        return $fields;
    }

    /**
     * Obtiene los datos de configuración.
     */
    public function getConfigFieldsValues(): array
    {
        $id_shop_group = Shop::getContextShopGroupID();
        $id_shop = Shop::getContextShopID();
        $fields = [];

        foreach ($this->fields_config as $field) {
            if ($field['type'] === 'password') {
                $fields[$field['name']] = Tools::getValue(
                    $field['name'],
                    Common::encrypt('decrypt', Configuration::get($field['name'], null, $id_shop_group, $id_shop))
                );
            } else {
                $fields[$field['name']] = Tools::getValue(
                    $field['name'],
                    Configuration::get($field['name'], null, $id_shop_group, $id_shop)
                );
            }
        }

        return $fields;
    }

    public function createShipment($shipment): bool
    {
        $shipment = (array) $shipment;
        $orderId = (int) ($shipment['id_order'] ?? 0);
        if ($orderId <= 0) {
            return false;
        }
        $order = new Order($orderId);

        $shipmentNumber = $shipment['num_shipment'] ?? Common::getUUID();
        $shipment['num_shipment'] = $shipmentNumber;

        $options = [
            'label_type' => $this->label_type,
            'display_pdf' => $this->display_pdf,
            'shortname' => $this->shortname,
        ];

        $generateShipmentCommand = new GenerateShipmentCommand(
            $this->shortname,
            $orderId,
            $order->reference ?? null,
            (string) $shipmentNumber,
            $shipment,
            $options
        );

        $shipmentEntity = $this->getGenerateShipmentHandler()->handle($generateShipmentCommand);

        $shipment['info_shipment'] = [
            'id_shipment' => $shipmentEntity->getId(),
            'num_shipment' => $shipmentEntity->getShipmentNumber(),
        ];

        return true;
    }

    /**
     * Procesa el request a la api de carrier para obtener las etiquetas.
     * Se usa para GOI pero se podria sobreescribir para otro transportista.
     * Mirar el funcionamiento en transporte GOI.
     */
    public function createLabel($id_shipment, $id_order)
    {
        return true;
    }

    public function saveLabels($id_shipment, $pdf, $num_package = 1): bool
    {
        $entityManager = $this->getEntityManager();
        $shipment = $entityManager->getRepository(ShipmentEntity::class)->find((int) $id_shipment);

        if (!$shipment instanceof ShipmentEntity) {
            return false;
        }

        $labelId = Common::getUUID();

        if (!Common::createFileLabel((string) $pdf, $labelId)) {
            return false;
        }

        $label = new LabelEntity($shipment);
        $label->setPackageId($labelId);
        $label->setLabelType($this->label_type);
        $label->setTrackerCode('TC' . $labelId . '-' . (int) $num_package);
        $label->setPdf($labelId);
        $label->setPrinted(false);

        $entityManager->persist($label);
        $entityManager->flush();

        return true;
    }

    /**
     * save data db table rj_multicarrier_shipment.
     *
     * @param array $info_shipment
     *
     * @return array|false
     */
    public function saveShipment($info_shipment, $response = null)
    {
        $info_shipment = (array) $info_shipment;
        $id_order = (int) ($info_shipment['id_order'] ?? 0);

        if (!$id_order) {
            return false;
        }

        $order = new Order($id_order);

        $shipmentEntity = $this->dispatchShipmentPersistence(
            $info_shipment,
            is_array($response) ? $response : null,
            [],
            $order
        );

        return [
            'id_shipment' => $shipmentEntity->getId(),
            'num_shipment' => $shipmentEntity->getShipmentNumber(),
        ];
    }

    /**
     * @param array<string, mixed> $shipmentData
     * @param array<string, mixed>|null $responseData
     * @param array<int, array<string, mixed>> $labels
     */
    private function dispatchShipmentPersistence(
        array $shipmentData,
        ?array $responseData,
        array $labels,
        Order $order
    ): ShipmentEntity {
        $infoPackage = (array) ($shipmentData['info_package'] ?? []);
        $companyData = (array) ($shipmentData['info_company_carrier'] ?? []);
        $infoPackageId = (int) ($infoPackage['id_infopackage'] ?? 0);

        if ($infoPackageId <= 0) {
            throw new RuntimeException('Missing info package identifier to persist shipment');
        }

    $command = new CreateShipmentCommand(
            (int) ($shipmentData['id_order'] ?? 0),
            $order->reference ?? null,
            $shipmentData['num_shipment'] ?? null,
            $infoPackageId,
            isset($companyData['id_carrier_company']) ? (int) $companyData['id_carrier_company'] : null,
            // Determine current shop id for multistore mapping
            (int) (Context::getContext()->shop->id ?? 0),
            $shipmentData['name_carrier'] ?? null,
            $shipmentData,
            $responseData,
            $labels
        );

        return $this->getCreateShipmentHandler()->handle($command);
    }

    private function getCreateShipmentHandler(): CreateShipmentHandler
    {
        if (!$this->createShipmentHandler instanceof CreateShipmentHandler) {
            $container = SymfonyContainer::getInstance();
            if (null === $container) {
                throw new RuntimeException('Symfony container is not available');
            }
            $handler = $container->get(CreateShipmentHandler::class);

            if (!$handler instanceof CreateShipmentHandler) {
                throw new RuntimeException('Unable to resolve CreateShipmentHandler service');
            }

            $this->createShipmentHandler = $handler;
        }

        return $this->createShipmentHandler;
    }

    private function getGenerateShipmentHandler(): GenerateShipmentHandler
    {
        if (!$this->generateShipmentHandler instanceof GenerateShipmentHandler) {
            $container = SymfonyContainer::getInstance();
            if (null === $container) {
                throw new RuntimeException('Symfony container is not available');
            }

            $handler = $container->get(GenerateShipmentHandler::class);

            if (!$handler instanceof GenerateShipmentHandler) {
                throw new RuntimeException('Unable to resolve GenerateShipmentHandler service');
            }

            $this->generateShipmentHandler = $handler;
        }

        return $this->generateShipmentHandler;
    }

    private function getEntityManager(): EntityManagerInterface
    {
        if (!$this->entityManager instanceof EntityManagerInterface) {
            $container = SymfonyContainer::getInstance();
            if (null === $container) {
                throw new RuntimeException('Symfony container is not available');
            }
            $entityManager = $container->get('doctrine.orm.entity_manager');

            if (!$entityManager instanceof EntityManagerInterface) {
                throw new RuntimeException('Unable to resolve Doctrine entity manager');
            }

            $this->entityManager = $entityManager;
        }

        return $this->entityManager;
    }

    private static function getUpsertTypeShipmentHandler(): UpsertTypeShipmentHandlerDomain
    {
        if (!self::$upsertTypeShipmentHandler instanceof UpsertTypeShipmentHandlerDomain) {
            $container = SymfonyContainer::getInstance();
            if (null === $container) {
                throw new RuntimeException('Symfony container is not available');
            }

            $handler = $container->get(UpsertTypeShipmentHandlerDomain::class);

            if (!$handler instanceof UpsertTypeShipmentHandlerDomain) {
                throw new RuntimeException('Unable to resolve UpsertTypeShipmentHandler service');
            }

            self::$upsertTypeShipmentHandler = $handler;
        }

        return self::$upsertTypeShipmentHandler;
    }

    private static function getToggleTypeShipmentStatusHandler(): ToggleTypeShipmentStatusHandler
    {
        if (!self::$toggleTypeShipmentStatusHandler instanceof ToggleTypeShipmentStatusHandler) {
            $container = SymfonyContainer::getInstance();
            if (null === $container) {
                throw new RuntimeException('Symfony container is not available');
            }

            $handler = $container->get(ToggleTypeShipmentStatusHandler::class);

            if (!$handler instanceof ToggleTypeShipmentStatusHandler) {
                throw new RuntimeException('Unable to resolve ToggleTypeShipmentStatusHandler service');
            }

            self::$toggleTypeShipmentStatusHandler = $handler;
        }

        return self::$toggleTypeShipmentStatusHandler;
    }

    private static function getDeleteTypeShipmentHandler(): DeleteTypeShipmentHandler
    {
        if (!self::$deleteTypeShipmentHandler instanceof DeleteTypeShipmentHandler) {
            $container = SymfonyContainer::getInstance();
            if (null === $container) {
                throw new RuntimeException('Symfony container is not available');
            }

            $handler = $container->get(DeleteTypeShipmentHandler::class);

            if (!$handler instanceof DeleteTypeShipmentHandler) {
                throw new RuntimeException('Unable to resolve DeleteTypeShipmentHandler service');
            }

            self::$deleteTypeShipmentHandler = $handler;
        }

        return self::$deleteTypeShipmentHandler;
    }

    private static function getUpsertInfoPackageHandler(): UpsertInfoPackageHandler
    {
        if (!self::$upsertInfoPackageHandler instanceof UpsertInfoPackageHandler) {
            $container = SymfonyContainer::getInstance();
            if (null === $container) {
                throw new RuntimeException('Symfony container is not available');
            }

            $handler = $container->get(UpsertInfoPackageHandler::class);

            if (!$handler instanceof UpsertInfoPackageHandler) {
                throw new RuntimeException('Unable to resolve UpsertInfoPackageHandler service');
            }

            self::$upsertInfoPackageHandler = $handler;
        }

        return self::$upsertInfoPackageHandler;
    }

    private static function getCreateLogEntryHandler(): CreateLogEntryHandler
    {
        if (!self::$createLogEntryHandler instanceof CreateLogEntryHandler) {
            $container = SymfonyContainer::getInstance();
            if (null === $container) {
                throw new RuntimeException('Symfony container is not available');
            }

            $handler = $container->get(CreateLogEntryHandler::class);

            if (!$handler instanceof CreateLogEntryHandler) {
                throw new RuntimeException('Unable to resolve CreateLogEntryHandler service');
            }

            self::$createLogEntryHandler = $handler;
        }

        return self::$createLogEntryHandler;
    }

    private static function getUpsertInfoShopHandler(): UpsertInfoShopHandler
    {
        if (!self::$upsertInfoShopHandlerService instanceof UpsertInfoShopHandler) {
            $container = SymfonyContainer::getInstance();
            if (null === $container) {
                throw new RuntimeException('Symfony container is not available');
            }

            $handler = $container->get(UpsertInfoShopHandler::class);

            if (!$handler instanceof UpsertInfoShopHandler) {
                throw new RuntimeException('Unable to resolve UpsertInfoShopHandler service');
            }

            self::$upsertInfoShopHandlerService = $handler;
        }

        return self::$upsertInfoShopHandlerService;
    }

    private static function getCompanyRepository(): CompanyRepository
    {
        if (!self::$companyRepository instanceof CompanyRepository) {
            $container = SymfonyContainer::getInstance();
            if (null === $container) {
                throw new RuntimeException('Symfony container is not available');
            }

            $repository = $container->get(CompanyRepository::class);

            if (!$repository instanceof CompanyRepository) {
                throw new RuntimeException('Unable to resolve CompanyRepository service');
            }

            self::$companyRepository = $repository;
        }

        return self::$companyRepository;
    }

    private static function getTypeShipmentRepository(): TypeShipmentRepository
    {
        if (!self::$typeShipmentRepository instanceof TypeShipmentRepository) {
            $container = SymfonyContainer::getInstance();
            if (null === $container) {
                throw new RuntimeException('Symfony container is not available');
            }

            $repository = $container->get(TypeShipmentRepository::class);

            if (!$repository instanceof TypeShipmentRepository) {
                throw new RuntimeException('Unable to resolve TypeShipmentRepository service');
            }

            self::$typeShipmentRepository = $repository;
        }

        return self::$typeShipmentRepository;
    }

    private static function getCarrierCompanyByShortname(string $shortname): ?array
    {
        $company = self::getCompanyRepository()->findOneByShortName($shortname);

        return $company instanceof Company ? self::mapCompanyToArray($company) : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function getAllCarrierCompanies(): array
    {
        $companies = self::getCompanyRepository()->findAllOrdered();

        return array_map(static fn (Company $company): array => self::mapCompanyToArray($company), $companies);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private static function getTypeShipmentsForCompany(int $companyId, bool $onlyActive = false): array
    {
        $company = self::getCompanyRepository()->find($companyId);

        if (!$company instanceof Company) {
            return [];
        }

        $typeShipments = self::getTypeShipmentRepository()->findByCompany($company, $onlyActive);

        return array_map(
            static fn (TypeShipment $typeShipment): array => self::mapTypeShipmentToArray($typeShipment, $company),
            $typeShipments
        );
    }

    private static function getTypeShipmentDataById(int $typeShipmentId): ?array
    {
        $typeShipment = self::getTypeShipmentRepository()->find($typeShipmentId);

        if (!$typeShipment instanceof TypeShipment) {
            return null;
        }

        return self::mapTypeShipmentToArray($typeShipment, $typeShipment->getCompany());
    }

    private static function typeShipmentExists(int $typeShipmentId): bool
    {
        return self::getTypeShipmentRepository()->find($typeShipmentId) instanceof TypeShipment;
    }

    private static function typeShipmentExistsByReference(int $referenceCarrierId): bool
    {
        if ($referenceCarrierId <= 0) {
            return false;
        }

        return self::getTypeShipmentRepository()->findActiveByReferenceCarrier($referenceCarrierId) instanceof TypeShipment;
    }

    private static function mapCompanyToArray(Company $company): array
    {
        return [
            'id_carrier_company' => (int) $company->getId(),
            'name' => $company->getName(),
            'shortname' => $company->getShortName(),
            'icon' => $company->getIcon(),
            'date_add' => self::formatDate($company->getCreatedAt()),
            'date_upd' => self::formatDate($company->getUpdatedAt()),
        ];
    }

    private static function mapTypeShipmentToArray(TypeShipment $typeShipment, Company $company): array
    {
        $referenceId = $typeShipment->getReferenceCarrierId();

        return [
            'id_type_shipment' => (int) $typeShipment->getId(),
            'id_carrier_company' => (int) $company->getId(),
            'name' => $typeShipment->getName(),
            'id_bc' => $typeShipment->getBusinessCode(),
            'id_reference_carrier' => $referenceId,
            'reference_carrier' => self::resolveCarrierName($referenceId),
            'active' => $typeShipment->isActive() ? 1 : 0,
            'carrier_company' => $company->getName(),
            'shortname' => $company->getShortName(),
        ];
    }

    private static function resolveCarrierName(?int $referenceId): ?string
    {
        if (null === $referenceId) {
            return null;
        }

        if (!class_exists('Carrier')) {
            return null;
        }

        $carrier = call_user_func(['Carrier', 'getCarrierByReference'], $referenceId);

        if (is_array($carrier) && isset($carrier['name'])) {
            return (string) $carrier['name'];
        }

        return null;
    }

    private static function formatDate(?DateTimeInterface $date): ?string
    {
        return $date?->format('Y-m-d H:i:s');
    }

    private static function resolveShopId(): int
    {
        if (class_exists('\\Shop') && method_exists('\\Shop', 'getContextShopID')) {
            $shopId = (int) call_user_func(['\\Shop', 'getContextShopID']);

            if ($shopId > 0) {
                return $shopId;
            }
        }

        if (class_exists('\\Shop') && method_exists('\\Shop', 'getContextListShopID')) {
            $shopIds = (array) call_user_func(['\\Shop', 'getContextListShopID']);

            if (!empty($shopIds)) {
                return (int) reset($shopIds);
            }
        }

        $context = Context::getContext();

        return isset($context->shop) ? (int) $context->shop->id : 0;
    }

    private static function nullableString($value): ?string
    {
        if (null === $value) {
            return null;
        }

        $trimmed = trim((string) $value);

        return '' === $trimmed ? null : $trimmed;
    }

    private static function resolveBusinessFlag($companyValue, $explicitValue): bool
    {
        if (null !== $explicitValue && '' !== trim((string) $explicitValue)) {
            $normalized = filter_var($explicitValue, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if (null !== $normalized) {
                return $normalized;
            }

            return true;
        }

        return '' !== trim((string) $companyValue);
    }

    private static function toNullableFloat($value): ?float
    {
        if ($value === '' || $value === null || $value === false) {
            return null;
        }

        return (float) $value;
    }

    private static function normalizeInfoPackageResponse(InfoPackageEntity $infoPackage): array
    {
        $retorno = $infoPackage->getRetorno();
        $vsec = $infoPackage->getVsec();
        $dorig = $infoPackage->getDorig();

        return [
            'id_infopackage' => $infoPackage->getId(),
            'id_order' => $infoPackage->getOrderId(),
            'id_reference_carrier' => $infoPackage->getReferenceCarrierId(),
            'id_type_shipment' => $infoPackage->getTypeShipment()->getId(),
            'quantity' => $infoPackage->getQuantity(),
            'weight' => $infoPackage->getWeight(),
            'length' => $infoPackage->getLength(),
            'width' => $infoPackage->getWidth(),
            'height' => $infoPackage->getHeight(),
            'cash_ondelivery' => $infoPackage->getCashOnDelivery(),
            'message' => $infoPackage->getMessage(),
            'hour_from' => $infoPackage->getHourFrom() ? $infoPackage->getHourFrom()->format('H:i:s') : null,
            'hour_until' => $infoPackage->getHourUntil() ? $infoPackage->getHourUntil()->format('H:i:s') : null,
            'retorno' => $retorno ?? 0,
            'rcs' => $infoPackage->isRcsEnabled() ? 1 : 0,
            'vsec' => $vsec ?? '0',
            'dorig' => $dorig ?? '',
        ];
    }

    /**
     * save data db table rj_multicarrier_infopackage - data del paquete order.
     */
    public static function saveInfoPackage()
    {
        $shopGroupId = (int) Shop::getContextShopGroupID();
        $shopId = (int) Shop::getContextShopID();

        $infoPackageIdRaw = Tools::getValue('id_infopackage');
        $infoPackageId = $infoPackageIdRaw ? (int) $infoPackageIdRaw : null;

        $hourFromCandidate = Tools::getValue('rj_hour_from');
        $hourUntilCandidate = Tools::getValue('rj_hour_until');

        $defaultHourFrom = (string) Configuration::get('RJ_HOUR_FROM', null, $shopGroupId, $shopId);
        $defaultHourUntil = (string) Configuration::get('RJ_HOUR_UNTIL', null, $shopGroupId, $shopId);

        $hourFrom = $hourFromCandidate ? $hourFromCandidate . ':00' : $defaultHourFrom . ':00';
        $hourUntil = $hourUntilCandidate ? $hourUntilCandidate . ':00' : $defaultHourUntil . ':00';

        $hourFrom = self::validateFormatTime($hourFrom) ? $hourFrom : '00:00:00';
        $hourUntil = self::validateFormatTime($hourUntil) ? $hourUntil : '00:00:00';

        $quantityInput = (int) Tools::getValue('rj_quantity');
        $quantity = $quantityInput > 0 ? $quantityInput : 1;
        $weight = (float) Tools::getValue('rj_weight');

        $length = self::toNullableFloat(Tools::getValue('rj_length'));
        $width = self::toNullableFloat(Tools::getValue('rj_width'));
        $height = self::toNullableFloat(Tools::getValue('rj_height'));

        $cashOnDeliveryRaw = Tools::getValue('rj_cash_ondelivery');
        $cashOnDelivery = ($cashOnDeliveryRaw === '' || null === $cashOnDeliveryRaw) ? null : (string) $cashOnDeliveryRaw;

        $messageRaw = Tools::getValue('rj_message');
        $message = ($messageRaw === '' || null === $messageRaw) ? null : (string) $messageRaw;

        $retornoRaw = Tools::getValue('rj_retorno');
        $retorno = ($retornoRaw === '' || null === $retornoRaw) ? 0 : (int) $retornoRaw;

        $rcs = (bool) Tools::getValue('rj_rcs');

        $vsecRaw = Tools::getValue('rj_vsec');
        $vsec = ($vsecRaw === '' || null === $vsecRaw) ? '0' : (string) $vsecRaw;

        $dorigRaw = Tools::getValue('rj_dorig');
        $dorig = ($dorigRaw === null) ? '' : (string) $dorigRaw;

        try {
            $infoPackage = self::getUpsertInfoPackageHandler()->handle(
                new UpsertInfoPackageCommand(
                    $infoPackageId,
                    (int) Tools::getValue('id_order'),
                    (int) Tools::getValue('id_reference_carrier'),
                    (int) Tools::getValue('id_type_shipment'),
                    $quantity,
                    $weight,
                    $length,
                    $width,
                    $height,
                    $cashOnDelivery,
                    $message,
                    $hourFrom,
                    $hourUntil,
                    $retorno,
                    $rcs,
                    $vsec,
                    $dorig,
                    $shopId
                )
            );
        } catch (RuntimeException | InvalidArgumentException $exception) {
            return false;
        }

        return self::normalizeInfoPackageResponse($infoPackage);
    }

    public static function saveInfoShop(): bool
    {
        $shopId = self::resolveShopId();

        if ($shopId <= 0) {
            return false;
        }

        $infoShopIdRaw = Tools::getValue('id_infoshop');
        $infoShopId = (null !== $infoShopIdRaw && '' !== $infoShopIdRaw) ? (int) $infoShopIdRaw : null;

        $companyValue = Tools::getValue('company');
        $explicitBusiness = Tools::getValue('isbusiness');
        $isBusiness = self::resolveBusinessFlag($companyValue, $explicitBusiness);

        try {
            self::getUpsertInfoShopHandler()->handle(
                new UpsertInfoShopCommand(
                    $infoShopId,
                    (string) Tools::getValue('firstname'),
                    (string) Tools::getValue('lastname'),
                    self::nullableString($companyValue),
                    self::nullableString(Tools::getValue('additionalname')),
                    (int) Tools::getValue('id_country'),
                    (string) Tools::getValue('state'),
                    (string) Tools::getValue('city'),
                    (string) Tools::getValue('street'),
                    (string) Tools::getValue('number'),
                    (string) Tools::getValue('postcode'),
                    self::nullableString(Tools::getValue('additionaladdress')),
                    $isBusiness,
                    self::nullableString(Tools::getValue('email')),
                    (string) Tools::getValue('phone'),
                    self::nullableString(Tools::getValue('vatnumber')),
                    [(int) $shopId]
                )
            );
        } catch (RuntimeException | InvalidArgumentException $exception) {
            return false;
        }

        return true;
    }

    public static function getModulesPay()
    {
        $id_shop = Shop::getContextShopID();

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
            'SELECT m.`name`  FROM `' . _DB_PREFIX_ . 'module` m
        INNER JOIN `' . _DB_PREFIX_ . 'module_carrier` mc ON m.`id_module` = mc.`id_module`
        WHERE mc.`id_shop` = ' . $id_shop . '
        GROUP BY m.`id_module`'
        );
    }

    public function getPosicionLabel($posicionLabel)
    {
        switch ($posicionLabel) {
            case '1':
                return '0';
            case '2':
                return '1';
            case '3':
                return '2';
            default:
                return '0';
        }
    }

    public static function validateFormatTime($time)
    {
        if (preg_match('/(?:[01]\d|2[0-3]):(?:[0-5]\d):(?:[0-5]\d)/', $time)) {
            return true;
        }

        return false;
    }

    public static function saveLog($name, $id_order, $body, $response): void
    {
        $context = \Context::getContext();
        $shopId = isset($context->shop->id) ? (int) $context->shop->id : 0;

        try {
            self::getCreateLogEntryHandler()->handle(
                new CreateLogEntryCommand(
                    (string) $name,
                    (int) $id_order,
                    (string) $body,
                    null === $response ? null : (string) $response,
                    $shopId
                )
            );
        } catch (RuntimeException $exception) {
            // swallow exception to keep legacy behaviour; logging failures shouldn't break flow
        }
    }
}
