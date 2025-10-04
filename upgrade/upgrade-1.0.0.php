<?php
/**
 * Multi Carrier Upgrade Script
 *
 * @author    Romell Jaramillo
 * @copyright 2025 Romell Jaramillo
 * @license   MIT License
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Upgrade to version 1.0.0
 *
 * @param object $module Module instance
 * @return bool
 */
function upgrade_module_1_0_0($module)
{
    // No upgrade needed for initial version
    return true;
}
