<?php
/**
 * Database installation script for RJ Multicarrier module.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

$sql = [];

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'rj_multicarrier_configuration` (
    `id_configuration` INT(11) NOT NULL AUTO_INCREMENT,
    `firstname` VARCHAR(100) NOT NULL,
    `lastname` VARCHAR(100) NOT NULL,
    `company` VARCHAR(100) NULL DEFAULT NULL,
    `additionalname` VARCHAR(100) NULL DEFAULT NULL,
    `id_country` INT(10) UNSIGNED NOT NULL,
    `state` VARCHAR(255) NOT NULL,
    `city` VARCHAR(255) NOT NULL,
    `street` VARCHAR(255) NOT NULL,
    `number` VARCHAR(100) NOT NULL,
    `postcode` VARCHAR(100) NOT NULL,
    `additionaladdress` VARCHAR(100) NULL DEFAULT NULL,
    `isbusiness` VARCHAR(100) NULL DEFAULT NULL,
    `email` VARCHAR(100) NULL DEFAULT NULL,
    `phone` VARCHAR(100) NOT NULL,
    `vatnumber` VARCHAR(100) NULL DEFAULT NULL,
    `label_prefix` VARCHAR(64) NULL DEFAULT NULL,
    `cod_module` VARCHAR(255) NULL DEFAULT NULL,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `date_add` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_upd` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_configuration`),
    INDEX `id_country` (`id_country`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;';

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'rj_multicarrier_configuration_shop` (
    `id_configuration` INT(11) NOT NULL,
    `id_shop` INT(11) NOT NULL,
    PRIMARY KEY (`id_configuration`, `id_shop`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;';

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'rj_multicarrier_validation_rule` (
    `id_validation_rule` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(191) NOT NULL,
    `priority` INT NOT NULL DEFAULT 0,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `shop_id` INT(11) NULL DEFAULT NULL,
    `product_ids` JSON NULL,
    `category_ids` JSON NULL,
    `zone_ids` JSON NULL,
    `country_ids` JSON NULL,
    `min_weight` DOUBLE NULL,
    `max_weight` DOUBLE NULL,
    `allow_ids` JSON NULL,
    `deny_ids` JSON NULL,
    `add_ids` JSON NULL,
    `created_at` DATETIME NULL,
    `updated_at` DATETIME NULL,
    PRIMARY KEY (`id_validation_rule`),
    INDEX `idx_validation_rule_active` (`active`),
    INDEX `idx_validation_rule_priority` (`priority`),
    INDEX `idx_validation_rule_shop` (`shop_id`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;';

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'rj_multicarrier_carrier` (
    `id_carrier` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(50) NOT NULL,
    `shortname` VARCHAR(4) NOT NULL,
    `icon` VARCHAR(250) NULL DEFAULT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `supports_api` TINYINT(1) NOT NULL DEFAULT 0,
    `adapter_service` VARCHAR(191) NULL DEFAULT NULL,
    `configuration_schema` LONGTEXT NULL DEFAULT NULL,
    `default_payload` LONGTEXT NULL DEFAULT NULL,
    `delete` TINYINT(1) NOT NULL DEFAULT 0,
    `date_add` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_upd` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_carrier`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;';

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'rj_multicarrier_carrier_shop` (
    `id_carrier` INT(10) UNSIGNED NOT NULL,
    `id_shop` INT(11) NOT NULL,
    PRIMARY KEY (`id_carrier`, `id_shop`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;';

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'rj_multicarrier_type_shipment` (
    `id_type_shipment` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_carrier` INT(10) UNSIGNED NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `id_bc` VARCHAR(100) NOT NULL,
    `id_reference_carrier` INT(10) NULL DEFAULT NULL,
    `active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `settings` JSON NULL DEFAULT NULL,
    `date_add` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_upd` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_type_shipment`),
    INDEX `idx_carrier` (`id_carrier`),
    INDEX `idx_reference` (`id_reference_carrier`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;';

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'rj_multicarrier_info_shipment` (
    `id_info_shipment` INT(11) NOT NULL AUTO_INCREMENT,
    `id_order` INT(10) NOT NULL,
    `id_reference_carrier` INT(10) NOT NULL,
    `id_type_shipment` INT(10) UNSIGNED NOT NULL,
    `id_employee` INT(10) NULL DEFAULT NULL,
    `logged_employee_id` INT(10) NULL DEFAULT NULL,
    `quantity` INT(10) NOT NULL DEFAULT 1,
    `weight` DECIMAL(20,6) NOT NULL DEFAULT 0.000000,
    `length` DECIMAL(20,6) NULL DEFAULT NULL,
    `width` DECIMAL(20,6) NULL DEFAULT NULL,
    `height` DECIMAL(20,6) NULL DEFAULT NULL,
    `cash_ondelivery` DECIMAL(20,6) NULL DEFAULT NULL,
    `message` VARCHAR(255) NULL DEFAULT NULL,
    `hour_from` TIME NULL DEFAULT NULL,
    `hour_until` TIME NULL DEFAULT NULL,
    `retorno` INT(10) NULL DEFAULT NULL,
    `rcs` TINYINT(1) NOT NULL DEFAULT 0,
    `vsec` DECIMAL(20,6) NULL DEFAULT NULL,
    `dorig` VARCHAR(255) NULL DEFAULT NULL,
    `date_add` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_upd` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_info_shipment`),
    INDEX `idx_order` (`id_order`),
    INDEX `idx_reference` (`id_reference_carrier`),
    INDEX `idx_type_shipment` (`id_type_shipment`),
    INDEX `idx_employee` (`id_employee`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;';

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'rj_multicarrier_info_shipment_shop` (
    `id_info_shipment` INT(11) NOT NULL,
    `id_shop` INT(11) NOT NULL,
    PRIMARY KEY (`id_info_shipment`, `id_shop`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;';

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'rj_multicarrier_shipment` (
    `id_shipment` INT(11) NOT NULL AUTO_INCREMENT,
    `id_order` INT(10) NOT NULL,
    `reference_order` VARCHAR(100) NULL DEFAULT NULL,
    `num_shipment` VARCHAR(100) NULL DEFAULT NULL,
    `id_carrier` INT(10) UNSIGNED NULL DEFAULT NULL,
    `id_info_shipment` INT(10) NOT NULL,
    `id_employee` INT(10) NULL DEFAULT NULL,
    `logged_employee_id` INT(10) NULL DEFAULT NULL,
    `account` VARCHAR(100) NULL DEFAULT NULL,
    `product` VARCHAR(100) NULL DEFAULT NULL,
    `request` LONGTEXT NULL DEFAULT NULL,
    `response` LONGTEXT NULL DEFAULT NULL,
    `delete` TINYINT(1) NOT NULL DEFAULT 0,
    `date_add` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_upd` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_shipment`),
    INDEX `idx_order` (`id_order`),
    INDEX `idx_package` (`id_info_shipment`),
    INDEX `idx_carrier` (`id_carrier`),
    INDEX `idx_shipment_employee` (`id_employee`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;';

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'rj_multicarrier_shipment_shop` (
    `id_shipment` INT(11) NOT NULL,
    `id_shop` INT(11) NOT NULL,
    PRIMARY KEY (`id_shipment`, `id_shop`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;';

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'rj_multicarrier_label` (
    `id_label` INT(11) NOT NULL AUTO_INCREMENT,
    `id_shipment` INT(11) NOT NULL,
    `package_id` VARCHAR(50) NULL DEFAULT NULL,
    `tracker_code` VARCHAR(100) NULL DEFAULT NULL,
    `label_type` VARCHAR(100) NULL DEFAULT NULL,
    `pdf` LONGTEXT NULL DEFAULT NULL,
    `print` TINYINT(1) NOT NULL DEFAULT 0,
    `date_add` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_upd` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_label`),
    INDEX `idx_shipment` (`id_shipment`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;';

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'rj_multicarrier_label_shop` (
    `id_label` INT(11) NOT NULL,
    `id_shop` INT(11) NOT NULL,
    PRIMARY KEY (`id_label`, `id_shop`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;';

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'rj_multicarrier_log` (
    `id_carrier_log` INT(11) NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(250) NOT NULL,
    `id_order` INT(10) NOT NULL,
    `id_shop` INT(11) UNSIGNED NOT NULL,
    `request` LONGTEXT NULL DEFAULT NULL,
    `response` LONGTEXT NULL DEFAULT NULL,
    `date_add` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_upd` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_carrier_log`),
    INDEX `idx_order` (`id_order`),
    INDEX `idx_shop` (`id_shop`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;';

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'rj_multicarrier_carrier_configuration` (
    `id_carrier_configuration` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_shop_group` INT(11) UNSIGNED NULL DEFAULT NULL,
    `id_shop` INT(11) UNSIGNED NULL DEFAULT NULL,
    `id_carrier` INT(11) UNSIGNED NULL DEFAULT NULL,
    `id_type_shipment` INT(11) UNSIGNED NULL DEFAULT NULL,
    `name` VARCHAR(254) NOT NULL,
    `value` TEXT NULL DEFAULT NULL,
    `is_required` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    `field_group` VARCHAR(64) NULL DEFAULT NULL,
    `field_type` VARCHAR(32) NULL DEFAULT \'text\',
    `payload_direction` VARCHAR(16) NULL DEFAULT NULL,
    `payload_key` VARCHAR(191) NULL DEFAULT NULL,
    `payload_source` VARCHAR(191) NULL DEFAULT NULL,
    `position` INT(11) NOT NULL DEFAULT 0,
    `is_dynamic` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0,
    `date_add` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_upd` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_carrier_configuration`),
    KEY `idx_name` (`name`),
    KEY `idx_shop` (`id_shop`),
    KEY `idx_shop_group` (`id_shop_group`),
    KEY `idx_carrier` (`id_carrier`),
    KEY `idx_type_shipment` (`id_type_shipment`),
    UNIQUE KEY `uniq_carrier_config_scope` (`id_carrier`, `id_type_shipment`, `id_shop_group`, `id_shop`, `name`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;';


foreach ($sql as $query) {
    if (false === \Db::getInstance()->execute($query)) {
        return false;
    }
}

return true;
