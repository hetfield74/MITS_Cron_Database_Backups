<?php
/**
 * --------------------------------------------------------------
 * File: mits_cron_database_backups.php
 * Created by PhpStorm
 * Date: 04.09.2026
 * Time: 11:09
 *
 * Author: Hetfield
 * Copyright: (c) 2019 - MerZ IT-SerVice
 * Web: https://www.merz-it-service.de
 * Contact: info@merz-it-service.de
 *
 * Released under the GNU General Public License
 * --------------------------------------------------------------
 */

date_default_timezone_set("Europe/Berlin");
chdir(dirname(__FILE__) . '/../../');

include_once('includes/application_top.php');

defined('STORE_NAME') or define('STORE_NAME', isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '');
if (!defined('STORE_OWNER_EMAIL_ADDRESS')) {
    define('STORE_OWNER_EMAIL_ADDRESS', '');
}
$store_owner_email = STORE_OWNER_EMAIL_ADDRESS;

defined('MODULE_MITS_CRON_DATABASE_BACKUPS_STATUS') or define('MODULE_MITS_CRON_DATABASE_BACKUPS_STATUS', 'true');
defined('MODULE_MITS_CRON_DATABASE_BACKUPS_HASH') or define('MODULE_MITS_CRON_DATABASE_BACKUPS_HASH', '3p7R9VAZcbtUCptYH212u4n7jtVBg4Wy');
defined('MODULE_MITS_CRON_DATABASE_BACKUPS_GZIP') or define('MODULE_MITS_CRON_DATABASE_BACKUPS_GZIP', 'true');
defined('MODULE_MITS_CRON_DATABASE_BACKUPS_COMPLETE_INSERT') or define('MODULE_MITS_CRON_DATABASE_BACKUPS_COMPLETE_INSERT', 'true');
defined('MODULE_MITS_CRON_DATABASE_BACKUPS_EXTENDED_INSERT') or define('MODULE_MITS_CRON_DATABASE_BACKUPS_EXTENDED_INSERT', 'true');
defined('MODULE_MITS_CRON_DATABASE_BACKUPS_SENDMAIL') or define('MODULE_MITS_CRON_DATABASE_BACKUPS_SENDMAIL', 'false');
defined('MODULE_MITS_CRON_DATABASE_BACKUPS_MAILADDRESS') or define('MODULE_MITS_CRON_DATABASE_BACKUPS_MAILADDRESS', $store_owner_email);
defined('MODULE_MITS_CRON_DATABASE_BACKUPS_SENDFTP') or define('MODULE_MITS_CRON_DATABASE_BACKUPS_SENDFTP', 'false');
defined('MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_HOST') or define('MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_HOST', '');
defined('MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_USER') or define('MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_USER', '');
defined('MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_PASS') or define('MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_PASS', '');
defined('MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_PORT') or define('MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_PORT', '');
defined('MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_PATH') or define('MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_PATH', '/');
defined('MODULE_MITS_CRON_DATABASE_BACKUPS_DELETEOLDBACKUPS') or define('MODULE_MITS_CRON_DATABASE_BACKUPS_DELETEOLDBACKUPS', 'true');
defined('MODULE_MITS_CRON_DATABASE_BACKUPS_DELETEOLDBACKUPS_DAYS') or define('MODULE_MITS_CRON_DATABASE_BACKUPS_DELETEOLDBACKUPS_DAYS', '180');
defined('MODULE_MITS_CRON_DATABASE_BACKUPS_DELETELOGS') or define('MODULE_MITS_CRON_DATABASE_BACKUPS_DELETELOGS', 'true');
defined('MODULE_MITS_CRON_DATABASE_BACKUPS_SQL_COMMENTS') or define('MODULE_MITS_CRON_DATABASE_BACKUPS_SQL_COMMENTS', 'true');
defined('MODULE_MITS_CRON_DATABASE_BACKUPS_BACKUP_MODE') or define('MODULE_MITS_CRON_DATABASE_BACKUPS_BACKUP_MODE', 'single');
defined('MODULE_MITS_CRON_DATABASE_BACKUPS_MYSQLDUMP_PATH') or define('MODULE_MITS_CRON_DATABASE_BACKUPS_MYSQLDUMP_PATH', 'mysqldump');
defined('MODULE_MITS_CRON_DATABASE_BACKUPS_MYSQL_PATH') or define('MODULE_MITS_CRON_DATABASE_BACKUPS_MYSQL_PATH', 'mysql');
defined('MODULE_MITS_CRON_DATABASE_BACKUPS_GZIP_PATH') or define('MODULE_MITS_CRON_DATABASE_BACKUPS_GZIP_PATH', 'gzip');
defined('MODULE_MITS_CRON_DATABASE_BACKUPS_DB_HOST') or define('MODULE_MITS_CRON_DATABASE_BACKUPS_DB_HOST', '');
defined('MODULE_MITS_CRON_DATABASE_BACKUPS_DB_PORT') or define('MODULE_MITS_CRON_DATABASE_BACKUPS_DB_PORT', '');
defined('MODULE_MITS_CRON_DATABASE_BACKUPS_DB_SOCKET') or define('MODULE_MITS_CRON_DATABASE_BACKUPS_DB_SOCKET', '');
defined('MODULE_MITS_CRON_DATABASE_BACKUPS_DB_FORCE_TCP') or define('MODULE_MITS_CRON_DATABASE_BACKUPS_DB_FORCE_TCP', 'false');
defined('MODULE_MITS_CRON_DATABASE_BACKUPS_WRITE_LOG') or define('MODULE_MITS_CRON_DATABASE_BACKUPS_WRITE_LOG', 'true');

