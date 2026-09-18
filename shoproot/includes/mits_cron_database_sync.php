<?php
/**
 * --------------------------------------------------------------
 * File: mits_cron_database_sync.php
 * Created by PhpStorm
 * Date: 04.09.2026
 * Time: 15:38
 *
 * Author: Hetfield
 * Copyright: (c) 2026 - MerZ IT-SerVice
 * Web: https://www.merz-it-service.de
 * Contact: info@merz-it-service.de
 *
 * Released under the GNU General Public License
 * --------------------------------------------------------------
 */

defined('DIR_FS_DOCUMENT_ROOT') || die('Direct Access to this location is not allowed.');

function mits_cdb_sync_profile_table()
{
    if (defined('TABLE_CONFIGURATION') && substr(TABLE_CONFIGURATION, -13) === 'configuration') {
        return substr(TABLE_CONFIGURATION, 0, -13) . 'mits_cdb_sync_profiles';
    }
    return 'mits_cdb_sync_profiles';
}

function mits_cdb_sync_db_escape($value)
{
    if (function_exists('xtc_db_input')) {
        return xtc_db_input((string)$value);
    }
    return addslashes((string)$value);
}

function mits_cdb_sync_text($constant, $fallback)
{
    return defined($constant) ? constant($constant) : $fallback;
}

function mits_cdb_sync_safe_unlink($file)
{
    $file = (string)$file;
    if ($file === '') {
        return false;
    }
    clearstatcache(true, $file);
    if (!is_file($file) && !is_link($file)) {
        return true;
    }
    return @unlink($file);
}

function mits_cdb_sync_elapsed($started_at)
{
    return round(max(0, microtime(true) - (float)$started_at), 5);
}

function mits_cdb_sync_table_exists($table)
{
    $escaped = str_replace(array('\\', '_', '%'), array('\\\\', '\\_', '\\%'), (string)$table);
    $query = xtc_db_query("SHOW TABLES LIKE '" . mits_cdb_sync_db_escape($escaped) . "'");
    return xtc_db_num_rows($query) > 0;
}

function mits_cdb_sync_profile_task_name($profile_id)
{
    return 'mits_cron_database_sync_profile_' . (int)$profile_id;
}

function mits_cdb_sync_profile_task_file($profile_id)
{
    return rtrim(DIR_FS_DOCUMENT_ROOT, '/\\') . DIRECTORY_SEPARATOR
        . 'api' . DIRECTORY_SEPARATOR . 'scheduled_tasks' . DIRECTORY_SEPARATOR . 'modules' . DIRECTORY_SEPARATOR
        . mits_cdb_sync_profile_task_name($profile_id) . '.php';
}

function mits_cdb_sync_profile_task_row($profile_id)
{
    if (!defined('TABLE_SCHEDULED_TASKS') || !mits_cdb_sync_table_exists(TABLE_SCHEDULED_TASKS)) {
        return false;
    }
    $task = mits_cdb_sync_profile_task_name($profile_id);
    $query = xtc_db_query("SELECT * FROM " . TABLE_SCHEDULED_TASKS . " WHERE tasks='" . mits_cdb_sync_db_escape($task) . "' LIMIT 1");
    if (xtc_db_num_rows($query) < 1) {
        return false;
    }
    return xtc_db_fetch_array($query);
}

function mits_cdb_sync_schedule_time_from_offset($offset)
{
    return gmdate('H:i', max(0, (int)$offset));
}

function mits_cdb_sync_refresh_cronjob_next_event()
{
    if (!defined('TABLE_SCHEDULED_TASKS') || !defined('TABLE_CONFIGURATION')
        || !mits_cdb_sync_table_exists(TABLE_SCHEDULED_TASKS)
        || !mits_cdb_sync_table_exists(TABLE_CONFIGURATION)) {
        return;
    }
    $next_event = time() + 86400;
    $query = xtc_db_query("SELECT time_next FROM " . TABLE_SCHEDULED_TASKS . " WHERE status='1' ORDER BY time_next ASC LIMIT 1");
    if (xtc_db_num_rows($query) > 0) {
        $row = xtc_db_fetch_array($query);
        if (!empty($row['time_next'])) {
            $next_event = (int)$row['time_next'];
        }
    }
    xtc_db_query(
        "UPDATE " . TABLE_CONFIGURATION . " SET configuration_value='" . (int)$next_event . "'"
        . " WHERE configuration_key='CRONJOB_NEXT_EVENT_TIME'"
    );
}

function mits_cdb_sync_schedule_offset($time_hm)
{
    if (!preg_match('/^(\d{1,2}):(\d{2})$/', (string)$time_hm, $match)) {
        $time_hm = '00:00';
    } else {
        $hour = min(23, max(0, (int)$match[1]));
        $minute = min(59, max(0, (int)$match[2]));
        $time_hm = sprintf('%02d:%02d', $hour, $minute);
    }
    $offset = strtotime('1970-01-01 ' . $time_hm . ' UTC');
    return ($offset === false) ? 0 : (int)$offset;
}

function mits_cdb_sync_next_scheduled_task_time($regularity, $unit, $offset)
{
    $regularity = max(1, (int)$regularity);
    $unit = in_array((string)$unit, array('m', 'h', 'd', 'w'), true) ? (string)$unit : 'd';
    if (!function_exists('next_scheduled_time') && defined('DIR_FS_INC') && is_file(DIR_FS_INC . 'next_scheduled_time.inc.php')) {
        require_once DIR_FS_INC . 'next_scheduled_time.inc.php';
    }
    if (function_exists('next_scheduled_time')) {
        return (int)next_scheduled_time($regularity, $unit, (int)$offset);
    }

    $now = time();
    if ($unit === 'm') {
        return $now + ($regularity * 60);
    }
    $step = $regularity * 3600;
    if ($unit === 'd') {
        $step = $regularity * 86400;
    } elseif ($unit === 'w') {
        $step = $regularity * 604800;
    }
    $next = mktime((int)gmdate('H', (int)$offset), (int)gmdate('i', (int)$offset), 0, (int)date('m'), (int)date('d'), (int)date('Y'));
    while ($next <= $now) {
        $next += $step;
    }
    return $next;
}

