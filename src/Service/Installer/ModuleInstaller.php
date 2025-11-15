<?php
/**
 * Module installer service responsible for database schema and legacy migrations.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Service\Installer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\Persistence\Mapping\Driver\MappingDriverChain;

if (!class_exists('\\Tab') && class_exists('\\TabCore')) {
    class_alias('\\TabCore', '\\Tab');
}

if (!class_exists('\\Language') && class_exists('\\LanguageCore')) {
    class_alias('\\LanguageCore', '\\Language');
}

final class ModuleInstaller
{
    private const ADMIN_TAB_DEFINITIONS = [
        [
            'class_name' => 'AdminRjMulticarrier',
            'parent_class_name' => 'IMPROVE',
            'route_name' => null,
            'active' => true,
            'icon' => 'business',
            'wording' => 'Multi-carrier',
        ],
        [
            'class_name' => 'AdminRjMulticarrierConfigurationParent',
            'parent_class_name' => 'AdminRjMulticarrier',
            'route_name' => 'admin_rj_multicarrier_configuration_shop_index',
            'active' => true,
            'icon' => 'settings',
            'wording' => 'Configuration shop',
        ],
        [
            'class_name' => 'AdminRjMulticarrierConfiguration',
            'parent_class_name' => 'AdminRjMulticarrierConfigurationParent',
            'route_name' => 'admin_rj_multicarrier_configuration_shop_index',
            'icon' => 'settings',
            'active' => true,
            'wording' => 'Configuration shop',
            'wording_domain' => 'Modules.RjMulticarrier.Admin',
            'translation_key' => 'Modules.RjMulticarrier.Admin.Menu.Configuration',
        ],
        [
            'class_name' => 'AdminRjMulticarrierValidationRules',
            'parent_class_name' => 'AdminRjMulticarrierConfigurationParent',
            'route_name' => 'admin_rj_multicarrier_validation_rule_index',
            'icon' => 'rule',
            'active' => true,
            'wording' => 'Carrier validation rules',
            'wording_domain' => 'Modules.RjMulticarrier.Admin',
            'translation_key' => 'Modules.RjMulticarrier.Admin.Menu.ValidationRules',
        ],
        [
            'class_name' => 'AdminRjMulticarrierCarriers',
            'parent_class_name' => 'AdminRjMulticarrierConfigurationParent',
            'route_name' => 'admin_rj_multicarrier_carriers_index',
            'icon' => 'local_shipping',
            'active' => true,
            'visible' => false,
            'wording' => 'Carriers',
            'wording_domain' => 'Modules.RjMulticarrier.Admin',
            'translation_key' => 'Modules.RjMulticarrier.Admin.Menu.Carriers',
        ],
        [
            'class_name' => 'AdminRjMulticarrierTypeShipment',
            'parent_class_name' => 'AdminRjMulticarrierConfigurationParent',
            'route_name' => 'admin_rj_multicarrier_type_shipment_index',
            'icon' => 'compare_arrows',
            'active' => true,
            'visible' => false,
            'wording' => 'Shipment types',
            'wording_domain' => 'Modules.RjMulticarrier.Admin',
            'translation_key' => 'Modules.RjMulticarrier.Admin.Menu.TypeShipments',
        ],
        [
            'class_name' => 'AdminRjMulticarrierShipments',
            'parent_class_name' => 'AdminRjMulticarrier',
            'route_name' => 'admin_rj_multicarrier_shipments_index',
            'icon' => 'local_shipping',
            'active' => true,
            'wording' => 'Shipments',
            'wording_domain' => 'Modules.RjMulticarrier.Admin',
            'translation_key' => 'Modules.RjMulticarrier.Admin.Menu.Shipments',
        ],
        [
            'class_name' => 'AdminRjMulticarrierInfoShipments',
            'parent_class_name' => 'AdminRjMulticarrier',
            'route_name' => 'admin_rj_multicarrier_info_shipments_index',
            'icon' => 'play_circle',
            'active' => true,
            'wording' => 'Generate shipment',
            'wording_domain' => 'Modules.RjMulticarrier.Admin',
            'translation_key' => 'Modules.RjMulticarrier.Admin.Menu.Generator',
        ],
        [
            'class_name' => 'AdminRjMulticarrierLog',
            'parent_class_name' => 'AdminRjMulticarrier',
            'route_name' => 'admin_rj_multicarrier_logs_index',
            'icon' => 'warning',
            'active' => true,
            'wording' => 'Logs',
            'wording_domain' => 'Modules.RjMulticarrier.Admin',
            'translation_key' => 'Modules.RjMulticarrier.Admin.Menu.Logs',
        ],
        [
            'class_name' => 'AdminRjMulticarrierAjax',
            'parent_class_name' => 'AdminRjMulticarrier',
            'route_name' => 'admin_rj_multicarrier_ajax',
            'icon' => null,
            'active' => false,
            'visible' => false,
            'wording' => 'Ajax bridge',
            'wording_domain' => 'Modules.RjMulticarrier.Admin',
            'translation_key' => 'Modules.RjMulticarrier.Admin.Menu.Ajax',
        ],
    ];

    private ?Connection $connection;
    private bool $doctrineDriverRegistered = false;

    public function __construct(
        ?Connection $connection = null,
        private ?MappingDriverChain $mappingDriverChain = null,
        private ?AttributeDriver $attributeDriver = null
    )
    {
        $this->connection = $connection;
        $this->registerDoctrineDriver();
    }

    public function install(): bool
    {
        $installed = $this->executeSqlScript('install')
            && $this->ensureFilesystem()
            && $this->installTabs()
            && $this->ensureConfigurationSchema();

        if ($installed) {
            $this->registerDoctrineDriver();
            $this->refreshRoutingCache();
            $this->syncTabRoutesWithDefinitions();
        }

        return $installed;
    }

    public function uninstall(): bool
    {
        $uninstalled = $this->uninstallTabs()
            && $this->executeSqlScript('uninstall');

        if ($uninstalled) {
            $this->registerDoctrineDriver();
            $this->refreshRoutingCache();
        }

        return $uninstalled;
    }

    private function registerDoctrineDriver(): void
    {
        if ($this->doctrineDriverRegistered) {
            return;
        }

        if (null === $this->mappingDriverChain || null === $this->attributeDriver) {
            return;
        }

        foreach ($this->mappingDriverChain->getDrivers() as $namespace => $driver) {
            if ($namespace === 'Roanja\\Module\\RjMulticarrier\\Entity') {
                $this->doctrineDriverRegistered = true;

                return;
            }
        }

        $this->mappingDriverChain->addDriver($this->attributeDriver, 'Roanja\\Module\\RjMulticarrier\\Entity');
        $this->doctrineDriverRegistered = true;
    }

    private function ensureConfigurationSchema(): bool
    {
        if (null === $this->connection) {
            return true;
        }

        try {
            /** @var AbstractSchemaManager $schemaManager */
            $schemaManager = method_exists($this->connection, 'createSchemaManager')
                ? call_user_func([$this->connection, 'createSchemaManager'])
                : $this->connection->getSchemaManager();

            $tableName = _DB_PREFIX_ . 'rj_multicarrier_configuration';
            if ($schemaManager->tablesExist([$tableName])) {
                $columns = $schemaManager->listTableColumns($tableName);

                if (!isset($columns['label_prefix'])) {
                    $this->connection->executeStatement('ALTER TABLE `' . $tableName . '` ADD `label_prefix` VARCHAR(64) NULL DEFAULT NULL');
                }

                if (!isset($columns['cod_module'])) {
                    $this->connection->executeStatement('ALTER TABLE `' . $tableName . '` ADD `cod_module` VARCHAR(255) NULL DEFAULT NULL');
                }
            }

            if (!$this->ensureValidationRuleSchema($schemaManager)) {
                return false;
            }
        } catch (\Throwable $exception) {
            return false;
        }

        return true;
    }

    private function ensureValidationRuleSchema(AbstractSchemaManager $schemaManager): bool
    {
        if (null === $this->connection) {
            return true;
        }

        $tableName = _DB_PREFIX_ . 'rj_multicarrier_validation_rule';

        if ($schemaManager->tablesExist([$tableName])) {
            return true;
        }

        $sql = 'CREATE TABLE `' . $tableName . '` (
    `id_validation_rule` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(191) NOT NULL,
    `priority` INT NOT NULL DEFAULT 0,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `shop_id` INT(11) NULL DEFAULT NULL,
    `shop_group_id` INT(11) NULL DEFAULT NULL,
    `product_ids` JSON NULL,
    `category_ids` JSON NULL,
    `zone_ids` JSON NULL,
    `country_ids` JSON NULL,
    `min_weight` DOUBLE NULL,
    `max_weight` DOUBLE NULL,
    `allow_ids` JSON NULL,
    `deny_ids` JSON NULL,
    `add_ids` JSON NULL,
    `prefer_ids` JSON NULL,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    PRIMARY KEY (`id_validation_rule`),
    INDEX `idx_validation_rule_active` (`active`),
    INDEX `idx_validation_rule_priority` (`priority`),
    INDEX `idx_validation_rule_shop` (`shop_id`),
    INDEX `idx_validation_rule_shop_group` (`shop_group_id`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;';

        $this->connection->executeStatement($sql);

        return true;
    }

    private function ensureFilesystem(): bool
    {
        $directories = [
            $this->getVarDir(),
            $this->getIconDir(),
            $this->getLabelsDir(),
        ];

        foreach ($directories as $directory) {
            if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
                return false;
            }
        }

        return true;
    }

    private function installTabs(): bool
    {
        $tabClass = $this->resolveTabClass();

        foreach (self::ADMIN_TAB_DEFINITIONS as $definition) {
            $parentId = $this->resolveTabParentId($definition['parent_class_name'] ?? null);

            if (!$this->createTab($definition, $parentId, $tabClass)) {
                return false;
            }
        }

        return true;
    }

    private function uninstallTabs(): bool
    {
        $tabClass = $this->resolveTabClass();

        foreach (array_reverse(self::ADMIN_TAB_DEFINITIONS) as $definition) {
            $tabId = (int) \call_user_func([$tabClass, 'getIdFromClassName'], $definition['class_name']);

            if (0 === $tabId) {
                continue;
            }

            $tab = new $tabClass($tabId);

            if (!$tab->delete()) {
                return false;
            }
        }

        return true;
    }

    private function executeSqlScript(string $scriptName): bool
    {
        $path = $this->getSqlScriptPath($scriptName);

        if (!is_file($path) || !is_readable($path)) {
            return false;
        }

        $result = require $path;

        return false !== $result;
    }

    private function getSqlScriptPath(string $scriptName): string
    {
        return $this->getModuleBaseDir() . 'sql/' . $scriptName . '.php';
    }

    private function getModuleBaseDir(): string
    {
        return rtrim(_PS_MODULE_DIR_ . 'rj_multicarrier/', DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    private function resolveTabParentId(?string $className): int
    {
        if (null === $className || '0' === $className || 0 === $className) {
            return 0;
        }

        $tabClass = $this->resolveTabClass();
        $parentId = (int) \call_user_func([$tabClass, 'getIdFromClassName'], $className);

        return $parentId > 0 ? $parentId : 0;
    }

    /**
     * Create or update a Tab (admin menu entry) from a definition array.
     *
     * The definition may include the following keys:
     * - class_name (string) required: class name used by PrestaShop Tab system
     * - parent_class_name (string|null) optional: parent class name or '0' for root
     * - route_name (string|null) optional: Symfony route name to attach for access control
     * - icon (string|null) optional: material icon name
     * - active (bool) optional: whether tab is active
     * - visible (bool) optional: whether tab is visible in the menu (hidden tabs are used for permissions)
     * - wording (string|null) optional: wording fallback
     * - wording_domain (string|null) optional: translation domain
     * - translation_key (string|null) optional: translation key used to build localized names
     *
     * @param array{class_name:string,parent_class_name?:string,route_name?:?string,icon?:?string,active?:bool,visible?:bool,wording?:string,wording_domain?:string,translation_key?:string} $definition
     * @param class-string $tabClass
     */
    private function createTab(array $definition, int $parentId, string $tabClass): bool
    {
        $className = $definition['class_name'];
        $tabId = (int) \call_user_func([$tabClass, 'getIdFromClassName'], $className);
        $tab = $tabId > 0 ? new $tabClass($tabId) : new $tabClass();

        $tab->module = 'rj_multicarrier';
        $tab->class_name = $className;
        $tab->id_parent = $parentId;
        $tab->active = isset($definition['active']) ? (int) $definition['active'] : 1;

        if (array_key_exists('route_name', $definition)) {
            $tab->route_name = $definition['route_name'] ?? null;
        }

        if (!empty($definition['icon'])) {
            $tab->icon = (string) $definition['icon'];
        }

        if (!empty($definition['wording'])) {
            $tab->wording = (string) $definition['wording'];
        }

        if (!empty($definition['wording_domain'])) {
            $tab->wording_domain = (string) $definition['wording_domain'];
        }

        if (array_key_exists('visible', $definition)) {
            $tab->visible = (int) ($definition['visible'] ? 1 : 0);
        }

        $tab->name = $this->buildTabNames($definition);

        return $tabId > 0 ? (bool) $tab->save() : (bool) $tab->add();
    }

    /**
     * @param array{translation_key?:string,wording?:string} $definition
     *
     * @return array<int, string>
     */
    private function buildTabNames(array $definition): array
    {
        $names = [];
        $translationKey = $definition['translation_key'] ?? null;

        foreach ($this->getLanguages() as $language) {
            $names[(int) $language['id_lang']] = $translationKey
                ? $this->resolveTabLabel($translationKey, $language)
                : ($definition['wording'] ?? 'RJ Multicarrier');
        }

        return $names;
    }

    /**
     * @return array<int, array{id_lang:int, iso_code:string}>
     */
    private function getLanguages(): array
    {
        $languageClass = class_exists('\\Language') ? '\\Language' : '\\LanguageCore';

        return (array) \call_user_func([$languageClass, 'getLanguages'], true);
    }

    private function resolveTabClass(): string
    {
        return class_exists('\\Tab') ? '\\Tab' : '\\TabCore';
    }

    private function resolveTabLabel(string $key, array $language): string
    {
        $labels = [
            'Modules.RjMulticarrier.Admin.Menu.Parent' => [
                'en' => 'Multi-carrier',
                'es' => 'Multi-carrier',
            ],
            'Modules.RjMulticarrier.Admin.Menu.Configuration' => [
                'en' => 'Shop information',
                'es' => 'Información tienda',
            ],
            'Modules.RjMulticarrier.Admin.Menu.Carriers' => [
                'en' => 'Carrier companies',
                'es' => 'Empresas de transporte',
            ],
            'Modules.RjMulticarrier.Admin.Menu.TypeShipments' => [
                'en' => 'Shipment types',
                'es' => 'Tipos de envío',
            ],
            'Modules.RjMulticarrier.Admin.Menu.Shipments' => [
                'en' => 'Shipments',
                'es' => 'Envíos',
            ],
            'Modules.RjMulticarrier.Admin.Menu.Labels' => [
                'en' => 'Labels',
                'es' => 'Etiquetas',
            ],
            'Modules.RjMulticarrier.Admin.Menu.Generator' => [
                'en' => 'Generate shipment',
                'es' => 'Generar envío',
            ],
            'Modules.RjMulticarrier.Admin.Menu.Logs' => [
                'en' => 'Logs',
                'es' => 'Registros',
            ],
            'Modules.RjMulticarrier.Admin.Menu.Ajax' => [
                'en' => 'Ajax bridge',
                'es' => 'Pasarela Ajax',
            ],
            'Modules.RjMulticarrier.Admin.Menu.ValidationRules' => [
                'en' => 'Carrier validation rules',
                'es' => 'Validaciones de transportista',
            ],
        ];

        $iso = strtolower((string) ($language['iso_code'] ?? 'en'));

        if (isset($labels[$key][$iso])) {
            return $labels[$key][$iso];
        }

        return $labels[$key]['en'] ?? 'RJ Multicarrier';
    }

    private function getVarDir(): string
    {
        if (defined('RJ_MULTICARRIER_VAR_DIR')) {
            return rtrim(RJ_MULTICARRIER_VAR_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        }

        return _PS_MODULE_DIR_ . 'rj_multicarrier/var/';
    }

    private function getIconDir(): string
    {
        if (defined('IMG_ICON_COMPANY_DIR')) {
            return rtrim(IMG_ICON_COMPANY_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        }

        return $this->getVarDir() . 'icons/';
    }

    private function getLabelsDir(): string
    {
        if (defined('RJ_MULTICARRIER_LABEL_DIR')) {
            return rtrim(RJ_MULTICARRIER_LABEL_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        }

        return _PS_MODULE_DIR_ . 'rj_multicarrier/labels/';
    }

    private function refreshRoutingCache(): void
    {
        if (class_exists('\\Tools')) {
            \Tools::clearSf2Cache();
        }
    }

    private function syncTabRoutesWithDefinitions(): void
    {
        foreach (self::ADMIN_TAB_DEFINITIONS as $definition) {
            $routeName = $definition['route_name'] ?? null;

            if (null === $routeName || '' === $routeName) {
                continue;
            }

            $tabClass = $this->resolveTabClass();
            $tabId = (int) \call_user_func([$tabClass, 'getIdFromClassName'], $definition['class_name']);

            if (0 === $tabId) {
                continue;
            }

            $tab = new $tabClass($tabId);

            if ($tab->route_name !== $routeName) {
                $tab->route_name = $routeName;
                $tab->save();
            }
        }
    }

}
