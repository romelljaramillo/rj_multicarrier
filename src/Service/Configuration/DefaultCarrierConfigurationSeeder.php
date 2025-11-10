<?php
/**
 * Seeds carrier configuration defaults, optionally migrating values from the legacy module.
 */
declare(strict_types=1);

namespace Roanja\Module\RjMulticarrier\Service\Configuration;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Roanja\Module\RjMulticarrier\Carrier\Adapter\CarrierAdapterInterface;
use Roanja\Module\RjMulticarrier\Entity\Carrier;
use Roanja\Module\RjMulticarrier\Entity\CarrierConfiguration;
use Roanja\Module\RjMulticarrier\Repository\CarrierConfigurationRepository;
use Roanja\Module\RjMulticarrier\Repository\CarrierRepository;

final class DefaultCarrierConfigurationSeeder
{
    /**
    * @var array<string, array<int, array{
    *     name: string,
    *     value: ?string,
    *     label?: ?string,
    *     required?: bool,
    *     description?: ?string,
    *     legacy?: array<int, string>
    * }>>
     */
    private array $defaultDefinitions;
    private bool $schemaChecked = false;

    public function __construct(
        private readonly CarrierRepository $carrierRepository,
        private readonly CarrierConfigurationRepository $configurationRepository,
        private readonly Connection $connection,
        iterable $carrierAdapters
    ) {
        $this->defaultDefinitions = $this->buildAdapterDefaults($carrierAdapters);
    }

    /**
     * Seed defaults for every active carrier.
     */
    public function seedAll(): void
    {
        $this->ensureSchema();
        $carriers = $this->carrierRepository->findAllOrdered();

        foreach ($carriers as $carrier) {
            $this->seedForCarrier($carrier);
        }
    }

    /**
     * Ensure defaults exist for a specific carrier.
     */
    public function seedForCarrier(Carrier $carrier): void
    {
        $this->ensureSchema();
        $defaults = $this->getDefaultsForCarrier($carrier);

        if (empty($defaults)) {
            return;
        }

        $existing = $this->configurationRepository->findByCarrier($carrier);
        $existingByName = [];

        foreach ($existing as $configuration) {
            $existingByName[$configuration->getName()] = $configuration;
        }

        $legacyValues = $this->fetchLegacyValues($carrier);
        $now = new DateTimeImmutable();

        foreach ($defaults as $definition) {
            $name = $definition['name'];
            $required = (bool) ($definition['required'] ?? true);

            $value = $legacyValues[$name] ?? null;

            if (null === $value && !empty($definition['legacy']) && is_array($definition['legacy'])) {
                foreach ($definition['legacy'] as $legacyName) {
                    $legacyKey = trim((string) $legacyName);
                    if ('' === $legacyKey) {
                        continue;
                    }

                    if (array_key_exists($legacyKey, $legacyValues)) {
                        $value = $legacyValues[$legacyKey];
                        break;
                    }
                }
            }

            if (null === $value && array_key_exists('value', $definition)) {
                $value = $definition['value'];
            }

            if (isset($existingByName[$name])) {
                $configuration = $existingByName[$name];
                $hasChanges = false;

                if ($configuration->isRequired() !== $required) {
                    $configuration->setIsRequired($required);
                    $hasChanges = true;
                }

                if (null === $configuration->getValue() && null !== $value) {
                    $configuration->setValue($value);
                    $hasChanges = true;
                }

                if ($hasChanges) {
                    $configuration->setUpdatedAt($now);
                    $this->configurationRepository->save($configuration);
                }

                continue;
            }

            $configuration = (new CarrierConfiguration())
                ->setCarrier($carrier)
                ->setTypeShipment(null)
                ->setName($name)
                ->setValue($value)
                ->setIsRequired($required)
                ->setCreatedAt($now)
                ->setUpdatedAt($now);

            $this->configurationRepository->save($configuration);
        }
    }