function mits_cdb_sync_write_profile_task_module($profile_id)
{
    $profile_id = (int)$profile_id;
    if ($profile_id < 1) {
        return array(false, mits_cdb_sync_text('TEXT_MITS_CDB_SYNC_LIB_TASK_INVALID_PROFILE', 'Invalid profile ID.'));
    }
    $file = mits_cdb_sync_profile_task_file($profile_id);
    $dir = dirname($file);
    if (!is_dir($dir)) {
        return array(false, sprintf(mits_cdb_sync_text('TEXT_MITS_CDB_SYNC_LIB_TASK_DIR_MISSING', 'Scheduled Tasks module directory does not exist: %s'), $dir));
    }

    $task = mits_cdb_sync_profile_task_name($profile_id);
    $function = 'cron_' . $task;
    $task_file = basename($file);
    $generated_date = date('d.m.Y');
    $generated_time = date('H:i');
    $contents = "<?php\n"
        . "/**\n"
        . " * --------------------------------------------------------------\n"
        . " * File: " . $task_file . "\n"
        . " * Created by PhpStorm\n"
        . " * Date: " . $generated_date . "\n"
        . " * Time: " . $generated_time . "\n"
        . " *\n"
        . " * Author: Hetfield\n"
        . " * Copyright: (c) 2019 - MerZ IT-SerVice\n"
        . " * Web: https://www.merz-it-service.de\n"
        . " * Contact: info@merz-it-service.de\n"
        . " *\n"
        . " * Released under the GNU General Public License\n"
        . " * --------------------------------------------------------------\n"
        . " */\n\n"
        . "defined('RUN_MODE_TASKS') or die('Direct Access to this location is not allowed.');\n\n"
        . "require_once DIR_FS_CATALOG . 'includes/mits_cron_database_sync.php';\n\n"
        . "/**\n"
        . " * @return bool\n"
        . " */\n"
        . "function " . $function . "()\n{\n"
        . "    if (defined('MODULE_MITS_CRON_DATABASE_BACKUPS_STATUS') && MODULE_MITS_CRON_DATABASE_BACKUPS_STATUS != 'true') {\n"
        . "        return false;\n"
        . "    }\n"
        . "    \$result = mits_cdb_sync_run_profile(" . $profile_id . ", false);\n"
        . "    return !empty(\$result['success']);\n"
        . "}\n";

    if (is_file($file) && is_readable($file) && (string)@file_get_contents($file) === $contents) {
        return array(true, '');
    }
    if (!is_writable($dir)) {
        return array(false, sprintf(mits_cdb_sync_text('TEXT_MITS_CDB_SYNC_LIB_TASK_DIR_NOT_WRITABLE', 'Scheduled Tasks module directory is not writable: %s'), $dir));
    }

    $tmp = $file . '.tmp.' . getmypid() . '.' . mt_rand(1000, 9999);
    $old_umask = umask(0022);
    $written = @file_put_contents($tmp, $contents, LOCK_EX);
    umask($old_umask);
    if ($written === false) {
        mits_cdb_sync_safe_unlink($tmp);
        return array(false, mits_cdb_sync_text('TEXT_MITS_CDB_SYNC_LIB_TASK_WRITE_FAILED', 'Scheduled Tasks profile module could not be written.'));
    }
    @chmod($tmp, 0644);
    if (!@rename($tmp, $file)) {
        mits_cdb_sync_safe_unlink($tmp);
        return array(false, mits_cdb_sync_text('TEXT_MITS_CDB_SYNC_LIB_TASK_ACTIVATE_FAILED', 'Scheduled Tasks profile module could not be activated.'));
    }
    @chmod($file, 0644);
    return array(true, '');
}

function mits_cdb_sync_ensure_profile_task($profile_id, $regularity = 1, $unit = 'd', $time_hm = '04:00', $status = null)
{
    $profile_id = (int)$profile_id;
    if ($profile_id < 1) {
        return array('success' => false, 'message' => mits_cdb_sync_text('TEXT_MITS_CDB_SYNC_LIB_TASK_INVALID_PROFILE', 'Invalid profile ID.'), 'task' => false);
    }
    if (!defined('TABLE_SCHEDULED_TASKS') || !mits_cdb_sync_table_exists(TABLE_SCHEDULED_TASKS)) {
        return array('success' => false, 'message' => mits_cdb_sync_text('TEXT_MITS_CDB_SYNC_LIB_TASK_TABLE_MISSING', 'Scheduled Tasks table is not available.'), 'task' => false);
    }

    list($module_ok, $module_error) = mits_cdb_sync_write_profile_task_module($profile_id);
    if (!$module_ok) {
        return array('success' => false, 'message' => $module_error, 'task' => false);
    }

    $regularity = max(1, min(999, (int)$regularity));
    $unit = in_array((string)$unit, array('m', 'h', 'd', 'w'), true) ? (string)$unit : 'd';
    $offset = mits_cdb_sync_schedule_offset($time_hm);
    $task_name = mits_cdb_sync_profile_task_name($profile_id);
    $task = mits_cdb_sync_profile_task_row($profile_id);

    if ($task !== false) {
        $changed = ((int)$task['time_regularity'] !== $regularity)
            || ((string)$task['time_unit'] !== $unit)
            || ((int)$task['time_offset'] !== $offset);
        $sets = array(
            "time_regularity='" . $regularity . "'",
            "time_unit='" . mits_cdb_sync_db_escape($unit) . "'",
            "time_offset='" . (int)$offset . "'",
            "edit='1'",
        );
        if ($changed || (int)$task['time_next'] <= 0) {
            $sets[] = "time_next='" . (int)mits_cdb_sync_next_scheduled_task_time($regularity, $unit, $offset) . "'";
        }
        if ($status !== null) {
            $sets[] = "status='" . ((int)$status ? 1 : 0) . "'";
        }
        xtc_db_query("UPDATE " . TABLE_SCHEDULED_TASKS . " SET " . implode(',', $sets) . " WHERE tasks_id='" . (int)$task['tasks_id'] . "'");
    } else {
        $next = mits_cdb_sync_next_scheduled_task_time($regularity, $unit, $offset);
        $task_status = ($status === null) ? 1 : ((int)$status ? 1 : 0);
        xtc_db_query(
            "INSERT INTO " . TABLE_SCHEDULED_TASKS
            . " (tasks, time_next, time_regularity, time_unit, time_offset, status, edit) VALUES ("
            . "'" . mits_cdb_sync_db_escape($task_name) . "','" . (int)$next . "','" . $regularity . "','" . mits_cdb_sync_db_escape($unit) . "','" . (int)$offset . "','" . $task_status . "','1')"
        );
    }

    $task = mits_cdb_sync_profile_task_row($profile_id);
    if ($task === false) {
        return array('success' => false, 'message' => mits_cdb_sync_text('TEXT_MITS_CDB_SYNC_LIB_TASK_CREATE_FAILED', 'Scheduled Task could not be created.'), 'task' => false);
    }
    mits_cdb_sync_refresh_cronjob_next_event();
    return array('success' => true, 'message' => '', 'task' => $task);
}

function mits_cdb_sync_remove_profile_task($profile_id)
{
    $task = mits_cdb_sync_profile_task_row($profile_id);
    if ($task !== false) {
        if (defined('TABLE_SCHEDULED_TASKS_LOG') && mits_cdb_sync_table_exists(TABLE_SCHEDULED_TASKS_LOG)) {
            xtc_db_query("DELETE FROM " . TABLE_SCHEDULED_TASKS_LOG . " WHERE tasks_id='" . (int)$task['tasks_id'] . "'");
        }
        xtc_db_query("DELETE FROM " . TABLE_SCHEDULED_TASKS . " WHERE tasks_id='" . (int)$task['tasks_id'] . "'");
    }
    $file = mits_cdb_sync_profile_task_file($profile_id);
    if (is_file($file)) {
        mits_cdb_sync_safe_unlink($file);
    }
    mits_cdb_sync_refresh_cronjob_next_event();
    return !is_file($file);
}

