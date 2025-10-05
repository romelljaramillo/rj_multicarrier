<?php
/**
 * Module installer service responsible for database schema and legacy migrations.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Service\Installer;

use Doctrine\DBAL\Connection;
use Roanja\Module\RjMulticarrier\Service\Doctrine\DoctrineMappingConfigurator;

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
            'parent_class_name' => '0',
            'route_name' => null,
            'active' => true,
            'wording' => 'Multi-carrier',
            'wording_domain' => 'Modules.RjMulticarrier.Admin',
            'translation_key' => 'Modules.RjMulticarrier.Admin.Menu.Parent',
        ],
        [
            'class_name' => 'AdminRjMulticarrierConfiguration',
            'parent_class_name' => 'AdminRjMulticarrier',
            'route_name' => 'admin_rj_multicarrier_configuration',
            'icon' => 'settings',
            'active' => true,
            'wording' => 'Configuration',
            'wording_domain' => 'Modules.RjMulticarrier.Admin',
            'translation_key' => 'Modules.RjMulticarrier.Admin.Menu.Configuration',
        ],
        [
            'class_name' => 'AdminRjMulticarrierTypeShipment',
            'parent_class_name' => 'AdminRjMulticarrier',
            'route_name' => 'admin_rj_multicarrier_type_shipment_index',
            'icon' => 'compare_arrows',
            'active' => true,
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
            'class_name' => 'AdminRjShipmentGenerate',
            'parent_class_name' => 'AdminRjMulticarrier',
            'route_name' => 'admin_rj_multicarrier_info_packages_index',
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

    public function __construct(?Connection $connection = null, private ?DoctrineMappingConfigurator $mappingConfigurator = null)
    {
        $this->connection = $connection;
    }

    public function install(): bool
    {
        $installed = $this->executeSqlScript('install')
            && $this->ensureFilesystem()
            && $this->installTabs();

        if ($installed) {
            $this->mappingConfigurator?->registerDriver();
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
            $this->mappingConfigurator?->registerDriver();
            $this->refreshRoutingCache();
        }

        return $uninstalled;
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
     * @param array{class_name:string,parent_class_name?:string,route_name?:?string,icon?:?string,active?:bool,wording?:string,wording_domain?:string,translation_key?:string} $definition
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
                'en' => 'Configuration',
                'es' => 'Configuración',
            ],
            'Modules.RjMulticarrier.Admin.Menu.TypeShipments' => [
                'en' => 'Shipment types',
                'es' => 'Tipos de envío',
            ],
            'Modules.RjMulticarrier.Admin.Menu.Companies' => [
                'en' => 'Carrier companies',
                'es' => 'Empresas de transporte',
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
