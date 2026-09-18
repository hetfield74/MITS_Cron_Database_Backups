<?php
/**
 * --------------------------------------------------------------
 * MITS CronDatabaseBackups - external synchronization dispatcher
 * Version: 1.8.2
 * --------------------------------------------------------------
 */

chdir(dirname(__FILE__) . '/../../');
include_once('includes/application_top.php');
require_once DIR_FS_DOCUMENT_ROOT . 'includes/mits_cron_database_sync.php';

header('Content-Type: text/plain; charset=UTF-8');

if (!defined('MODULE_MITS_CRON_DATABASE_BACKUPS_STATUS') || MODULE_MITS_CRON_DATABASE_BACKUPS_STATUS !== 'true') {
    http_response_code(403);
    echo "MITS_CDB_SYNC_DISABLED\n";
    exit;
}
if (!defined('MODULE_MITS_CRON_DATABASE_BACKUPS_HASH') || MODULE_MITS_CRON_DATABASE_BACKUPS_HASH === '') {
    http_response_code(403);
    echo "MITS_CDB_SYNC_HASH_MISSING\n";
    exit;
}

$given = isset($_GET['pw']) ? (string)$_GET['pw'] : '';
$valid = function_exists('hash_equals')
    ? hash_equals((string)MODULE_MITS_CRON_DATABASE_BACKUPS_HASH, $given)
    : ((string)MODULE_MITS_CRON_DATABASE_BACKUPS_HASH === $given);
if (!$valid) {
    http_response_code(403);
    echo "MITS_CDB_SYNC_FORBIDDEN\n";
    exit;
}

$profile_id = isset($_GET['profile']) ? (int)$_GET['profile'] : 0;
if ($profile_id > 0) {
    $profile_result = mits_cdb_sync_run_profile($profile_id, false);
    if (empty($profile_result['success'])) {
        http_response_code(500);
        echo 'MITS_CDB_SYNC_FAILED profile=' . $profile_id . ' message=' . str_replace(array("\r", "\n"), ' ', (string)$profile_result['message']) . "\n";
        exit;
    }
    echo 'MITS_CDB_SYNC_SUCCESS profile=' . $profile_id . "\n";
    exit;
}

$result = mits_cdb_sync_run_due_profiles();
if ((int)$result['failed'] > 0) {
    http_response_code(500);
    echo 'MITS_CDB_SYNC_FAILED processed=' . (int)$result['processed'] . ' success=' . (int)$result['success'] . ' failed=' . (int)$result['failed'] . "\n";
    exit;
}

echo 'MITS_CDB_SYNC_SUCCESS processed=' . (int)$result['processed'] . ' success=' . (int)$result['success'] . "\n";