function mits_cdb_sync_sync_profile_schedule_from_task(&$profile)
{
    if (!is_array($profile) || empty($profile['profile_id'])) {
        return false;
    }
    $task = mits_cdb_sync_profile_task_row((int)$profile['profile_id']);
    if ($task === false) {
        return false;
    }
    $task['_module_exists'] = is_file(mits_cdb_sync_profile_task_file((int)$profile['profile_id']));
    $profile['schedule_enabled'] = 1;
    $profile['schedule_regularity'] = (int)$task['time_regularity'];
    $profile['schedule_unit'] = (string)$task['time_unit'];
    $profile['schedule_time'] = mits_cdb_sync_schedule_time_from_offset($task['time_offset']);
    $profile['next_run'] = (int)$task['time_next'];
    $profile['_scheduled_task'] = $task;
    return true;
}

function mits_cdb_sync_write_log_enabled()
{
    if (!defined('MODULE_MITS_CRON_DATABASE_BACKUPS_WRITE_LOG')) {
        return true;
    }
    return strtolower((string)MODULE_MITS_CRON_DATABASE_BACKUPS_WRITE_LOG) === 'true';
}

function mits_cdb_sync_log($message, $context = array())
{
    if (!mits_cdb_sync_write_log_enabled()) {
        return;
    }

    $parts = array((string)$message);
    foreach ((array)$context as $key => $value) {
        if (is_bool($value)) {
            $value = $value ? 'yes' : 'no';
        } elseif (is_array($value) || is_object($value)) {
            $value = json_encode($value);
        }
        $value = str_replace(array("\r", "\n"), ' ', (string)$value);
        $parts[] = $key . '=' . $value;
    }

    $line = '[' . date('Y-m-d H:i:s') . '] [db-sync] ' . implode(' | ', $parts) . PHP_EOL;
    $written = false;
    if (defined('DIR_FS_LOG') && is_dir(DIR_FS_LOG) && is_writable(DIR_FS_LOG)) {
        $written = (@error_log($line, 3, DIR_FS_LOG . 'mits_cron_database_backups_' . date('Y-m') . '.log') !== false);
    }
    if (!$written) {
        @error_log(rtrim($line));
    }
}

function mits_cdb_sync_exec_enabled()
{
    return function_exists('exec')
        && !in_array('exec', array_map('trim', explode(',', (string)ini_get('disable_functions'))), true)
        && strtolower((string)ini_get('safe_mode')) !== '1';
}

function mits_cdb_sync_binary($constant, $fallback)
{
    $value = defined($constant) ? trim((string)constant($constant)) : '';
    if ($value === '') {
        $value = $fallback;
    }
    return escapeshellarg($value);
}

function mits_cdb_sync_secret_file()
{
    return rtrim(DIR_FS_DOCUMENT_ROOT, '/\\') . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'local' . DIRECTORY_SEPARATOR . 'mits_cdb_sync_key.php';
}

function mits_cdb_sync_create_secret()
{
    $file = mits_cdb_sync_secret_file();
    $dir = dirname($file);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        return false;
    }
    if (!is_dir($dir) || !is_writable($dir)) {
        return false;
    }

    try {
        $secret = random_bytes(32);
    } catch (Exception $e) {
        $secret = function_exists('openssl_random_pseudo_bytes') ? openssl_random_pseudo_bytes(32) : false;
    }
    if ($secret === false || strlen($secret) !== 32) {
        return false;
    }

    $contents = "<?php\nif (!defined('DIR_FS_DOCUMENT_ROOT')) { http_response_code(404); exit; }\nreturn '" . base64_encode($secret) . "';\n";
    $old_umask = umask(0077);
    $written = @file_put_contents($file, $contents, LOCK_EX);
    umask($old_umask);
    if ($written === false) {
        mits_cdb_sync_safe_unlink($file);
        return false;
    }
    @chmod($file, 0600);
    $perms = @fileperms($file);
    if ($perms === false || (($perms & 0077) !== 0)) {
        mits_cdb_sync_safe_unlink($file);
        return false;
    }
    return $secret;
}

function mits_cdb_sync_crypto_key()
{
    static $secret = null;
    static $loaded = false;
    if ($loaded) {
        return $secret;
    }
    $loaded = true;

    $file = mits_cdb_sync_secret_file();
    if (!is_file($file)) {
        $secret = mits_cdb_sync_create_secret();
        return $secret;
    }
    if (!is_readable($file)) {
        return false;
    }
    $encoded = include $file;
    $decoded = is_string($encoded) ? base64_decode($encoded, true) : false;
    if ($decoded === false || strlen($decoded) !== 32) {
        return false;
    }
    $secret = $decoded;
    return $secret;
}

function mits_cdb_sync_crypto_available()
{
    return function_exists('openssl_encrypt')
        && function_exists('openssl_decrypt')
        && in_array('aes-256-gcm', openssl_get_cipher_methods(), true)
        && mits_cdb_sync_crypto_key() !== false;
}

function mits_cdb_sync_encrypt_password($plain)
{
    if ($plain === '') {
        return '';
    }
    if (!mits_cdb_sync_crypto_available()) {
        return false;
    }

    try {
        $iv = random_bytes(12);
    } catch (Exception $e) {
        $iv = openssl_random_pseudo_bytes(12);
    }
    if ($iv === false || strlen($iv) !== 12) {
        return false;
    }

    $tag = '';
    $cipher = openssl_encrypt((string)$plain, 'aes-256-gcm', mits_cdb_sync_crypto_key(), OPENSSL_RAW_DATA, $iv, $tag, 'MITS_CDB_SYNC');
    if ($cipher === false || $tag === '') {
        return false;
    }

    return 'v1:' . base64_encode($iv) . ':' . base64_encode($tag) . ':' . base64_encode($cipher);
}

function mits_cdb_sync_decrypt_password($encoded)
{
    if ($encoded === '') {
        return '';
    }
    if (!mits_cdb_sync_crypto_available()) {
        return false;
    }

    $parts = explode(':', (string)$encoded, 4);
    if (count($parts) !== 4 || $parts[0] !== 'v1') {
        return false;
    }
    $iv = base64_decode($parts[1], true);
    $tag = base64_decode($parts[2], true);
    $cipher = base64_decode($parts[3], true);
    if ($iv === false || $tag === false || $cipher === false) {
        return false;
    }

    return openssl_decrypt($cipher, 'aes-256-gcm', mits_cdb_sync_crypto_key(), OPENSSL_RAW_DATA, $iv, $tag, 'MITS_CDB_SYNC');
}

