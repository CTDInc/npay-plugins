<?php

/**
 * NPay Payment Gateway — NukeViet installer
 *
 * Run from the NukeViet CLI (or admin module installer). Inserts the
 * default settings rows for the shops module so the admin UI can show
 * the gateway right after copy-paste.
 */

if (!defined('NV_ADMIN') && !defined('NV_SYSTEM') && PHP_SAPI !== 'cli') {
    die('Stop!!!');
}

if (!defined('NV_ROOTDIR')) {
    define('NV_SYSTEM', true);
    define('NV_ROOTDIR', realpath(dirname(__FILE__) . '/../../../../'));
    require NV_ROOTDIR . '/includes/mainfile.php';
}

global $db, $global_config;

$settings = array(
    'npay_api_base'       => 'https://api.npay.vn',
    'npay_api_token'      => '',
    'npay_bank_code'      => '',
    'npay_account_number' => '',
    'npay_account_name'   => '',
    'npay_prefix'         => 'NPAY',
    'npay_qr_template'    => 'compact',
);

$installed = 0;
$skipped   = 0;

foreach ($settings as $name => $value) {
    try {
        $check = $db->query("SELECT config_value FROM " . NV_CONFIG_GLOBALTABLE
            . " WHERE lang='sys' AND module='shops' AND config_name=" . $db->quote($name))->fetch();
        if ($check) {
            $skipped++;
            continue;
        }
        $sql = "INSERT INTO " . NV_CONFIG_GLOBALTABLE
             . " (lang, module, config_name, config_value) VALUES ("
             . $db->quote('sys') . ", "
             . $db->quote('shops') . ", "
             . $db->quote($name) . ", "
             . $db->quote($value) . ")";
        $db->query($sql);
        $installed++;
    } catch (Exception $e) {
        // Continue — admin can re-run.
    }
}

// Optional payment log table (will silently fail if it already exists).
try {
    $db->query("CREATE TABLE IF NOT EXISTS " . NV_PREFIXLANG . "_shops_payment_log (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        gateway VARCHAR(32) NOT NULL DEFAULT '',
        pay_id VARCHAR(64) NOT NULL DEFAULT '',
        order_id INT UNSIGNED NOT NULL DEFAULT 0,
        amount DECIMAL(18,2) NOT NULL DEFAULT 0,
        reference VARCHAR(128) NOT NULL DEFAULT '',
        bank VARCHAR(64) NOT NULL DEFAULT '',
        tx_date VARCHAR(32) NOT NULL DEFAULT '',
        raw LONGTEXT NULL,
        created_at INT UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        KEY idx_pay_id (pay_id),
        KEY idx_order_id (order_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (Exception $e) {
    // ignore
}

if (PHP_SAPI === 'cli') {
    echo "NPay install complete. inserted={$installed}, skipped={$skipped}\n";
}

return array(
    'success'   => true,
    'inserted'  => $installed,
    'skipped'   => $skipped,
);
