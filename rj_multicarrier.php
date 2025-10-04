<?php
/**
 * Multi Carrier Module for PrestaShop 8+
 *
 * @author    Romell Jaramillo <romell.jaramillo@gmail.com>
 * @copyright 2025 Romell Jaramillo
 * @license   MIT License
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class Rj_Multicarrier extends Module
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->name = 'rj_multicarrier';
        $this->tab = 'shipping_logistics';
        $this->version = '1.0.0';
        $this->author = 'Romell Jaramillo';
        $this->need_instance = 0;
        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => _PS_VERSION_];
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Multi Carrier');
        $this->description = $this->l('Manage multiple carriers for your shop with advanced options.');
        $this->confirmUninstall = $this->l('Are you sure you want to uninstall this module?');

        if (!Configuration::get('RJ_MULTICARRIER_ENABLED')) {
            $this->warning = $this->l('No configuration set for this module.');
        }
    }

    /**
     * Install module
     *
     * @return bool
     */
    public function install()
    {
        if (Shop::isFeatureActive()) {
            Shop::setContext(Shop::CONTEXT_ALL);
        }

        return parent::install()
            && $this->registerHook('displayHeader')
            && $this->registerHook('displayBackOfficeHeader')
            && $this->registerHook('displayCarrierList')
            && $this->registerHook('actionCarrierUpdate')
            && $this->registerHook('displayAdminOrderTabContent')
            && $this->registerHook('displayAdminOrderTabLink')
            && $this->installDB()
            && $this->installConfiguration();
    }

    /**
     * Uninstall module
     *
     * @return bool
     */
    public function uninstall()
    {
        return $this->uninstallDB()
            && $this->uninstallConfiguration()
            && parent::uninstall();
    }

    /**
     * Install database tables
     *
     * @return bool
     */
    protected function installDB()
    {
        $sql = [];

        // Main carriers configuration table
        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'rj_multicarrier` (
            `id_rj_multicarrier` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_carrier` INT(11) UNSIGNED NOT NULL,
            `id_shop` INT(11) UNSIGNED NOT NULL DEFAULT 1,
            `active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
            `priority` INT(11) UNSIGNED NOT NULL DEFAULT 0,
            `date_add` DATETIME NOT NULL,
            `date_upd` DATETIME NOT NULL,
            PRIMARY KEY (`id_rj_multicarrier`),
            KEY `id_carrier` (`id_carrier`),
            KEY `id_shop` (`id_shop`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

        // Carrier rules table for conditions
        $sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'rj_multicarrier_rule` (
            `id_rj_multicarrier_rule` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `id_rj_multicarrier` INT(11) UNSIGNED NOT NULL,
            `rule_type` VARCHAR(50) NOT NULL,
            `rule_value` TEXT NOT NULL,
            `active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
            `date_add` DATETIME NOT NULL,
            `date_upd` DATETIME NOT NULL,
            PRIMARY KEY (`id_rj_multicarrier_rule`),
            KEY `id_rj_multicarrier` (`id_rj_multicarrier`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

        foreach ($sql as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Uninstall database tables
     *
     * @return bool
     */
    protected function uninstallDB()
    {
        $sql = [
            'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'rj_multicarrier_rule`',
            'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'rj_multicarrier`',
        ];

        foreach ($sql as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Install module configuration
     *
     * @return bool
     */
    protected function installConfiguration()
    {
        return Configuration::updateValue('RJ_MULTICARRIER_ENABLED', 1)
            && Configuration::updateValue('RJ_MULTICARRIER_DEBUG', 0)
            && Configuration::updateValue('RJ_MULTICARRIER_PRIORITY_ORDER', 'asc');
    }

    /**
     * Uninstall module configuration
     *
     * @return bool
     */
    protected function uninstallConfiguration()
    {
        return Configuration::deleteByName('RJ_MULTICARRIER_ENABLED')
            && Configuration::deleteByName('RJ_MULTICARRIER_DEBUG')
            && Configuration::deleteByName('RJ_MULTICARRIER_PRIORITY_ORDER');
    }

    /**
     * Get module configuration page
     *
     * @return string HTML content
     */
    public function getContent()
    {
        $output = '';

        // Handle form submission
        if (Tools::isSubmit('submitRjMulticarrierConfig')) {
            $this->postProcess();
            $output .= $this->displayConfirmation($this->l('Settings updated successfully.'));
        }

        // Display configuration form
        $output .= $this->renderForm();

        // Display carriers list
        $output .= $this->renderCarriersList();

        return $output;
    }

    /**
     * Process configuration form
     */
    protected function postProcess()
    {
        Configuration::updateValue('RJ_MULTICARRIER_ENABLED', (int)Tools::getValue('RJ_MULTICARRIER_ENABLED'));
        Configuration::updateValue('RJ_MULTICARRIER_DEBUG', (int)Tools::getValue('RJ_MULTICARRIER_DEBUG'));
        Configuration::updateValue('RJ_MULTICARRIER_PRIORITY_ORDER', Tools::getValue('RJ_MULTICARRIER_PRIORITY_ORDER'));
    }

    /**
     * Render configuration form
     *
     * @return string HTML content
     */
    protected function renderForm()
    {
        $fields_form = [
            'form' => [
                'legend' => [
                    'title' => $this->l('Multi Carrier Settings'),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'switch',
                        'label' => $this->l('Enable module'),
                        'name' => 'RJ_MULTICARRIER_ENABLED',
                        'is_bool' => true,
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Yes'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->l('No'),
                            ],
                        ],
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Debug mode'),
                        'name' => 'RJ_MULTICARRIER_DEBUG',
                        'is_bool' => true,
                        'desc' => $this->l('Enable debug mode to see detailed logs.'),
                        'values' => [
                            [
                                'id' => 'debug_on',
                                'value' => 1,
                                'label' => $this->l('Yes'),
                            ],
                            [
                                'id' => 'debug_off',
                                'value' => 0,
                                'label' => $this->l('No'),
                            ],
                        ],
                    ],
                    [
                        'type' => 'select',
                        'label' => $this->l('Priority order'),
                        'name' => 'RJ_MULTICARRIER_PRIORITY_ORDER',
                        'desc' => $this->l('Order in which carriers will be displayed based on priority.'),
                        'options' => [
                            'query' => [
                                ['id' => 'asc', 'name' => $this->l('Ascending')],
                                ['id' => 'desc', 'name' => $this->l('Descending')],
                            ],
                            'id' => 'id',
                            'name' => 'name',
                        ],
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                    'class' => 'btn btn-default pull-right',
                ],
            ],
        ];

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = (int)Configuration::get('PS_LANG_DEFAULT');
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG')
            ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG')
            : 0;
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitRjMulticarrierConfig';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = [
            'fields_value' => [
                'RJ_MULTICARRIER_ENABLED' => Configuration::get('RJ_MULTICARRIER_ENABLED'),
                'RJ_MULTICARRIER_DEBUG' => Configuration::get('RJ_MULTICARRIER_DEBUG'),
                'RJ_MULTICARRIER_PRIORITY_ORDER' => Configuration::get('RJ_MULTICARRIER_PRIORITY_ORDER'),
            ],
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $helper->generateForm([$fields_form]);
    }

    /**
     * Render carriers list
     *
     * @return string HTML content
     */
    protected function renderCarriersList()
    {
        $carriers = Carrier::getCarriers(
            $this->context->language->id,
            true,
            false,
            false,
            null,
            Carrier::ALL_CARRIERS
        );

        $fields_list = [
            'id_carrier' => [
                'title' => $this->l('ID'),
                'width' => 50,
                'type' => 'text',
            ],
            'name' => [
                'title' => $this->l('Name'),
                'width' => 'auto',
                'type' => 'text',
            ],
            'delay' => [
                'title' => $this->l('Delay'),
                'width' => 'auto',
                'type' => 'text',
            ],
            'active' => [
                'title' => $this->l('Active'),
                'width' => 70,
                'active' => 'status',
                'type' => 'bool',
                'align' => 'center',
                'orderby' => false,
            ],
        ];

        $helper = new HelperList();
        $helper->shopLinkType = '';
        $helper->simple_header = true;
        $helper->identifier = 'id_carrier';
        $helper->actions = ['edit', 'delete'];
        $helper->show_toolbar = true;
        $helper->title = $this->l('Available Carriers');
        $helper->table = 'carrier';

        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name;

        return $helper->generateList($carriers, $fields_list);
    }

    /**
     * Hook: Display Header
     */
    public function hookDisplayHeader($params)
    {
        if (!Configuration::get('RJ_MULTICARRIER_ENABLED')) {
            return;
        }

        $this->context->controller->addCSS($this->_path . 'views/css/front.css');
        $this->context->controller->addJS($this->_path . 'views/js/front.js');
    }

    /**
     * Hook: Display Back Office Header
     */
    public function hookDisplayBackOfficeHeader($params)
    {
        if (!Configuration::get('RJ_MULTICARRIER_ENABLED')) {
            return;
        }

        $this->context->controller->addCSS($this->_path . 'views/css/back.css');
        $this->context->controller->addJS($this->_path . 'views/js/back.js');
    }

    /**
     * Hook: Display Carrier List
     */
    public function hookDisplayCarrierList($params)
    {
        if (!Configuration::get('RJ_MULTICARRIER_ENABLED')) {
            return;
        }

        // Custom carrier list display logic
        $this->context->smarty->assign([
            'carriers' => $params['carriers'] ?? [],
            'delivery_option' => $params['delivery_option'] ?? null,
        ]);

        return $this->display(__FILE__, 'views/templates/hook/carrier_list.tpl');
    }

    /**
     * Hook: Action Carrier Update
     */
    public function hookActionCarrierUpdate($params)
    {
        if (!Configuration::get('RJ_MULTICARRIER_ENABLED')) {
            return;
        }

        // Update carrier logic
        if (isset($params['id_carrier']) && isset($params['carrier'])) {
            // Log or process carrier update
            if (Configuration::get('RJ_MULTICARRIER_DEBUG')) {
                PrestaShopLogger::addLog(
                    'RJ MultiCarrier: Carrier updated - ID: ' . $params['id_carrier'],
                    1,
                    null,
                    'Carrier',
                    $params['id_carrier']
                );
            }
        }
    }

    /**
     * Hook: Display Admin Order Tab Link
     */
    public function hookDisplayAdminOrderTabLink($params)
    {
        if (!Configuration::get('RJ_MULTICARRIER_ENABLED')) {
            return;
        }

        $this->context->smarty->assign([
            'id_order' => $params['id_order'] ?? 0,
        ]);

        return $this->display(__FILE__, 'views/templates/admin/tab_link.tpl');
    }

    /**
     * Hook: Display Admin Order Tab Content
     */
    public function hookDisplayAdminOrderTabContent($params)
    {
        if (!Configuration::get('RJ_MULTICARRIER_ENABLED')) {
            return;
        }

        $this->context->smarty->assign([
            'id_order' => $params['id_order'] ?? 0,
            'carriers' => Carrier::getCarriers($this->context->language->id, true),
        ]);

        return $this->display(__FILE__, 'views/templates/admin/tab_content.tpl');
    }
}