function mits_cdb_backup_bool($constant, $default = 'false')
{
    $value = defined($constant) ? constant($constant) : $default;
    return ((string)$value === 'true');
}

function mits_cdb_backup_config_value($constant, $default = '')
{
    $value = defined($constant) ? constant($constant) : $default;
    return trim((string)$value);
}

function mits_cdb_backup_write_log_enabled()
{
    return !defined('MODULE_MITS_CRON_DATABASE_BACKUPS_WRITE_LOG')
        || strtolower((string)MODULE_MITS_CRON_DATABASE_BACKUPS_WRITE_LOG) == 'true';
}

function mits_cdb_backup_log($message, $context = array())
{
    if (!mits_cdb_backup_write_log_enabled()) {
        return;
    }

    $parts = array((string)$message);
    foreach ($context as $key => $value) {
        if (is_bool($value)) {
            $value = $value ? 'yes' : 'no';
        } elseif (is_array($value) || is_object($value)) {
            $value = json_encode($value);
        }
        $value = str_replace(array("\r", "\n"), ' ', (string)$value);
        $parts[] = $key . '=' . $value;
    }

    $line = '[' . date('Y-m-d H:i:s') . '] [backup-callback] ' . implode(' | ', $parts) . PHP_EOL;
    $written = false;
    if (defined('DIR_FS_LOG') && is_dir(DIR_FS_LOG) && is_writable(DIR_FS_LOG)) {
        $written = (@error_log($line, 3, DIR_FS_LOG . 'mits_cron_database_backups_' . date('Y-m') . '.log') !== false);
    }
    if (!$written) {
        @error_log(rtrim($line));
    }
}

function mits_cdb_backup_file_mode($path)
{
    $perms = @fileperms($path);
    if ($perms === false) {
        return 'n/a';
    }
    return substr(sprintf('%04o', $perms & 0777), -4);
}

function mits_cdb_backup_path_info($path)
{
    $exists = file_exists($path);
    return $path
      . ';exists=' . ($exists ? 'yes' : 'no')
      . ';readable=' . (is_readable($path) ? 'yes' : 'no')
      . ';writable=' . (is_writable($path) ? 'yes' : 'no')
      . ';mode=' . mits_cdb_backup_file_mode($path)
      . ';uid=' . ($exists && @fileowner($path) !== false ? @fileowner($path) : 'n/a')
      . ';gid=' . ($exists && @filegroup($path) !== false ? @filegroup($path) : 'n/a');
}

function mits_cdb_backup_trigger()
{
    if (PHP_SAPI == 'cli') {
        return 'cli';
    }
    $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? (string)$_SERVER['HTTP_USER_AGENT'] : '';
    if (stripos($user_agent, 'MITS CronDatabaseBackups Scheduled Task') !== false) {
        return 'scheduled-task-curl';
    }
    return 'http';
}

function mits_cdb_backup_db_connection_summary()
{
    $host = defined('DB_SERVER') ? (string)DB_SERVER : 'localhost';
    $configured_host = mits_cdb_backup_config_value('MODULE_MITS_CRON_DATABASE_BACKUPS_DB_HOST', '');
    if ($configured_host != '') {
        $host = $configured_host;
    }

    $port = mits_cdb_backup_config_value('MODULE_MITS_CRON_DATABASE_BACKUPS_DB_PORT', '');
    $socket = mits_cdb_backup_config_value('MODULE_MITS_CRON_DATABASE_BACKUPS_DB_SOCKET', '');
    if ($socket == '' && $port == '' && strpos($host, ':') !== false && substr_count($host, ':') === 1) {
        $host_parts = explode(':', $host, 2);
        if (isset($host_parts[1]) && preg_match('/^[0-9]+$/', $host_parts[1])) {
            $host = $host_parts[0];
            $port = $host_parts[1];
        }
    }

    return 'host=' . $host
      . ';port=' . ($port != '' ? $port : 'default')
      . ';socket=' . ($socket != '' ? $socket : 'none')
      . ';force_tcp=' . (mits_cdb_backup_bool('MODULE_MITS_CRON_DATABASE_BACKUPS_DB_FORCE_TCP', 'false') ? 'yes' : 'no');
}

function mits_cdb_backup_command_binary($constant, $default)
{
    $binary = mits_cdb_backup_config_value($constant, $default);
    if ($binary == '') {
        $binary = $default;
    }
    return escapeshellarg($binary);
}

