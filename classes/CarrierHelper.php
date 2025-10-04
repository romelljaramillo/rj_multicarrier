<?php
/**
 * Multi Carrier Helper Class
 *
 * @author    Romell Jaramillo
 * @copyright 2025 Romell Jaramillo
 * @license   MIT License
 */

namespace RjMulticarrier;

if (!defined('_PS_VERSION_')) {
    exit;
}

class CarrierHelper
{
    /**
     * Get all carriers with multi carrier configuration
     *
     * @param int $id_shop Shop ID
     * @param int $id_lang Language ID
     * @return array
     */
    public static function getCarriers($id_shop = null, $id_lang = null)
    {
        if (!$id_shop) {
            $id_shop = (int)\Context::getContext()->shop->id;
        }
        
        if (!$id_lang) {
            $id_lang = (int)\Context::getContext()->language->id;
        }

        $sql = 'SELECT c.*, mc.priority, mc.active as mc_active
                FROM ' . _DB_PREFIX_ . 'carrier c
                LEFT JOIN ' . _DB_PREFIX_ . 'rj_multicarrier mc ON (c.id_carrier = mc.id_carrier)
                WHERE c.deleted = 0
                AND (mc.id_shop = ' . (int)$id_shop . ' OR mc.id_shop IS NULL)
                ORDER BY mc.priority ASC, c.name ASC';

        return \Db::getInstance()->executeS($sql);
    }

    /**
     * Get carrier configuration
     *
     * @param int $id_carrier Carrier ID
     * @param int $id_shop Shop ID
     * @return array|false
     */
    public static function getCarrierConfig($id_carrier, $id_shop = null)
    {
        if (!$id_shop) {
            $id_shop = (int)\Context::getContext()->shop->id;
        }

        $sql = 'SELECT *
                FROM ' . _DB_PREFIX_ . 'rj_multicarrier
                WHERE id_carrier = ' . (int)$id_carrier . '
                AND id_shop = ' . (int)$id_shop;

        return \Db::getInstance()->getRow($sql);
    }

    /**
     * Save carrier configuration
     *
     * @param int $id_carrier Carrier ID
     * @param array $data Configuration data
     * @param int $id_shop Shop ID
     * @return bool
     */
    public static function saveCarrierConfig($id_carrier, $data, $id_shop = null)
    {
        if (!$id_shop) {
            $id_shop = (int)\Context::getContext()->shop->id;
        }

        $existing = self::getCarrierConfig($id_carrier, $id_shop);

        if ($existing) {
            return \Db::getInstance()->update(
                'rj_multicarrier',
                [
                    'active' => (int)($data['active'] ?? 1),
                    'priority' => (int)($data['priority'] ?? 0),
                    'date_upd' => date('Y-m-d H:i:s'),
                ],
                'id_carrier = ' . (int)$id_carrier . ' AND id_shop = ' . (int)$id_shop
            );
        } else {
            return \Db::getInstance()->insert(
                'rj_multicarrier',
                [
                    'id_carrier' => (int)$id_carrier,
                    'id_shop' => (int)$id_shop,
                    'active' => (int)($data['active'] ?? 1),
                    'priority' => (int)($data['priority'] ?? 0),
                    'date_add' => date('Y-m-d H:i:s'),
                    'date_upd' => date('Y-m-d H:i:s'),
                ]
            );
        }
    }

    /**
     * Delete carrier configuration
     *
     * @param int $id_carrier Carrier ID
     * @param int $id_shop Shop ID
     * @return bool
     */
    public static function deleteCarrierConfig($id_carrier, $id_shop = null)
    {
        if (!$id_shop) {
            $id_shop = (int)\Context::getContext()->shop->id;
        }

        return \Db::getInstance()->delete(
            'rj_multicarrier',
            'id_carrier = ' . (int)$id_carrier . ' AND id_shop = ' . (int)$id_shop
        );
    }

    /**
     * Get carrier rules
     *
     * @param int $id_carrier Carrier ID
     * @return array
     */
    public static function getCarrierRules($id_carrier)
    {
        $config = self::getCarrierConfig($id_carrier);
        
        if (!$config) {
            return [];
        }

        $sql = 'SELECT *
                FROM ' . _DB_PREFIX_ . 'rj_multicarrier_rule
                WHERE id_rj_multicarrier = ' . (int)$config['id_rj_multicarrier'] . '
                AND active = 1
                ORDER BY date_add DESC';

        return \Db::getInstance()->executeS($sql);
    }

    /**
     * Save carrier rule
     *
     * @param int $id_carrier Carrier ID
     * @param string $rule_type Rule type
     * @param string $rule_value Rule value
     * @return bool
     */
    public static function saveCarrierRule($id_carrier, $rule_type, $rule_value)
    {
        $config = self::getCarrierConfig($id_carrier);
        
        if (!$config) {
            return false;
        }

        return \Db::getInstance()->insert(
            'rj_multicarrier_rule',
            [
                'id_rj_multicarrier' => (int)$config['id_rj_multicarrier'],
                'rule_type' => pSQL($rule_type),
                'rule_value' => pSQL($rule_value),
                'active' => 1,
                'date_add' => date('Y-m-d H:i:s'),
                'date_upd' => date('Y-m-d H:i:s'),
            ]
        );
    }
}