function mits_cdb_sync_source_connection()
{
    $host = defined('MODULE_MITS_CRON_DATABASE_BACKUPS_DB_HOST') && trim((string)MODULE_MITS_CRON_DATABASE_BACKUPS_DB_HOST) !== ''
        ? trim((string)MODULE_MITS_CRON_DATABASE_BACKUPS_DB_HOST)
        : (defined('DB_SERVER') ? (string)DB_SERVER : 'localhost');
    $port = defined('MODULE_MITS_CRON_DATABASE_BACKUPS_DB_PORT') ? trim((string)MODULE_MITS_CRON_DATABASE_BACKUPS_DB_PORT) : '';
    $socket = defined('MODULE_MITS_CRON_DATABASE_BACKUPS_DB_SOCKET') ? trim((string)MODULE_MITS_CRON_DATABASE_BACKUPS_DB_SOCKET) : '';
    $force_tcp = defined('MODULE_MITS_CRON_DATABASE_BACKUPS_DB_FORCE_TCP') && strtolower((string)MODULE_MITS_CRON_DATABASE_BACKUPS_DB_FORCE_TCP) === 'true';

    if ($socket === '' && $port === '' && preg_match('/^(.+):(\d+)$/', $host, $match)) {
        $host = $match[1];
        $port = $match[2];
    }

    return array(
        'host' => $host,
        'port' => $port,
        'socket' => $socket,
        'username' => defined('DB_SERVER_USERNAME') ? (string)DB_SERVER_USERNAME : '',
        'password' => defined('DB_SERVER_PASSWORD') ? (string)DB_SERVER_PASSWORD : '',
        'database' => defined('DB_DATABASE') ? (string)DB_DATABASE : '',
        'force_tcp' => $force_tcp,
        'ssl_mode' => 'preferred',
        'ssl_ca' => '',
    );
}

function mits_cdb_sync_profile_to_target($profile)
{
    $password = mits_cdb_sync_decrypt_password(isset($profile['target_password']) ? (string)$profile['target_password'] : '');
    if ($password === false) {
        return false;
    }

    return array(
        'host' => isset($profile['target_host']) ? (string)$profile['target_host'] : '',
        'port' => isset($profile['target_port']) ? (string)$profile['target_port'] : '',
        'socket' => isset($profile['target_socket']) ? (string)$profile['target_socket'] : '',
        'username' => isset($profile['target_username']) ? (string)$profile['target_username'] : '',
        'password' => $password,
        'database' => isset($profile['target_database']) ? (string)$profile['target_database'] : '',
        'force_tcp' => !empty($profile['target_force_tcp']),
        'ssl_mode' => isset($profile['target_ssl_mode']) ? (string)$profile['target_ssl_mode'] : 'preferred',
        'ssl_ca' => isset($profile['target_ssl_ca']) ? (string)$profile['target_ssl_ca'] : '',
    );
}

function mits_cdb_sync_temp_dir()
{
    $candidates = array();
    $system_tmp = function_exists('sys_get_temp_dir') ? rtrim((string)sys_get_temp_dir(), '/\\') : '';
    if ($system_tmp !== '') {
        $suffix = substr(hash('sha256', DIR_FS_DOCUMENT_ROOT), 0, 16);
        $candidates[] = $system_tmp . DIRECTORY_SEPARATOR . 'mits_cdb_sync_' . $suffix . DIRECTORY_SEPARATOR;
    }
    $candidates[] = rtrim(DIR_FS_DOCUMENT_ROOT . 'export/mits_cron_database_backups/.sync_tmp', '/\\') . DIRECTORY_SEPARATOR;

    foreach (array_unique($candidates) as $dir) {
        if (!is_dir($dir)) {
            $old_umask = umask(0077);
            @mkdir($dir, 0700, true);
            umask($old_umask);
        }
        if (!is_dir($dir) || !is_writable($dir)) {
            continue;
        }
        @chmod($dir, 0700);
        $dir_perms = @fileperms($dir);
        if ($dir_perms === false || (($dir_perms & 0077) !== 0)) {
            continue;
        }

        if (strpos($dir, rtrim(DIR_FS_DOCUMENT_ROOT, '/\\') . DIRECTORY_SEPARATOR) === 0) {
            $htaccess = $dir . '.htaccess';
            if (!is_file($htaccess)) {
                $old_umask = umask(0077);
                @file_put_contents($htaccess, "Options -Indexes\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder deny,allow\nDeny from all\n</IfModule>\n", LOCK_EX);
                umask($old_umask);
                @chmod($htaccess, 0600);
            }
            $index = $dir . 'index.html';
            if (!is_file($index)) {
                $old_umask = umask(0077);
                @file_put_contents($index, '', LOCK_EX);
                umask($old_umask);
                @chmod($index, 0600);
            }
        }

        foreach ((array)glob($dir . 'mits_sync_*') as $old) {
            if (is_file($old) && @filemtime($old) !== false && @filemtime($old) < time() - 86400) {
                mits_cdb_sync_safe_unlink($old);
            }
        }
        return $dir;
    }

    return false;
}

function mits_cdb_sync_temp_file($suffix)
{
    $dir = mits_cdb_sync_temp_dir();
    if ($dir === false) {
        return false;
    }

    try {
        $random = bin2hex(random_bytes(12));
    } catch (Exception $e) {
        $random = md5(uniqid(mt_rand(), true));
    }
    return $dir . 'mits_sync_' . $random . $suffix;
}

function mits_cdb_sync_option_quote($value)
{
    $value = str_replace(array('\\', '"', "\r", "\n", "\t"), array('\\\\', '\\"', '\\r', '\\n', '\\t'), (string)$value);
    return '"' . $value . '"';
}

function mits_cdb_sync_mysql_flavor()
{
    static $flavor = null;
    if ($flavor !== null) {
        return $flavor;
    }

    $binary = mits_cdb_sync_binary('MODULE_MITS_CRON_DATABASE_BACKUPS_MYSQL_PATH', 'mysql');
    $out = array();
    $ret = 1;
    @exec($binary . ' --version 2>&1', $out, $ret);
    $version = implode(' ', $out);
    $flavor = (stripos($version, 'mariadb') !== false) ? 'mariadb' : 'mysql';
    return $flavor;
}