function mits_cdb_backup_db_connection_args()
{
    $host = defined('DB_SERVER') ? (string)DB_SERVER : 'localhost';
    $configured_host = mits_cdb_backup_config_value('MODULE_MITS_CRON_DATABASE_BACKUPS_DB_HOST', '');
    if ($configured_host != '') {
        $host = $configured_host;
    }

    $port = mits_cdb_backup_config_value('MODULE_MITS_CRON_DATABASE_BACKUPS_DB_PORT', '');
    $socket = mits_cdb_backup_config_value('MODULE_MITS_CRON_DATABASE_BACKUPS_DB_SOCKET', '');

    if ($socket == '' && $port == '' && strpos($host, ':') !== false && substr_count($host, ':') === 1) {
        $host_parts = explode(':', $host, 2);
        if (isset($host_parts[1]) && preg_match('/^[0-9]+$/', $host_parts[1])) {
            $host = $host_parts[0];
            $port = $host_parts[1];
        }
    }

    if ($host == '') {
        $host = 'localhost';
    }

    $args = '';
    if ($socket == '' && mits_cdb_backup_bool('MODULE_MITS_CRON_DATABASE_BACKUPS_DB_FORCE_TCP', 'false')) {
        $args .= ' --protocol=TCP';
    }
    $args .= ' --host=' . escapeshellarg($host);

    if ($port != '' && preg_match('/^[0-9]+$/', $port)) {
        $args .= ' --port=' . escapeshellarg($port);
    }

    if ($socket != '') {
        $args .= ' --socket=' . escapeshellarg($socket);
    }

    return $args;
}

function mits_cdb_backup_command_error_output($target_file, $output = array())
{
    $error_file = $target_file . '.err';
    if (is_file($error_file) && filesize($error_file) > 0) {
        $error_content = trim((string)@file_get_contents($error_file));
        if ($error_content != '') {
            $output[] = $error_content;
        }
    }
    return $output;
}

function mits_cdb_backup_safe_name($value)
{
    return preg_replace('/[^A-Za-z0-9_.-]/', '_', (string)$value);
}

function mits_cdb_backup_ensure_directory_protection($dir)
{
    $dir = rtrim((string)$dir, '/\\') . DIRECTORY_SEPARATOR;
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }

    if (!is_dir($dir) || !is_writable($dir)) {
        return false;
    }
    $protection_check = 'Require all denied';
    $htaccess_file = $dir . '.htaccess';
    $htaccess_block = "
Options -Indexes
"
      . "<IfModule mod_authz_core.c>
"
      . "  Require all denied
"
      . "</IfModule>
"
      . "<IfModule !mod_authz_core.c>
"
      . "  Order deny,allow
"
      . "  Deny from all
"
      . "</IfModule>
";

    $current_htaccess = is_file($htaccess_file) ? (string)@file_get_contents($htaccess_file) : '';
    if ($current_htaccess === '' || strpos($current_htaccess, $protection_check) === false) {
        @file_put_contents($htaccess_file, rtrim($current_htaccess) . $htaccess_block, LOCK_EX);
        @chmod($htaccess_file, 0644);
    }

    $index_file = $dir . 'index.html';
    if (!is_file($index_file)) {
        @file_put_contents($index_file, '', LOCK_EX);
        @chmod($index_file, 0644);
    }

    $web_config_file = $dir . 'web.config';
    if (!is_file($web_config_file)) {
        $web_config = '<?xml version="1.0" encoding="UTF-8"?>' . "
"
          . '<configuration>' . "
"
          . '  <system.webServer>' . "
"
          . '    <security>' . "
"
          . '      <authorization>' . "
"
          . '        <clear />' . "
"
          . '        <add accessType="Deny" users="*" />' . "
"
          . '      </authorization>' . "
"
          . '    </security>' . "
"
          . '  </system.webServer>' . "
"
          . '</configuration>' . "
";
        @file_put_contents($web_config_file, $web_config, LOCK_EX);
        @chmod($web_config_file, 0644);
    }

    return is_file($htaccess_file) && is_file($index_file);
}

function mits_cdb_backup_comments_option()
{
    return mits_cdb_backup_bool('MODULE_MITS_CRON_DATABASE_BACKUPS_SQL_COMMENTS', 'true') ? ' --comments' : ' --skip-comments';
}

function mits_cdb_backup_complete_insert_option()
{
    if (defined('MODULE_MITS_CRON_DATABASE_BACKUPS_COMPLETE_INSERT') && MODULE_MITS_CRON_DATABASE_BACKUPS_COMPLETE_INSERT == 'false') {
        return ' --complete-insert=FALSE';
    }
    return '';
}

function mits_cdb_backup_extended_insert_option()
{
    if (defined('MODULE_MITS_CRON_DATABASE_BACKUPS_EXTENDED_INSERT') && MODULE_MITS_CRON_DATABASE_BACKUPS_EXTENDED_INSERT == 'false') {
        return ' --extended-insert=FALSE';
    }
    return '';
}

function mits_cdb_backup_mysql_password_arg()
{
    if (defined('DB_SERVER_PASSWORD') && DB_SERVER_PASSWORD != '') {
        return ' -p' . escapeshellarg(DB_SERVER_PASSWORD);
    }
    return '';
}

