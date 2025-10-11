<?php
/**
 * Database installation script for RJ Multicarrier module.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

$sql = [];

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'rj_multicarrier_infoshop` (
    `id_infoshop` INT(11) NOT NULL AUTO_INCREMENT,
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
    `date_add` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_upd` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_infoshop`),
    INDEX `id_country` (`id_country`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;';

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'rj_multicarrier_infoshop_shop` (
    `id_infoshop` INT(11) NOT NULL,
    `id_shop` INT(11) NOT NULL,
    PRIMARY KEY (`id_infoshop`, `id_shop`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;';

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'rj_multicarrier_company` (
    `id_carrier_company` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(50) NOT NULL,
    `shortname` VARCHAR(4) NOT NULL,
    `icon` VARCHAR(250) NULL DEFAULT NULL,
    `date_add` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_upd` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_carrier_company`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;';

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'rj_multicarrier_company_shop` (
    `id_company_shop` INT(11) NOT NULL AUTO_INCREMENT,
    `id_shop` INT(11) NOT NULL,
    `id_carrier_company` INT(10) UNSIGNED NOT NULL,
    `date_add` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_upd` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_company_shop`),
    INDEX `idx_company` (`id_carrier_company`),
    INDEX `idx_shop` (`id_shop`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;';

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'rj_multicarrier_type_shipment` (
    `id_type_shipment` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_carrier_company` INT(10) UNSIGNED NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `id_bc` VARCHAR(100) NOT NULL,
    `id_reference_carrier` INT(10) NULL DEFAULT NULL,
    `active` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1,
    `date_add` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_upd` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_type_shipment`),
    INDEX `idx_company` (`id_carrier_company`),
    INDEX `idx_reference` (`id_reference_carrier`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;';

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'rj_multicarrier_infopackage` (
    `id_infopackage` INT(11) NOT NULL AUTO_INCREMENT,
    `id_order` INT(10) NOT NULL,
    `id_reference_carrier` INT(10) NOT NULL,
    `id_type_shipment` INT(10) UNSIGNED NOT NULL,
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
    PRIMARY KEY (`id_infopackage`),
    INDEX `idx_order` (`id_order`),
    INDEX `idx_reference` (`id_reference_carrier`),
    INDEX `idx_type_shipment` (`id_type_shipment`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;';

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'rj_multicarrier_infopackage_shop` (
    `id_infopackage` INT(11) NOT NULL,
    `id_shop` INT(11) NOT NULL,
    PRIMARY KEY (`id_infopackage`, `id_shop`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;';

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'rj_multicarrier_shipment` (
    `id_shipment` INT(11) NOT NULL AUTO_INCREMENT,
    `id_order` INT(10) NOT NULL,
    `reference_order` VARCHAR(100) NULL DEFAULT NULL,
    `num_shipment` VARCHAR(100) NULL DEFAULT NULL,
    `id_carrier_company` INT(10) UNSIGNED NULL DEFAULT NULL,
    `id_infopackage` INT(10) NOT NULL,
    `account` VARCHAR(100) NULL DEFAULT NULL,
    `product` VARCHAR(100) NULL DEFAULT NULL,
    `request` LONGTEXT NULL DEFAULT NULL,
    `response` LONGTEXT NULL DEFAULT NULL,
    `delete` TINYINT(1) NOT NULL DEFAULT 0,
    `date_add` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_upd` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_shipment`),
    INDEX `idx_order` (`id_order`),
    INDEX `idx_package` (`id_infopackage`),
    INDEX `idx_company` (`id_carrier_company`)
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

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'rj_multicarrier_configuration` (
    `id_configuration` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_shop_group` INT(11) UNSIGNED NULL DEFAULT NULL,
    `id_shop` INT(11) UNSIGNED NULL DEFAULT NULL,
    `id_carrier_company` INT(11) UNSIGNED NULL DEFAULT NULL,
    `name` VARCHAR(254) NOT NULL,
    `value` TEXT NULL DEFAULT NULL,
    `date_add` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `date_upd` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id_configuration`),
    KEY `idx_name` (`name`),
    KEY `idx_shop` (`id_shop`),
    KEY `idx_shop_group` (`id_shop_group`),
    KEY `idx_company` (`id_carrier_company`)
) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4;';

$sql[] = 'INSERT INTO `' . _DB_PREFIX_ . 'rj_multicarrier_company` (`id_carrier_company`, `name`, `shortname`, `icon`) VALUES
    (1, \'Default Carrier\', \'DEF\', NULL),
    (2, \'DHL\', \'DHL\', NULL),
    (3, \'Correo Express\', \'CEX\', NULL),
    (4, \'GOI\', \'GOI\', NULL)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `shortname` = VALUES(`shortname`), `icon` = VALUES(`icon`);';

$sql[] = 'INSERT INTO `' . _DB_PREFIX_ . 'rj_multicarrier_type_shipment` (`id_type_shipment`, `id_carrier_company`, `name`, `id_bc`, `id_reference_carrier`, `active`) VALUES
    (1, 3, \'PAQ 10\', \'61\', NULL, 0),
    (2, 3, \'PAQ 14\', \'62\', NULL, 0),
    (3, 3, \'PAQ 24\', \'63\', NULL, 0),
    (4, 3, \'Baleares\', \'66\', NULL, 0),
    (5, 3, \'Canarias Express\', \'67\', NULL, 0),
    (6, 3, \'Canarias Aéreo\', \'68\', NULL, 0),
    (7, 3, \'Canarias Marítimo\', \'69\', NULL, 0),
    (8, 3, \'CEX Portugal Óptica\', \'73\', NULL, 0),
    (9, 3, \'Paquetería Óptica\', \'76\', NULL, 0),
    (10, 3, \'Internacional Express\', \'91\', NULL, 0),
    (11, 3, \'Internacional Estandard\', \'90\', NULL, 0),
    (12, 3, \'Paq Empresa 14\', \'92\', NULL, 0),
    (13, 3, \'ePaq 24\', \'93\', NULL, 0),
    (14, 3, \'Campaña CEX\', \'27\', NULL, 0),
    (15, 3, \'Entrega en Oficina\', \'44\', NULL, 0),
    (16, 3, \'Entrega + Recogida Multichrono\', \'54\', NULL, 0),
    (17, 3, \'Entrega + recogida + Manip Multichrono\', \'55\', NULL, 0),
    (18, 2, \'DHL PARCEL IBERIA\', \'IBERIA\', NULL, 0),
    (19, 4, \'Goi carrier\', \'T,M\', 2, 0),
    (20, 4, \'GOI - Montaje\', \'T,I,M\', 1, 0)
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `id_bc` = VALUES(`id_bc`), `id_reference_carrier` = VALUES(`id_reference_carrier`), `active` = VALUES(`active`);';

foreach ($sql as $query) {
    if (false === \Db::getInstance()->execute($query)) {
        return false;
    }
}

return true;
