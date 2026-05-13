<?php

/**
 * NPay Payment Gateway — NukeViet uninstaller
 *
 * Removes only the configuration rows. The payment log table is left in
 * place so historical records are preserved; drop it manually if needed.
 */

if (!defined('NV_ADMIN') && !defined('NV_SYSTEM') && PHP_SAPI !== 'cli') {
    die('Stop!!!');
}

if (!defined('NV_ROOTDIR')) {
    define('NV_SYSTEM', true);
    define('NV_ROOTDIR', realpath(dirname(__FILE__) . '/../../../../'));
    require NV_ROOTDIR . '/includes/mainfile.php';
}

global $db;

$keys = array(
    'npay_api_base',
    'npay_api_token',
    'npay_bank_code',
    'npay_account_number',
    'npay_account_name',
    'npay_prefix',
    'npay_qr_template',
);

$removed = 0;
foreach ($keys as $name) {
    try {
        $db->query("DELETE FROM " . NV_CONFIG_GLOBALTABLE
            . " WHERE lang='sys' AND module='shops' AND config_name=" . $db->quote($name));
        $removed++;
    } catch (Exception $e) {
        // ignore
    }
}

if (PHP_SAPI === 'cli') {
    echo "NPay uninstall complete. removed={$removed} config rows.\n";
    echo "Note: nv_*_shops_payment_log table is preserved. Drop manually if desired.\n";
}

return array('success' => true, 'removed' => $removed);