function mits_cdb_backup_mysqldump_command($target_file, $table = '')
{
    $command = mits_cdb_backup_command_binary('MODULE_MITS_CRON_DATABASE_BACKUPS_MYSQLDUMP_PATH', 'mysqldump') . ' --opt'
      . mits_cdb_backup_comments_option()
      . mits_cdb_backup_complete_insert_option()
      . mits_cdb_backup_extended_insert_option()
      . mits_cdb_backup_db_connection_args()
      . ' -u' . escapeshellarg(DB_SERVER_USERNAME)
      . mits_cdb_backup_mysql_password_arg()
      . ' ' . escapeshellarg(DB_DATABASE);

    if ($table != '') {
        $command .= ' ' . escapeshellarg($table);
    }

    $command .= ' > ' . escapeshellarg($target_file) . ' 2> ' . escapeshellarg($target_file . '.err');
    return $command;
}

function mits_cdb_backup_sql_header($mode)
{
    if (!mits_cdb_backup_bool('MODULE_MITS_CRON_DATABASE_BACKUPS_SQL_COMMENTS', 'true')) {
        return '';
    }

    $lines = array(
      '-- ------------------------------------------------------------',
      '-- MITS Cron Database Backups',
      '-- Created: ' . date('Y-m-d H:i:s'),
      '-- Database: ' . (defined('DB_DATABASE') ? DB_DATABASE : ''),
      '-- Backup mode: ' . $mode,
      '-- ------------------------------------------------------------',
      ''
    );

    return implode("\n", $lines) . "\n";
}

function mits_cdb_backup_prepend_sql_header($file, $mode)
{
    $header = mits_cdb_backup_sql_header($mode);
    if ($header == '' || !is_file($file)) {
        return true;
    }

    $temp_file = $file . '.header_tmp_' . mt_rand(1000, 9999);
    $out = @fopen($temp_file, 'wb');
    if (!$out) {
        return false;
    }

    fwrite($out, $header);
    $in = @fopen($file, 'rb');
    if (!$in) {
        fclose($out);
        @unlink($temp_file);
        return false;
    }

    while (!feof($in)) {
        fwrite($out, fread($in, 1048576));
    }

    fclose($in);
    fclose($out);

    if (!@rename($temp_file, $file)) {
        @unlink($temp_file);
        return false;
    }

    return true;
}

function mits_cdb_backup_get_tables()
{
    $tables = array();
    $views = array();
    $tables_query = xtc_db_query('SHOW FULL TABLES');

    while ($table = xtc_db_fetch_array($tables_query)) {
        $values = array_values($table);
        if (empty($values[0])) {
            continue;
        }
        $type = isset($values[1]) ? strtoupper((string)$values[1]) : '';
        if ($type == 'VIEW') {
            $views[] = $values[0];
        } else {
            $tables[] = $values[0];
        }
    }

    sort($tables, SORT_STRING);
    sort($views, SORT_STRING);
    return array_merge($tables, $views);
}

function mits_cdb_backup_delete_directory($dir)
{
    if (!file_exists($dir)) {
        return true;
    }

    if (!is_dir($dir)) {
        return @unlink($dir);
    }

    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        if (is_dir($path)) {
            mits_cdb_backup_delete_directory($path);
        } else {
            @unlink($path);
        }
    }

    return @rmdir($dir);
}

function mits_cdb_backup_table_sql_header($mode, $table)
{
    $header = mits_cdb_backup_sql_header($mode);
    if ($header != '') {
        $header .= '-- Table: ' . $table . "

";
    }
    return $header . "SET FOREIGN_KEY_CHECKS=0;

";
}

function mits_cdb_backup_prepend_text($file, $text)
{
    if ($text == '' || !is_file($file)) {
        return true;
    }

    $temp_file = $file . '.prepend_tmp_' . mt_rand(1000, 9999);
    $out = @fopen($temp_file, 'wb');
    if (!$out) {
        return false;
    }

    fwrite($out, $text);
    $in = @fopen($file, 'rb');
    if (!$in) {
        fclose($out);
        @unlink($temp_file);
        return false;
    }

    while (!feof($in)) {
        fwrite($out, fread($in, 1048576));
    }

    fclose($in);
    fclose($out);

    if (!@rename($temp_file, $file)) {
        @unlink($temp_file);
        return false;
    }

    return true;
}