function mits_cdb_sync_write_option_file($connection, $target = false)
{
    if (isset($connection['port']) && trim((string)$connection['port']) !== '') {
        $port = trim((string)$connection['port']);
        if (!ctype_digit($port) || (int)$port < 1 || (int)$port > 65535) {
            return array(false, mits_cdb_sync_text('TEXT_MITS_CDB_SYNC_LIB_TARGET_PORT', 'The database port must be a number between 1 and 65535.'));
        }
    }

    $file = mits_cdb_sync_temp_file('.cnf');
    if ($file === false) {
        return array(false, mits_cdb_sync_text('TEXT_MITS_CDB_SYNC_LIB_TEMP_DIR', 'Temporary secure directory is not writable.'));
    }

    $lines = array('[client]');
    if (!empty($connection['socket'])) {
        $lines[] = 'socket=' . mits_cdb_sync_option_quote($connection['socket']);
    } else {
        if (!empty($connection['host'])) {
            $lines[] = 'host=' . mits_cdb_sync_option_quote($connection['host']);
        }
        if (!empty($connection['port'])) {
            $lines[] = 'port=' . (int)$connection['port'];
        }
        if (!empty($connection['force_tcp'])) {
            $lines[] = 'protocol=TCP';
        }
    }
    $lines[] = 'user=' . mits_cdb_sync_option_quote(isset($connection['username']) ? $connection['username'] : '');
    $lines[] = 'password=' . mits_cdb_sync_option_quote(isset($connection['password']) ? $connection['password'] : '');

    if ($target) {
        $ssl_mode = isset($connection['ssl_mode']) ? strtolower((string)$connection['ssl_mode']) : 'preferred';
        $ssl_ca = isset($connection['ssl_ca']) ? trim((string)$connection['ssl_ca']) : '';
        $flavor = mits_cdb_sync_mysql_flavor();
        if ($ssl_mode === 'required') {
            $lines[] = ($flavor === 'mariadb') ? 'ssl=1' : 'ssl-mode=REQUIRED';
        } elseif ($ssl_mode === 'verify') {
            if ($ssl_ca === '' || !is_file($ssl_ca) || !is_readable($ssl_ca)) {
                return array(false, mits_cdb_sync_text('TEXT_MITS_CDB_SYNC_LIB_TLS_CA', 'TLS verification is enabled, but the CA file is not readable.'));
            }
            if ($flavor === 'mariadb') {
                $lines[] = 'ssl=1';
                $lines[] = 'ssl-ca=' . mits_cdb_sync_option_quote($ssl_ca);
                $lines[] = 'ssl-verify-server-cert=1';
            } else {
                $lines[] = 'ssl-mode=VERIFY_IDENTITY';
                $lines[] = 'ssl-ca=' . mits_cdb_sync_option_quote($ssl_ca);
            }
        }
    }

    $contents = implode(PHP_EOL, $lines) . PHP_EOL;
    $old_umask = umask(0077);
    $written = @file_put_contents($file, $contents, LOCK_EX);
    umask($old_umask);
    if ($written === false) {
        mits_cdb_sync_safe_unlink($file);
        return array(false, mits_cdb_sync_text('TEXT_MITS_CDB_SYNC_LIB_CNF_WRITE', 'Temporary credential file could not be written.'));
    }

    @chmod($file, 0600);
    $perms = @fileperms($file);
    if ($perms === false || (($perms & 0077) !== 0)) {
        mits_cdb_sync_safe_unlink($file);
        return array(false, mits_cdb_sync_text('TEXT_MITS_CDB_SYNC_LIB_CNF_PERMS', 'Temporary credential file could not be secured with file mode 0600.'));
    }

    return array($file, '');
}

function mits_cdb_sync_source_tables()
{
    $tables = array();
    $query = xtc_db_query('SHOW FULL TABLES');
    while ($row = xtc_db_fetch_array($query)) {
        $values = array_values($row);
        if (!isset($values[0])) {
            continue;
        }
        if (isset($values[1]) && strtoupper((string)$values[1]) !== 'BASE TABLE') {
            continue;
        }
        if ((string)$values[0] === mits_cdb_sync_profile_table()) {
            continue;
        }
        $tables[] = (string)$values[0];
    }
    sort($tables, SORT_NATURAL | SORT_FLAG_CASE);
    return $tables;
}

function mits_cdb_sync_validate_tables($selected)
{
    $available = mits_cdb_sync_source_tables();
    $lookup = array_fill_keys($available, true);
    $valid = array();
    $invalid = array();
    foreach ((array)$selected as $table) {
        $table = trim((string)$table);
        if ($table === '') {
            continue;
        }
        if (isset($lookup[$table])) {
            $valid[$table] = $table;
        } else {
            $invalid[] = $table;
        }
    }
    return array(array_values($valid), $invalid);
}

function mits_cdb_sync_parse_tables($value)
{
    if (is_array($value)) {
        return array_values(array_filter(array_map('trim', $value), 'strlen'));
    }
    $decoded = json_decode((string)$value, true);
    if (is_array($decoded)) {
        return array_values(array_filter(array_map('trim', $decoded), 'strlen'));
    }
    return array_values(array_filter(array_map('trim', preg_split('/[\r\n,;]+/', (string)$value)), 'strlen'));
}

function mits_cdb_sync_next_run($regularity, $unit, $time_hm, $from = null)
{
    $regularity = max(1, (int)$regularity);
    $unit = in_array($unit, array('m', 'h', 'd', 'w'), true) ? $unit : 'd';
    $from = ($from === null) ? time() : (int)$from;
    if (!preg_match('/^(\d{1,2}):(\d{2})$/', (string)$time_hm, $match)) {
        $hour = 0;
        $minute = 0;
    } else {
        $hour = min(23, max(0, (int)$match[1]));
        $minute = min(59, max(0, (int)$match[2]));
    }

    $anchor = mktime($hour, $minute, 0, (int)date('m', $from), (int)date('d', $from), (int)date('Y', $from));

    if ($unit === 'm') {
        $step = $regularity * 60;
        while ($anchor <= $from) {
            $anchor += $step;
        }
        return $anchor;
    }
    if ($unit === 'h') {
        $step = $regularity * 3600;
        while ($anchor <= $from) {
            $anchor += $step;
        }
        return $anchor;
    }

    if ($unit === 'w') {
        while ($anchor <= $from) {
            $anchor = strtotime('+' . $regularity . ' week', $anchor);
        }
        return $anchor;
    }

    while ($anchor <= $from) {
        $anchor = strtotime('+' . $regularity . ' day', $anchor);
    }
    return $anchor;
}

function mits_cdb_sync_get_profile($profile_id)
{
    $table = mits_cdb_sync_profile_table();
    if (!mits_cdb_sync_table_exists($table)) {
        return false;
    }
    $query = xtc_db_query("SELECT * FROM " . $table . " WHERE profile_id = '" . (int)$profile_id . "' LIMIT 1");
    if (xtc_db_num_rows($query) < 1) {
        return false;
    }
    return xtc_db_fetch_array($query);
}

function mits_cdb_sync_profiles()
{
    $profiles = array();
    $table = mits_cdb_sync_profile_table();
    if (!mits_cdb_sync_table_exists($table)) {
        return $profiles;
    }
    $query = xtc_db_query('SELECT * FROM ' . $table . ' ORDER BY name ASC, profile_id ASC');
    while ($row = xtc_db_fetch_array($query)) {
        $profiles[] = $row;
    }
    return $profiles;
}

function mits_cdb_sync_acquire_lock($profile_id)
{
    $name = 'mits_cdb_sync_' . (int)$profile_id;
    $query = xtc_db_query("SELECT GET_LOCK('" . mits_cdb_sync_db_escape($name) . "', 0) AS lock_ok");
    $row = xtc_db_fetch_array($query);
    return isset($row['lock_ok']) && (int)$row['lock_ok'] === 1;
}

function mits_cdb_sync_release_lock($profile_id)
{
    $name = 'mits_cdb_sync_' . (int)$profile_id;
    @xtc_db_query("SELECT RELEASE_LOCK('" . mits_cdb_sync_db_escape($name) . "')");
}