    /**
     * @return array<string, ?string>
     */
    private function fetchLegacyValues(Carrier $carrier): array
    {
        $tableCarrierCompany = _DB_PREFIX_ . 'rj_carrier_company';
        $tableConfiguration = _DB_PREFIX_ . 'rj_carrier_configuration';

        if (!$this->tableExists($tableCarrierCompany) || !$this->tableExists($tableConfiguration)) {
            return [];
        }

        if (!class_exists('\\Db')) {
            return [];
        }

        $db = \Db::getInstance();
        $shortName = $carrier->getShortName();
        $escape = function ($value) {
            if (function_exists('pSQL')) {
                return \pSQL((string) $value);
            }

            return addslashes((string) $value);
        };

        $legacyCarrierId = (int) $db->getValue(
            sprintf(
                "SELECT cc.id_carrier_company FROM `%s` cc WHERE cc.shortname = '%s'",
                $tableCarrierCompany,
                $escape($shortName)
            )
        );

        if ($legacyCarrierId <= 0) {
            return [];
        }

        $rows = (array) $db->executeS(
            sprintf(
                'SELECT cfg.name, cfg.value FROM `%s` cfg WHERE cfg.id_carrier_company = %d',
                $tableConfiguration,
                $legacyCarrierId
            )
        );

        $values = [];

        foreach ($rows as $row) {
            $name = (string) ($row['name'] ?? '');

            if ('' === $name || isset($values[$name])) {
                continue;
            }

            $values[$name] = $row['value'] ?? null;
        }

        return $values;
    }

    /**
     * @param iterable<mixed> $carrierAdapters
     * @return array<string, array<int, array{
     *     name: string,
     *     value: mixed,
     *     label?: ?string,
     *     required?: bool,
     *     description?: ?string,
     *     legacy?: array<int, string>
     * }>>
     */
    private function buildAdapterDefaults(iterable $carrierAdapters): array
    {
        $map = [];

        foreach ($carrierAdapters as $adapter) {
            if (!$adapter instanceof CarrierAdapterInterface) {
                continue;
            }

            $defaults = $adapter::getDefaultConfiguration();
            if (!is_array($defaults) || empty($defaults)) {
                continue;
            }

            $normalized = [];
            foreach ($defaults as $definition) {
                if (!is_array($definition)) {
                    continue;
                }

                $name = isset($definition['name']) ? trim((string) $definition['name']) : '';
                if ('' === $name) {
                    continue;
                }

                $label = null;
                if (isset($definition['label'])) {
                    $label = (string) $definition['label'];
                    if ('' === trim($label)) {
                        $label = null;
                    }
                }

                $description = null;
                if (isset($definition['description'])) {
                    $description = trim((string) $definition['description']);
                    if ('' === $description) {
                        $description = null;
                    }
                }

                $legacyKeys = [];
                if (isset($definition['legacy']) && is_array($definition['legacy'])) {
                    foreach ($definition['legacy'] as $legacyName) {
                        $legacyKey = trim((string) $legacyName);
                        if ('' === $legacyKey) {
                            continue;
                        }

                        $legacyKeys[] = $legacyKey;
                    }
                }

                $normalized[] = [
                    'name' => $name,
                    'value' => $definition['value'] ?? null,
                    'label' => $label,
                    'required' => isset($definition['required']) ? (bool) $definition['required'] : true,
                    'description' => $description,
                    'legacy' => $legacyKeys,
                ];
            }

            if (empty($normalized)) {
                continue;
            }

            $map[strtoupper($adapter->getCode())] = $normalized;
        }

        return $map;
    }

    /**
     * @return array<int, array{
     *     name: string,
     *     value: mixed,
     *     label?: ?string,
     *     required?: bool,
     *     description?: ?string,
     *     legacy?: array<int, string>
     * }>
     */
    private function getDefaultsForCarrier(Carrier $carrier): array
    {
        $code = strtoupper($carrier->getShortName());

        return $this->defaultDefinitions[$code] ?? [];
    }

    /**
     * @return array<int, array{
     *     name: string,
     *     value: mixed,
     *     label: string,
     *     required?: bool,
     *     description?: ?string,
     *     legacy?: array<int, string>
     * }>
     */
    public function getDefaultDefinitionsForCarrier(Carrier $carrier): array
    {
        $definitions = $this->getDefaultsForCarrier($carrier);
        if (empty($definitions)) {
            return [];
        }

        foreach ($definitions as $index => $definition) {
            if (!is_array($definition)) {
                continue;
            }

            $name = isset($definition['name']) ? (string) $definition['name'] : '';
            if ('' === $name) {
                continue;
            }

            $label = $definition['label'] ?? null;
            $definitions[$index]['label'] = (string) ($label ?: $name);
            $definitions[$index]['required'] = isset($definition['required']) ? (bool) $definition['required'] : true;
            if (isset($definition['description'])) {
                $description = trim((string) $definition['description']);
                $definitions[$index]['description'] = '' === $description ? null : $description;
            }
        }

        return $definitions;
    }