function mits_cdb_backup_create_tables_directory($target_dir, $backup_base, $selected_tables, &$dump_output)
{
    $backup_dir = rtrim($target_dir, '/\\') . DIRECTORY_SEPARATOR . $backup_base . '_tables';
    mits_cdb_backup_delete_directory($backup_dir);
    @mkdir($backup_dir, 0777, true);
    mits_cdb_backup_ensure_directory_protection($target_dir);
    mits_cdb_backup_ensure_directory_protection($backup_dir);

    if (!is_dir($backup_dir) || !is_writable($backup_dir)) {
        $dump_output = array('Der Backup-Ordner f&uuml;r die Tabellen konnte nicht erstellt werden.');
        mits_cdb_backup_log('Tabellen-Backup fehlgeschlagen: Zielordner nicht beschreibbar.', array('backup_dir' => mits_cdb_backup_path_info($backup_dir)));
        return false;
    }

    $all_tables = mits_cdb_backup_get_tables();
    if (is_array($selected_tables) && !empty($selected_tables)) {
        $allowed = array_flip($all_tables);
        $tables = array();
        foreach ($selected_tables as $selected_table) {
            if (isset($allowed[$selected_table])) {
                $tables[] = $selected_table;
            }
        }
    } else {
        $tables = $all_tables;
    }

    if (empty($tables)) {
        $dump_output = array('Es wurden keine Tabellen f&uuml;r das Backup gefunden.');
        mits_cdb_backup_log('Tabellen-Backup fehlgeschlagen: keine Tabellen gefunden.');
        mits_cdb_backup_delete_directory($backup_dir);
        return false;
    }

    $manifest = array(
      '<?php die(\'Direct Access to this location is not allowed.\'); ?>',
      '# MITS Cron Database Backups',
      '# Created: ' . date('Y-m-d H:i:s'),
      '# Database: ' . (defined('DB_DATABASE') ? DB_DATABASE : ''),
      '# Tables: ' . count($tables),
      ''
    );
    @file_put_contents($backup_dir . DIRECTORY_SEPARATOR . 'mits_manifest.php', implode("\n", $manifest));

    $table_counter = 1;
    foreach ($tables as $table_name) {
        $sql_file = $backup_dir . DIRECTORY_SEPARATOR . sprintf('%04d_%s.sql', $table_counter, mits_cdb_backup_safe_name($table_name));
        $dump_command = mits_cdb_backup_mysqldump_command($sql_file, $table_name);
        $dump_output = array();
        $dump_result = 1;
        exec($dump_command, $dump_output, $dump_result);
        if ($dump_result !== 0 || !is_file($sql_file) || filesize($sql_file) < 1) {
            $dump_output = mits_cdb_backup_command_error_output($sql_file, $dump_output);
            mits_cdb_backup_log('mysqldump fuer Tabelle fehlgeschlagen.', array('table' => $table_name, 'exit_code' => $dump_result, 'output' => implode(' / ', $dump_output)));
            mits_cdb_backup_delete_directory($backup_dir);
            return false;
        }
        @unlink($sql_file . '.err');

        if (!mits_cdb_backup_prepend_text($sql_file, mits_cdb_backup_table_sql_header('tables', $table_name))) {
            mits_cdb_backup_delete_directory($backup_dir);
            $dump_output = array('Der SQL-Kopf konnte nicht eingef&uuml;gt werden.');
            return false;
        }
        @file_put_contents($sql_file, "\nSET FOREIGN_KEY_CHECKS=1;\n", FILE_APPEND);

        $gzip_output = array();
        $gzip_result = 1;
        exec(mits_cdb_backup_command_binary('MODULE_MITS_CRON_DATABASE_BACKUPS_GZIP_PATH', 'gzip') . ' -f ' . escapeshellarg($sql_file) . ' 2>&1', $gzip_output, $gzip_result);
        if ($gzip_result !== 0 || !is_file($sql_file . '.gz') || filesize($sql_file . '.gz') < 1) {
            mits_cdb_backup_delete_directory($backup_dir);
            $dump_output = (!empty($gzip_output) ? $gzip_output : array('GZIP-Komprimierung der Tabelle ist fehlgeschlagen.'));
            mits_cdb_backup_log('GZIP fuer Tabelle fehlgeschlagen.', array('table' => $table_name, 'exit_code' => $gzip_result, 'output' => implode(' / ', $dump_output)));
            return false;
        }
        $table_counter++;
    }

    return $backup_dir;
}