function mits_cdb_sync_source_identity()
{
    $query = xtc_db_query("SELECT @@hostname AS host, @@port AS port, DATABASE() AS db");
    $row = xtc_db_fetch_array($query);
    return array(
        'host' => isset($row['host']) ? (string)$row['host'] : '',
        'port' => isset($row['port']) ? (string)$row['port'] : '',
        'db' => isset($row['db']) ? (string)$row['db'] : (defined('DB_DATABASE') ? (string)DB_DATABASE : ''),
    );
}

function mits_cdb_sync_target_identity($target_option_file, $target_database, $require_tls = false)
{
    $mysql = mits_cdb_sync_binary('MODULE_MITS_CRON_DATABASE_BACKUPS_MYSQL_PATH', 'mysql');
    $sql = "SELECT CONCAT(@@hostname, CHAR(31), @@port, CHAR(31), DATABASE()); SHOW SESSION STATUS LIKE 'Ssl_cipher';";
    $command = $mysql
        . ' --defaults-extra-file=' . escapeshellarg($target_option_file)
        . ' --batch --skip-column-names --raw'
        . ' ' . escapeshellarg($target_database)
        . ' -e ' . escapeshellarg($sql)
        . ' 2>&1';
    $output = array();
    $return = 1;
    @exec($command, $output, $return);
    if ($return !== 0 || empty($output)) {
        return array(false, trim(implode(' / ', $output)));
    }
    $parts = explode(chr(31), trim((string)$output[0]));
    $tls_cipher = '';
    foreach (array_slice($output, 1) as $line) {
        $status = preg_split('/\t+/', trim((string)$line), 2);
        if (isset($status[0]) && strcasecmp($status[0], 'Ssl_cipher') === 0) {
            $tls_cipher = isset($status[1]) ? trim((string)$status[1]) : '';
            break;
        }
    }
    if ($require_tls && $tls_cipher === '') {
        return array(false, mits_cdb_sync_text('TEXT_MITS_CDB_SYNC_LIB_TLS_REQUIRED', 'TLS is required for the target connection, but no encrypted connection was established.'));
    }
    return array(array(
        'host' => isset($parts[0]) ? $parts[0] : '',
        'port' => isset($parts[1]) ? $parts[1] : '',
        'db' => isset($parts[2]) ? $parts[2] : $target_database,
        'tls_cipher' => $tls_cipher,
    ), '');
}

function mits_cdb_sync_is_same_database($source_identity, $target_identity)
{
    return strcasecmp(trim((string)$source_identity['host']), trim((string)$target_identity['host'])) === 0
        && (string)$source_identity['port'] === (string)$target_identity['port']
        && strcasecmp(trim((string)$source_identity['db']), trim((string)$target_identity['db'])) === 0;
}

function mits_cdb_sync_update_profile_result($profile_id, $success, $message)
{
    $table = mits_cdb_sync_profile_table();
    if (!mits_cdb_sync_table_exists($table)) {
        return;
    }
    xtc_db_query(
        "UPDATE " . $table . " SET last_run='" . time() . "', last_status='" . ($success ? 'success' : 'error') . "', last_message='" . mits_cdb_sync_db_escape($message) . "', date_updated=NOW() WHERE profile_id='" . (int)$profile_id . "'"
    );
}

function mits_cdb_sync_update_next_run($profile)
{
    $table = mits_cdb_sync_profile_table();
    if (empty($profile['schedule_enabled'])) {
        $next = 0;
    } else {
        $next = mits_cdb_sync_next_run($profile['schedule_regularity'], $profile['schedule_unit'], $profile['schedule_time'], time());
    }
    xtc_db_query("UPDATE " . $table . " SET next_run='" . (int)$next . "', date_updated=NOW() WHERE profile_id='" . (int)$profile['profile_id'] . "'");
    return $next;
}

