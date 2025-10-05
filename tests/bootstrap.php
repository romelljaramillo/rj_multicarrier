<?php
/**
 * Test bootstrap for the rj_multicarrier module.
 */
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

if (!defined('_PS_MODULE_DIR_')) {
    define('_PS_MODULE_DIR_', sys_get_temp_dir() . DIRECTORY_SEPARATOR);
}

if (!defined('RJ_MULTICARRIER_LABEL_DIR')) {
    define('RJ_MULTICARRIER_LABEL_DIR', sys_get_temp_dir() . '/rj_multicarrier_labels/');
}