if (defined('MODULE_MITS_CRON_DATABASE_BACKUPS_STATUS') && MODULE_MITS_CRON_DATABASE_BACKUPS_STATUS == 'true') {
    $pw = '';
    if (isset($_GET) && $_GET && isset($_GET['pw'])) {
        $pw = (string)$_GET['pw'];
    } elseif (isset($_REQUEST) && $_REQUEST && isset($_REQUEST['pw'])) {
        $pw = (string)$_REQUEST['pw'];
    } elseif (isset($argv) && is_array($argv) && isset($argv[1])) {
        $pw = (string)$argv[1];
    }

    $valid_pw = false;
    if (!empty($pw) && defined('MODULE_MITS_CRON_DATABASE_BACKUPS_HASH') && MODULE_MITS_CRON_DATABASE_BACKUPS_HASH != '') {
        if (function_exists('hash_equals')) {
            $valid_pw = hash_equals((string)MODULE_MITS_CRON_DATABASE_BACKUPS_HASH, $pw);
        } else {
            $valid_pw = ((string)MODULE_MITS_CRON_DATABASE_BACKUPS_HASH === $pw);
        }
    }

    if (!$valid_pw) {
        if (!headers_sent()) {
            header('HTTP/1.1 403 Forbidden');
        }
        echo 'Kein Zugriff erlaubt!';
        exit;
    } else {
        @ini_set('display_errors', 1);
        @set_time_limit(0);

        $exec_enabled = function_exists('exec') && !in_array('exec', array_map('trim', explode(',', (string)ini_get('disable_functions')))) && strtolower((string)ini_get('safe_mode')) != 1;
        $no_exec = '';
        $backup_success = false;
        $dump_output = array();
        $dump_result = null;
        $gzip_output = array();
        $gzip_result = null;

        $module_backup_dir = DIR_FS_DOCUMENT_ROOT . 'export/mits_cron_database_backups';
        $admin_backup_dir = DIR_FS_DOCUMENT_ROOT . (defined('DIR_ADMIN') ? DIR_ADMIN : 'admin/') . 'backups';
        mits_cdb_backup_ensure_directory_protection($module_backup_dir);

        if (is_dir($module_backup_dir) && is_writable($module_backup_dir)) {
            $dir = 'export/mits_cron_database_backups/';
        } elseif (is_dir($admin_backup_dir) && is_writable($admin_backup_dir)) {
            $dir = (defined('DIR_ADMIN') ? DIR_ADMIN : 'admin/') . 'backups/';
            mits_cdb_backup_log('Primaerer Backup-Ordner nicht beschreibbar, Admin-Backupordner wird verwendet.', array('primary_dir' => mits_cdb_backup_path_info($module_backup_dir), 'fallback_dir' => mits_cdb_backup_path_info($admin_backup_dir)));
        } else {
            $dir = 'export/mits_cron_database_backups/';
        }

        $target_backup_dir = DIR_FS_DOCUMENT_ROOT . $dir;
        mits_cdb_backup_log(
            'Backup-Callback gestartet',
            array(
                'trigger' => mits_cdb_backup_trigger(),
                'sapi' => PHP_SAPI,
                'cwd' => getcwd(),
                'euid' => function_exists('posix_geteuid') ? posix_geteuid() : 'n/a',
                'egid' => function_exists('posix_getegid') ? posix_getegid() : 'n/a',
                'exec' => $exec_enabled ? 'yes' : 'no',
                'backup_dir' => mits_cdb_backup_path_info($target_backup_dir),
                'mysqldump' => mits_cdb_backup_config_value('MODULE_MITS_CRON_DATABASE_BACKUPS_MYSQLDUMP_PATH', 'mysqldump'),
                'db' => mits_cdb_backup_db_connection_summary(),
                'open_basedir' => (string)ini_get('open_basedir'),
            )
        );
        $safe_database = mits_cdb_backup_safe_name(DB_DATABASE);
        $backup_base = $safe_database . "_" . date("Y-m-d_H-i-s");
        $backup_mode = (defined('MODULE_MITS_CRON_DATABASE_BACKUPS_BACKUP_MODE') ? MODULE_MITS_CRON_DATABASE_BACKUPS_BACKUP_MODE : 'single');
        $sql_file = $backup_base . ".sql";
        $backup_file = DIR_FS_DOCUMENT_ROOT . $dir . $sql_file;
        $backup_final_file = $backup_file;
        $backup_final_name = $sql_file;

        if ($exec_enabled) {
            if ($backup_mode == 'tables' || $backup_mode == 'tables_zip') {
                $backup_tables_dir = mits_cdb_backup_create_tables_directory(DIR_FS_DOCUMENT_ROOT . $dir, $backup_base, array(), $dump_output);
                $backup_success = ($backup_tables_dir !== false);
                $backup_final_file = $backup_tables_dir;
                $backup_final_name = basename($backup_tables_dir);
            } else {
                $dump_command = mits_cdb_backup_mysqldump_command($backup_file);
                exec($dump_command, $dump_output, $dump_result);
                $backup_success = ($dump_result === 0 && is_file($backup_file) && filesize($backup_file) > 0);
                if (!$backup_success) {
                    $dump_output = mits_cdb_backup_command_error_output($backup_file, $dump_output);
                    mits_cdb_backup_log('mysqldump fehlgeschlagen.', array('exit_code' => $dump_result, 'target' => mits_cdb_backup_path_info($backup_file), 'output' => implode(' / ', $dump_output)));
                } else {
                    @unlink($backup_file . '.err');
                    $backup_success = mits_cdb_backup_prepend_sql_header($backup_file, 'single');
                }
                if ($backup_success && defined('MODULE_MITS_CRON_DATABASE_BACKUPS_GZIP') && MODULE_MITS_CRON_DATABASE_BACKUPS_GZIP == 'true') {
                    exec(mits_cdb_backup_command_binary('MODULE_MITS_CRON_DATABASE_BACKUPS_GZIP_PATH', 'gzip') . ' -f ' . escapeshellarg($backup_file) . ' 2>&1', $gzip_output, $gzip_result);
                    $backup_success = ($gzip_result === 0 && is_file($backup_file . '.gz') && filesize($backup_file . '.gz') > 0);
                    if (!$backup_success) {
                        $dump_output = (!empty($gzip_output) ? $gzip_output : array('GZIP-Komprimierung der Sicherung ist fehlgeschlagen.'));
                        mits_cdb_backup_log('GZIP-Komprimierung fehlgeschlagen.', array('exit_code' => $gzip_result, 'output' => implode(' / ', $dump_output)));
                    }
                    $backup_final_file = $backup_file . '.gz';
                    $backup_final_name = $sql_file . '.gz';
                }
            }
        } else {
            $no_exec = '<p style="padding:6px;color:#444;font-size:14px;"><strong>Ihr Server verf&uuml;gt nicht &uuml;ber die notwendigen Bereichtigungen. Die Funktion <i>exec()</i>ist deaktiviert.</strong></p>';
            mits_cdb_backup_log('Backup abgebrochen: exec() ist nicht verfuegbar.', array('disable_functions' => (string)ini_get('disable_functions')));
        }

        echo '
        <!doctype html>
        <html>
        <head>
        <meta charset="' . (isset($_SESSION['language_charset']) ? $_SESSION['language_charset'] : 'utf-8') . '">
        <title>Datenbanksicherung</title>
        </head>
        <body style="text-align:center;background:#ffe;font-family: Arial, Helvetica, sans-serif">
        <div style="text-align:center">
          <a href="https://www.merz-it-service.de/">
            <img src="' . DIR_WS_CATALOG . 'callback/mits_cron_database_backups/merz-it-service.png" border="0" alt="MerZ IT-SerVice" style="margin:0 auto;display:block;max-width:100%;height:auto;" />
          </a>
        </div>
        <h1 style="padding:6px;color:#444;font-size:18px;">MITS Datenbanksicherung per Cronjob</h1> 
        ' . $no_exec . '      
      ';

        if ($backup_success) {
            $backup_size = is_file($backup_final_file) ? @filesize($backup_final_file) : 0;
            mits_cdb_backup_log('Datenbanksicherung erfolgreich erstellt.', array('backup' => basename($backup_final_file), 'size' => $backup_size, 'mode' => $backup_mode));
            echo '<p style="padding:6px;color:#444;font-size:14px;"><strong>Datenbank wurde erfolgreich gesichert!</strong></p>' . "\n<span style='display:none'>MITS_CRON_DATABASE_BACKUPS_SUCCESS</span>\n";

            if (is_file($backup_final_file) && defined('MODULE_MITS_CRON_DATABASE_BACKUPS_SENDMAIL') && MODULE_MITS_CRON_DATABASE_BACKUPS_SENDMAIL == 'true') {
                if (defined('MODULE_MITS_CRON_DATABASE_BACKUPS_MAILADDRESS') && MODULE_MITS_CRON_DATABASE_BACKUPS_MAILADDRESS != '') {
                    require_once(DIR_FS_INC . 'xtc_validate_email.inc.php');
                    if (xtc_validate_email(MODULE_MITS_CRON_DATABASE_BACKUPS_MAILADDRESS)) {
                        $mail_file = $backup_final_file;
                        $mail_content_html = 'Im Anhang befindet sich die Datenbanksicherung des Shops ' . STORE_NAME . ' vom ' . date("d.m.Y H:i:s") . ' Uhr';
                        $mail_content_txt = 'Im Anhang befindet sich die Datenbanksicherung des Shops ' . STORE_NAME . ' vom ' . date("d.m.Y H:i:s") . ' Uhr';
                        xtc_php_mail(
                          EMAIL_SUPPORT_ADDRESS,
                          EMAIL_SUPPORT_NAME,
                          MODULE_MITS_CRON_DATABASE_BACKUPS_MAILADDRESS,
                          'MITS CronDatabaseBackups by Hetfield',
                          '',
                          EMAIL_SUPPORT_REPLY_ADDRESS,
                          EMAIL_SUPPORT_REPLY_ADDRESS_NAME,
                          $mail_file,
                          '',
                          'Datenbanksicherung vom ' . date("d.m.Y H:i:s") . ' Uhr',
                          $mail_content_html,
                          $mail_content_txt
                        );
                        echo '<p style="padding:6px;color:#444;font-size:14px;"><strong>E-Mail mit der Datenbanksicherung wurde gesendet!</strong></p>';
                    }
                }
            }

            if (is_file($backup_final_file) && defined('MODULE_MITS_CRON_DATABASE_BACKUPS_SENDFTP') && MODULE_MITS_CRON_DATABASE_BACKUPS_SENDFTP == 'true') {
                if (defined('MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_HOST') && MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_HOST != '') {
                    if (defined('MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_PORT') && MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_PORT != '' && is_numeric(MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_PORT)) {
                        $ftp_conn_id = ftp_connect(MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_HOST, MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_PORT);
                    } else {
                        $ftp_conn_id = ftp_connect(MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_HOST);
                    }

                    if (!$ftp_conn_id) {
                        echo '<p style="padding:6px;color:#444;font-size:14px;"><strong>FTP-Verbindung ist fehlgeschlagen!</strong></p>';
                    } elseif (defined('MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_USER') && MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_USER != ''
                      && defined('MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_PASS') && MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_PASS != '') {
                        $ftp_login_result = ftp_login($ftp_conn_id, MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_USER, MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_PASS);
                        if (!$ftp_login_result) {
                            echo '<p style="padding:6px;color:#444;font-size:14px;"><strong>FTP-Verbindung ist fehlgeschlagen!</strong></p>';
                        } else {
                            echo '<p style="padding:6px;color:#444;font-size:14px;"><strong>Verbunden mit FTP-Server!</strong></p>';
                            $ftp_path = MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_PATH;
                            if (substr($ftp_path, -1) == '/') {
                                $ftp_path = substr($ftp_path, 0, -1);
                            }
                            $destination_file = $ftp_path . '/' . $backup_final_name;
                            $source_file = $backup_final_file;
                            $ftp_upload = ftp_put($ftp_conn_id, $destination_file, $source_file, FTP_BINARY);
                            if (!$ftp_upload) {
                                echo '<p style="padding:6px;color:#444;font-size:14px;"><strong>FTP-Upload ist fehlgeschlagen!</strong></p>';
                            } else {
                                echo '<p style="padding:6px;color:#444;font-size:14px;"><strong>Datenbanksicherung erfolgreich auf den FTP-Server &uuml;bertragen!</strong></p>';
                            }
                        }
                        ftp_close($ftp_conn_id);
                    }
                }
            }

            if (defined('MODULE_MITS_CRON_DATABASE_BACKUPS_DELETEOLDBACKUPS') 
              && MODULE_MITS_CRON_DATABASE_BACKUPS_DELETEOLDBACKUPS == 'true' 
              && is_numeric(MODULE_MITS_CRON_DATABASE_BACKUPS_DELETEOLDBACKUPS_DAYS)
            ) {
                $timestamp = time();
                $handle = opendir($dir);
                $daysinsecond = MODULE_MITS_CRON_DATABASE_BACKUPS_DELETEOLDBACKUPS_DAYS * 24 * 60 * 60;
                while ($datei = readdir($handle)) {
                    if ($datei == '.' || $datei == '..') {
                    } else {
                        $datum = filemtime($dir . '/' . $datei);
                        if ($timestamp - $datum > $daysinsecond) {
                            if (preg_match('/\.sql$/i', $datei)) {
                                @unlink($dir . '/' . $datei);
                            } elseif (preg_match('/\.sql.gz$/i', $datei)) {
                                @unlink($dir . '/' . $datei);
                            } elseif (is_dir($dir . '/' . $datei) && preg_match('/_tables$/i', $datei)) {
                                mits_cdb_backup_delete_directory($dir . '/' . $datei);
                            }
                        }
                    }
                }
                closedir($handle);
                echo '<p style="padding:6px;color:#444;font-size:14px;"><strong>Alte Datenbanksicherungen erfolgreich gel&ouml;scht!</strong></p>';

                if (defined('MODULE_MITS_CRON_DATABASE_BACKUPS_DELETELOGS') && MODULE_MITS_CRON_DATABASE_BACKUPS_DELETELOGS == 'true') {
                    $log_dir = 'log/';
                    $handle_log = opendir($log_dir);
                    while ($datei = readdir($handle_log)) {
                        if ($datei == '.' || $datei == '..' || $datei == 'index.html') {
                        } elseif (!is_dir(DIR_FS_LOG . $datei) && $datei != 'xss_blacklist.log') {
                            $datum = filemtime($log_dir . '/' . $datei);
                            if ($timestamp - $datum > $daysinsecond) {
                                @unlink($log_dir . '/' . $datei);
                            } elseif (substr($datei, 0, 10) == 'mod_notice') {
                                @unlink($log_dir . '/' . $datei);
                            } elseif (substr($datei, 0, 14) == 'mod_deprecated') {
                                @unlink($log_dir . '/' . $datei);
                            } elseif (substr($datei, 0, 10) == 'mod_strict') {
                                @unlink($log_dir . '/' . $datei);
                            }
                        }
                    }
                    closedir($handle_log);
                    echo '<p style="padding:6px;color:#444;font-size:14px;"><strong>Alte Log-Files erfolgreich gel&ouml;scht!</strong></p>';
                }
            }
        } else {
            mits_cdb_backup_log('Datenbanksicherung wurde nicht erstellt.', array('backup_dir' => mits_cdb_backup_path_info(DIR_FS_DOCUMENT_ROOT . $dir), 'output' => !empty($dump_output) ? implode(' / ', $dump_output) : 'keine Ausgabe'));
            echo '<p style="padding:6px;color:#444;font-size:14px;"><strong>Datenbanksicherung wurde nicht erstellt! Bitte &uuml;berpr&uuml;fen sie die Serverberechtigungen!</strong></p>';
            if (!empty($dump_output)) {
                echo '<pre style="display:inline-block;text-align:left;max-width:90%;white-space:pre-wrap;padding:10px;background:#fff;border:1px solid #ccc;color:#444;">' . htmlspecialchars(implode("
", $dump_output), ENT_QUOTES) . '</pre>';
            }
        }

        echo '
      <a style="display:block;padding:6px;margin:0 auto;color:#fff;background:#444;width:60%;-webkit-border-radius:4px;-moz-border-radius:4px;border-radius:4px;text-decoration:none;" href="' . xtc_href_link_admin(
            (defined('DIR_ADMIN') ? DIR_ADMIN : 'admin/') . 'module_export.php',
            'set=system&module=mits_cron_database_backups',
            'NONSSL'
          ) . '"><strong>Zur&uuml;ck zum Modul &raquo;</strong></a>
      <p style="text-align:center;padding:6px;color:#555;font-size:11px;margin-top:50px;"> &copy; by <a href="https://www.merz-it-service.de/"><span style="padding:2px;background:#ffe;color:#6a9;font-weight:bold;">Hetfield (MerZ IT-SerVice)</span></a></p>
      </body>
      </html>
    ';
    }
}
?>