function mits_cdb_sync_execute($target, $mode, $tables = array(), $context = array())
{
    $total_started = microtime(true);
    $timings = array();
    @set_time_limit(0);
    if (function_exists('ignore_user_abort')) {
        @ignore_user_abort(true);
    }

    $result = array('success' => false, 'message' => '', 'details' => array());
    if (!mits_cdb_sync_exec_enabled()) {
        $result['message'] = mits_cdb_sync_text('TEXT_MITS_CDB_SYNC_LIB_EXEC', 'exec() is disabled or unavailable.');
        return $result;
    }
    if (empty($target['database']) || empty($target['username']) || (empty($target['host']) && empty($target['socket']))) {
        $result['message'] = mits_cdb_sync_text('TEXT_MITS_CDB_SYNC_LIB_TARGET_REQUIRED', 'Target host/socket, database and user are required.');
        return $result;
    }
    if (!in_array($mode, array('full', 'tables'), true)) {
        $mode = 'full';
    }

    $valid_tables = array();
    if ($mode === 'tables') {
        list($valid_tables, $invalid) = mits_cdb_sync_validate_tables($tables);
        if (!empty($invalid)) {
            $result['message'] = sprintf(mits_cdb_sync_text('TEXT_MITS_CDB_SYNC_LIB_INVALID_TABLES', 'Source tables not found: %s'), implode(', ', $invalid));
            return $result;
        }
        if (empty($valid_tables)) {
            $result['message'] = mits_cdb_sync_text('TEXT_MITS_CDB_SYNC_LIB_NO_TABLES', 'No tables were selected for transfer.');
            return $result;
        }
    }

    $source = mits_cdb_sync_source_connection();
    list($source_cnf, $source_error) = mits_cdb_sync_write_option_file($source, false);
    if ($source_cnf === false) {
        $result['message'] = $source_error;
        return $result;
    }
    list($target_cnf, $target_error) = mits_cdb_sync_write_option_file($target, true);
    if ($target_cnf === false) {
        mits_cdb_sync_safe_unlink($source_cnf);
        $result['message'] = $target_error;
        return $result;
    }

    $dump_file = mits_cdb_sync_temp_file('.sql');
    if ($dump_file === false) {
        mits_cdb_sync_safe_unlink($source_cnf);
        mits_cdb_sync_safe_unlink($target_cnf);
        $result['message'] = mits_cdb_sync_text('TEXT_MITS_CDB_SYNC_LIB_DUMP_TEMP', 'Temporary SQL file could not be prepared.');
        return $result;
    }

    $cleanup_done = false;
    $cleanup = function () use ($source_cnf, $target_cnf, $dump_file, &$cleanup_done) {
        if ($cleanup_done) {
            return;
        }
        $cleanup_done = true;
        mits_cdb_sync_safe_unlink($source_cnf);
        mits_cdb_sync_safe_unlink($target_cnf);
        mits_cdb_sync_safe_unlink($dump_file);
    };
    register_shutdown_function($cleanup);

    $phase_started = microtime(true);
    list($target_identity, $identity_error) = mits_cdb_sync_target_identity($target_cnf, $target['database'], in_array(isset($target['ssl_mode']) ? $target['ssl_mode'] : 'preferred', array('required', 'verify'), true));
    $timings['target_connection_s'] = mits_cdb_sync_elapsed($phase_started);
    if ($target_identity === false) {
        mits_cdb_sync_log('Synchronisationsphase fehlgeschlagen.', array(
            'profile_id' => isset($context['profile_id']) ? (int)$context['profile_id'] : 0,
            'phase' => 'target_connection',
            'duration_s' => $timings['target_connection_s'],
            'message' => $identity_error,
        ));
        $cleanup();
        $result['message'] = sprintf(mits_cdb_sync_text('TEXT_MITS_CDB_SYNC_LIB_TARGET_CONNECTION', 'Connection to target database failed: %s'), $identity_error);
        $result['details']['timings'] = $timings;
        return $result;
    }

    $phase_started = microtime(true);
    $source_identity = mits_cdb_sync_source_identity();
    $same_database = mits_cdb_sync_is_same_database($source_identity, $target_identity);
    $timings['safety_check_s'] = mits_cdb_sync_elapsed($phase_started);
    if ($same_database) {
        mits_cdb_sync_log('Synchronisationsphase fehlgeschlagen.', array(
            'profile_id' => isset($context['profile_id']) ? (int)$context['profile_id'] : 0,
            'phase' => 'safety_check',
            'duration_s' => $timings['safety_check_s'],
            'message' => 'source_equals_target',
        ));
        $cleanup();
        $result['message'] = mits_cdb_sync_text('TEXT_MITS_CDB_SYNC_LIB_SAME_DB', 'The target database is identical to the current shop database. Transfer was aborted for safety.');
        $result['details']['timings'] = $timings;
        return $result;
    }

    $charset = defined('DB_SERVER_CHARSET') ? preg_replace('/[^A-Za-z0-9_]/', '', (string)DB_SERVER_CHARSET) : '';
    $mysqldump = mits_cdb_sync_binary('MODULE_MITS_CRON_DATABASE_BACKUPS_MYSQLDUMP_PATH', 'mysqldump');
    $dump_command = $mysqldump
        . ' --defaults-extra-file=' . escapeshellarg($source_cnf)
        . ' --opt --single-transaction --skip-lock-tables --quick --hex-blob';
    if ($charset !== '') {
        $dump_command .= ' --default-character-set=' . escapeshellarg($charset);
    }
    if ($mode === 'full') {
        $dump_command .= ' --ignore-table=' . escapeshellarg($source['database'] . '.' . mits_cdb_sync_profile_table());
    }
    $dump_command .= ' --result-file=' . escapeshellarg($dump_file)
        . ' ' . escapeshellarg($source['database']);
    if ($mode === 'tables') {
        foreach ($valid_tables as $table) {
            $dump_command .= ' ' . escapeshellarg($table);
        }
    }
    $dump_command .= ' 2>&1';

    $phase_started = microtime(true);
    $old_umask = umask(0077);
    $dump_output = array();
    $dump_return = 1;
    @exec($dump_command, $dump_output, $dump_return);
    umask($old_umask);
    @chmod($dump_file, 0600);
    $timings['dump_s'] = mits_cdb_sync_elapsed($phase_started);

    if ($dump_return !== 0 || !is_file($dump_file) || filesize($dump_file) === 0) {
        mits_cdb_sync_log('Synchronisationsphase fehlgeschlagen.', array(
            'profile_id' => isset($context['profile_id']) ? (int)$context['profile_id'] : 0,
            'phase' => 'mysqldump',
            'duration_s' => $timings['dump_s'],
            'exit_code' => (int)$dump_return,
        ));
        $cleanup();
        $result['message'] = sprintf(mits_cdb_sync_text('TEXT_MITS_CDB_SYNC_LIB_DUMP_FAILED', 'mysqldump failed: %s'), !empty($dump_output) ? trim(implode(' / ', $dump_output)) : '-');
        $result['details']['timings'] = $timings;
        return $result;
    }
    $dump_perms = @fileperms($dump_file);
    if ($dump_perms === false || (($dump_perms & 0077) !== 0)) {
        $cleanup();
        $result['message'] = mits_cdb_sync_text('TEXT_MITS_CDB_SYNC_LIB_DUMP_PERMS', 'Temporary SQL dump could not be protected with file mode 0600.');
        return $result;
    }

    $mysql = mits_cdb_sync_binary('MODULE_MITS_CRON_DATABASE_BACKUPS_MYSQL_PATH', 'mysql');
    $import_command = $mysql
        . ' --defaults-extra-file=' . escapeshellarg($target_cnf)
        . ' --binary-mode=1'
        . ' ' . escapeshellarg($target['database'])
        . ' < ' . escapeshellarg($dump_file)
        . ' 2>&1';
    $phase_started = microtime(true);
    $import_output = array();
    $import_return = 1;
    @exec($import_command, $import_output, $import_return);
    $timings['import_s'] = mits_cdb_sync_elapsed($phase_started);
    $dump_size = @filesize($dump_file);
    $phase_started = microtime(true);
    $cleanup();
    $timings['cleanup_s'] = mits_cdb_sync_elapsed($phase_started);

    if ($import_return !== 0) {
        mits_cdb_sync_log('Synchronisationsphase fehlgeschlagen.', array(
            'profile_id' => isset($context['profile_id']) ? (int)$context['profile_id'] : 0,
            'phase' => 'import',
            'duration_s' => $timings['import_s'],
            'cleanup_s' => $timings['cleanup_s'],
            'exit_code' => (int)$import_return,
        ));
        $result['message'] = sprintf(mits_cdb_sync_text('TEXT_MITS_CDB_SYNC_LIB_IMPORT_FAILED', 'Import into target database failed: %s'), !empty($import_output) ? trim(implode(' / ', $import_output)) : '-');
        $result['details']['timings'] = $timings;
        return $result;
    }

    $label = ($mode === 'tables') ? sprintf(mits_cdb_sync_text('TEXT_MITS_CDB_SYNC_LIB_TABLE_COUNT', '%s table(s)'), count($valid_tables)) : mits_cdb_sync_text('TEXT_MITS_CDB_SYNC_LIB_FULL_DATABASE', 'complete database');
    $result['success'] = true;
    $result['message'] = sprintf(mits_cdb_sync_text('TEXT_MITS_CDB_SYNC_LIB_SUCCESS', 'Transfer successful: %s to %s.'), $label, $target['database']);
    $timings['total_s'] = mits_cdb_sync_elapsed($total_started);
    $result['details'] = array('dump_size' => (int)$dump_size, 'tables' => $valid_tables, 'target_identity' => $target_identity, 'timings' => $timings);

    $log_context = array(
        'profile_id' => isset($context['profile_id']) ? (int)$context['profile_id'] : 0,
        'profile' => isset($context['profile']) ? $context['profile'] : 'manual',
        'mode' => $mode,
        'tables' => ($mode === 'tables' ? count($valid_tables) : 'all'),
        'target_host' => isset($target['host']) ? $target['host'] : '',
        'target_database' => $target['database'],
        'dump_bytes' => (int)$dump_size,
        'target_connection_s' => isset($timings['target_connection_s']) ? $timings['target_connection_s'] : 0,
        'safety_check_s' => isset($timings['safety_check_s']) ? $timings['safety_check_s'] : 0,
        'dump_s' => isset($timings['dump_s']) ? $timings['dump_s'] : 0,
        'import_s' => isset($timings['import_s']) ? $timings['import_s'] : 0,
        'cleanup_s' => isset($timings['cleanup_s']) ? $timings['cleanup_s'] : 0,
        'total_s' => isset($timings['total_s']) ? $timings['total_s'] : 0,
    );
    mits_cdb_sync_log('Datenbank-Synchronisation erfolgreich abgeschlossen.', $log_context);
    return $result;
}