    /**
     * @return string[]
     */
    public function getDefaultNamesForCarrier(Carrier $carrier): array
    {
        return array_map(
            static fn (array $definition): string => (string) $definition['name'],
            $this->getDefaultsForCarrier($carrier)
        );
    }

    private function tableExists(string $table): bool
    {
        try {
            $method = method_exists($this->connection, 'createSchemaManager') ? 'createSchemaManager' : 'getSchemaManager';
            $schemaManager = $this->connection->{$method}();

            return $schemaManager->tablesExist([$table]);
        } catch (\Throwable $exception) {
            return false;
        }
    }

    private function ensureSchema(): void
    {
        if ($this->schemaChecked) {
            return;
        }

        $this->schemaChecked = true;

        try {
            $method = method_exists($this->connection, 'createSchemaManager') ? 'createSchemaManager' : 'getSchemaManager';
            $schemaManager = $this->connection->{$method}();
            $tableName = CarrierConfiguration::TABLE_NAME;

            if (!$schemaManager->tablesExist([$tableName])) {
                return;
            }

            $columns = $schemaManager->listTableColumns($tableName);
            $hasPrimaryColumn = isset($columns['id_carrier_configuration']);
            $hasLegacyColumn = isset($columns['id_configuration']);

            if (!$hasPrimaryColumn && $hasLegacyColumn) {
                $this->connection->executeStatement(
                    sprintf(
                        'ALTER TABLE `%s` CHANGE `id_configuration` `id_carrier_configuration` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT',
                        $tableName
                    )
                );

                $columns = $schemaManager->listTableColumns($tableName);
                $hasPrimaryColumn = isset($columns['id_carrier_configuration']);
            }

            if (!$hasPrimaryColumn) {
                $this->connection->executeStatement(
                    sprintf(
                        'ALTER TABLE `%s` ADD `id_carrier_configuration` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT FIRST',
                        $tableName
                    )
                );
            }

            $indexes = $schemaManager->listTableIndexes($tableName);
            $hasPrimaryKey = false;

            foreach ($indexes as $index) {
                if ($index->isPrimary()) {
                    $hasPrimaryKey = in_array('id_carrier_configuration', $index->getColumns(), true);
                    if (!$hasPrimaryKey) {
                        $this->connection->executeStatement(
                            sprintf('ALTER TABLE `%s` DROP PRIMARY KEY', $tableName)
                        );
                    }
                    break;
                }
            }

            if (!$hasPrimaryKey) {
                $this->connection->executeStatement(
                    sprintf('ALTER TABLE `%s` ADD PRIMARY KEY (`id_carrier_configuration`)', $tableName)
                );
            }

            $columns = $schemaManager->listTableColumns($tableName);
            $expectedColumns = [
                'id_shop_group' => 'INT(11) UNSIGNED NULL DEFAULT NULL',
                'id_shop' => 'INT(11) UNSIGNED NULL DEFAULT NULL',
                'id_carrier' => 'INT(11) UNSIGNED NULL DEFAULT NULL',
                'id_type_shipment' => 'INT(11) UNSIGNED NULL DEFAULT NULL',
                'name' => 'VARCHAR(254) NOT NULL',
                'value' => 'TEXT NULL DEFAULT NULL',
                'is_required' => 'TINYINT(1) UNSIGNED NOT NULL DEFAULT 0',
                'date_add' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP',
                'date_upd' => 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
            ];

            foreach ($expectedColumns as $columnName => $definition) {
                if (isset($columns[$columnName])) {
                    continue;
                }

                $this->connection->executeStatement(
                    sprintf('ALTER TABLE `%s` ADD `%s` %s', $tableName, $columnName, $definition)
                );
            }

            $indexes = $schemaManager->listTableIndexes($tableName);
            $uniqueName = 'uniq_carrier_config_scope';
            $hasUnique = false;
            foreach ($indexes as $index) {
                if ($index->getName() === $uniqueName) {
                    $hasUnique = true;
                    break;
                }
            }

            if (!$hasUnique) {
                try {
                    $this->connection->executeStatement(
                        sprintf(
                            'CREATE UNIQUE INDEX `%s` ON `%s` (`id_carrier`, `id_type_shipment`, `id_shop_group`, `id_shop`, `name`)',
                            $uniqueName,
                            $tableName
                        )
                    );
                } catch (\Throwable $exception) {
                    // Ignore: duplicates or legacy data may prevent creating unique index.
                }
            }
        } catch (\Throwable $exception) {
            // Best effort: schema issues will surface when queries execute.
        }
    }
}
