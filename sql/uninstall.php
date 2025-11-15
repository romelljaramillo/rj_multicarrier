<?php
/**
 * Database uninstall script for RJ Multicarrier module.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

$sql = [
    'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'rj_multicarrier_label_shop`',
    'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'rj_multicarrier_label`',
    'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'rj_multicarrier_shipment_shop`',
    'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'rj_multicarrier_shipment`',
    'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'rj_multicarrier_info_shipment_shop`',
    'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'rj_multicarrier_info_shipment`',
    'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'rj_multicarrier_configuration_shop`',
    'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'rj_multicarrier_validation_rule`',
    'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'rj_multicarrier_configuration`',
    'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'rj_multicarrier_carrier_shop`',
    'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'rj_multicarrier_log`',
    'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'rj_multicarrier_type_shipment`',
    'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'rj_multicarrier_carrier`',
    'DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'rj_multicarrier_configuration`',
];

foreach ($sql as $query) {
    if (false === \Db::getInstance()->execute($query)) {
        return false;
    }
}

return true;