function mits_cdb_sync_run_profile($profile_id, $manual = false)
{
    $profile = mits_cdb_sync_get_profile($profile_id);
    if ($profile === false) {
        return array('success' => false, 'message' => mits_cdb_sync_text('TEXT_MITS_CDB_SYNC_LIB_PROFILE_NOT_FOUND', 'Synchronization profile was not found.'), 'details' => array());
    }
    if (!$manual && empty($profile['enabled'])) {
        return array('success' => false, 'message' => mits_cdb_sync_text('TEXT_MITS_CDB_SYNC_LIB_PROFILE_DISABLED', 'Synchronization profile is disabled.'), 'details' => array());
    }
    if (!mits_cdb_sync_acquire_lock($profile_id)) {
        return array('success' => false, 'message' => mits_cdb_sync_text('TEXT_MITS_CDB_SYNC_LIB_PROFILE_LOCKED', 'Synchronization profile is already running.'), 'details' => array('code' => 'locked'));
    }

    $target = mits_cdb_sync_profile_to_target($profile);
    if ($target === false) {
        mits_cdb_sync_release_lock($profile_id);
        $message = mits_cdb_sync_text('TEXT_MITS_CDB_SYNC_LIB_PASSWORD_DECRYPT', 'Stored target password could not be decrypted. Edit the profile and save the password again.');
        mits_cdb_sync_update_profile_result($profile_id, false, $message);
        return array('success' => false, 'message' => $message, 'details' => array());
    }

    $tables = mits_cdb_sync_parse_tables(isset($profile['tables_json']) ? $profile['tables_json'] : '');
    mits_cdb_sync_log('Datenbank-Synchronisation gestartet.', array(
        'profile_id' => (int)$profile_id,
        'profile' => $profile['name'],
        'trigger' => $manual ? 'manual-profile' : 'scheduled',
        'mode' => $profile['sync_mode'],
        'target_host' => $profile['target_host'],
        'target_database' => $profile['target_database'],
    ));

    $result = mits_cdb_sync_execute($target, $profile['sync_mode'], $tables, array('profile_id' => $profile_id, 'profile' => $profile['name']));
    mits_cdb_sync_update_profile_result($profile_id, $result['success'], $result['message']);
    if (!$result['success']) {
        mits_cdb_sync_log('Datenbank-Synchronisation fehlgeschlagen.', array('profile_id' => (int)$profile_id, 'profile' => $profile['name'], 'message' => $result['message']));
    }
    mits_cdb_sync_release_lock($profile_id);
    return $result;
}

function mits_cdb_sync_run_due_profiles()
{
    $table = mits_cdb_sync_profile_table();
    if (!mits_cdb_sync_table_exists($table)) {
        return array('processed' => 0, 'success' => 0, 'failed' => 0);
    }

    $processed = 0;
    $success = 0;
    $failed = 0;
    $now = time();

    if (defined('TABLE_SCHEDULED_TASKS') && mits_cdb_sync_table_exists(TABLE_SCHEDULED_TASKS)) {
        $prefix = 'mits_cron_database_sync_profile_';
        $all_tasks = xtc_db_query(
            "SELECT tasks_id FROM " . TABLE_SCHEDULED_TASKS
            . " WHERE tasks LIKE '" . mits_cdb_sync_db_escape($prefix) . "%' LIMIT 1"
        );
        if (xtc_db_num_rows($all_tasks) > 0) {
            $tasks_query = xtc_db_query(
                "SELECT * FROM " . TABLE_SCHEDULED_TASKS
                . " WHERE status='1' AND time_next <= '" . (int)$now . "'"
                . " AND tasks LIKE '" . mits_cdb_sync_db_escape($prefix) . "%'"
                . " ORDER BY time_next ASC, tasks_id ASC"
            );
            while ($task = xtc_db_fetch_array($tasks_query)) {
                if (!preg_match('/^mits_cron_database_sync_profile_(\d+)$/', (string)$task['tasks'], $match)) {
                    continue;
                }
                $profile_id = (int)$match[1];
                $profile = mits_cdb_sync_get_profile($profile_id);
                if ($profile === false) {
                    mits_cdb_sync_log('Verwaiste geplante Synchronisationsaufgabe übersprungen.', array('tasks_id' => (int)$task['tasks_id'], 'profile_id' => $profile_id));
                    continue;
                }

                $regularity = max(1, (int)$task['time_regularity']);
                $unit = in_array((string)$task['time_unit'], array('m', 'h', 'd', 'w'), true) ? (string)$task['time_unit'] : 'd';
                $next = mits_cdb_sync_next_scheduled_task_time($regularity, $unit, (int)$task['time_offset']);
                $duration = $regularity * 3600;
                if ($unit === 'm') {
                    $duration = $regularity * 60;
                } elseif ($unit === 'd') {
                    $duration = $regularity * 86400;
                } elseif ($unit === 'w') {
                    $duration = $regularity * 604800;
                }
                if (time() + ($duration / 2) > $next) {
                    $next += $duration;
                }

                xtc_db_query("UPDATE " . TABLE_SCHEDULED_TASKS . " SET time_next='" . (int)$next . "' WHERE tasks_id='" . (int)$task['tasks_id'] . "'");
                xtc_db_query(
                    "UPDATE " . $table . " SET schedule_enabled='1', schedule_regularity='" . $regularity . "', schedule_unit='" . mits_cdb_sync_db_escape($unit) . "',"
                    . " schedule_time='" . mits_cdb_sync_db_escape(mits_cdb_sync_schedule_time_from_offset($task['time_offset'])) . "', next_run='" . (int)$next . "', date_updated=NOW()"
                    . " WHERE profile_id='" . $profile_id . "'"
                );

                $processed++;
                $time_start = microtime(true);
                $result = mits_cdb_sync_run_profile($profile_id, false);
                if (!empty($result['success'])) {
                    $success++;
                    if (defined('TABLE_SCHEDULED_TASKS_LOG') && mits_cdb_sync_table_exists(TABLE_SCHEDULED_TASKS_LOG)) {
                        $sql_data_array = array(
                            'tasks_id' => (int)$task['tasks_id'],
                            'time_run' => time(),
                            'time_taken' => round((microtime(true) - $time_start), 5),
                        );
                        xtc_db_perform(TABLE_SCHEDULED_TASKS_LOG, $sql_data_array);
                    }
                } elseif (isset($result['details']['code']) && $result['details']['code'] === 'locked') {
                    $success++;
                } else {
                    $failed++;
                }
            }
            mits_cdb_sync_refresh_cronjob_next_event();
            return array('processed' => $processed, 'success' => $success, 'failed' => $failed);
        }
    }

    return array('processed' => $processed, 'success' => $success, 'failed' => $failed);
}
