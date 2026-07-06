<?php
/**
 * --------------------------------------------------------------
 * File: mits_cron_database_restore.php
 * Date: 12.06.2026
 *
 * Author: Hetfield
 * Copyright: (c) 2026 - MerZ IT-SerVice
 * Web: https://www.merz-it-service.de
 * Contact: info@merz-it-service.de
 *
 * Released under the GNU General Public License
 * --------------------------------------------------------------
 */

require('includes/application_top.php');

@set_time_limit(0);

function mits_cdb_restore_get_charset()
{
    if (isset($_SESSION['language_charset']) && $_SESSION['language_charset'] != '') {
        return $_SESSION['language_charset'];
    }
    if (defined('CHARSET') && CHARSET != '') {
        return CHARSET;
    }
    return 'UTF-8';
}

function mits_cdb_restore_html($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, mits_cdb_restore_get_charset());
}

function mits_cdb_restore_plain_html($value, $strip_tags = false)
{
    $charset = mits_cdb_restore_get_charset();
    $value = html_entity_decode((string)$value, ENT_QUOTES | ENT_HTML5, $charset);
    if ($strip_tags === true) {
        $value = strip_tags($value);
    }
    return htmlspecialchars($value, ENT_QUOTES, $charset);
}

function mits_cdb_restore_text($constant, $fallback)
{
    return defined($constant) ? constant($constant) : $fallback;
}

function mits_cdb_restore_trailing_slash($path)
{
    return rtrim($path, '/\\') . DIRECTORY_SEPARATOR;
}

function mits_cdb_restore_exec_enabled()
{
    return function_exists('exec')
        && !in_array('exec', array_map('trim', explode(',', (string)ini_get('disable_functions'))))
        && strtolower((string)ini_get('safe_mode')) != '1';
}

function mits_cdb_restore_make_token()
{
    if (function_exists('random_bytes')) {
        return bin2hex(random_bytes(32));
    }
    if (function_exists('xtc_RandomString')) {
        return xtc_RandomString(32);
    }
    return md5(uniqid(mt_rand(), true));
}

function mits_cdb_restore_ensure_token()
{
    if (empty($_SESSION['MITS_CDB_RESTORE_TOKEN'])) {
        $_SESSION['MITS_CDB_RESTORE_TOKEN'] = mits_cdb_restore_make_token();
    }
    return $_SESSION['MITS_CDB_RESTORE_TOKEN'];
}

function mits_cdb_restore_check_token($token)
{
    if (empty($_SESSION['MITS_CDB_RESTORE_TOKEN']) || $token == '') {
        return false;
    }
    if (function_exists('hash_equals')) {
        return hash_equals((string)$_SESSION['MITS_CDB_RESTORE_TOKEN'], (string)$token);
    }
    return ((string)$_SESSION['MITS_CDB_RESTORE_TOKEN'] === (string)$token);
}

function mits_cdb_restore_check_admin_csrf()
{
    if (!isset($_SESSION['CSRFName']) || !isset($_SESSION['CSRFToken'])) {
        return true;
    }

    $csrf_name = (string)$_SESSION['CSRFName'];
    if ($csrf_name == '' || !isset($_POST[$csrf_name])) {
        return false;
    }

    if (function_exists('hash_equals')) {
        return hash_equals((string)$_SESSION['CSRFToken'], (string)$_POST[$csrf_name]);
    }

    return ((string)$_SESSION['CSRFToken'] === (string)$_POST[$csrf_name]);
}

function mits_cdb_restore_rotate_token()
{
    $_SESSION['MITS_CDB_RESTORE_TOKEN'] = mits_cdb_restore_make_token();
}

function mits_cdb_restore_csrf_hidden_fields()
{
    $fields = '';
    if (isset($_SESSION['CSRFName']) && isset($_SESSION['CSRFToken'])) {
        $fields .= xtc_draw_hidden_field($_SESSION['CSRFName'], $_SESSION['CSRFToken']);
    }
    $fields .= xtc_draw_hidden_field('mits_restore_token', mits_cdb_restore_ensure_token());
    return $fields;
}

function mits_cdb_restore_backup_dirs()
{
    $dirs = array();

    $module_dir = mits_cdb_restore_trailing_slash(DIR_FS_DOCUMENT_ROOT . 'export/mits_cron_database_backups');
    if (is_dir($module_dir)) {
        $dirs['module'] = array(
            'key' => 'module',
            'path' => $module_dir,
            'label' => TEXT_MITS_CDB_RESTORE_DIR_MODULE,
        );
    }

    if (defined('DIR_FS_BACKUP')) {
        $admin_dir = mits_cdb_restore_trailing_slash(DIR_FS_BACKUP);
    } else {
        $admin_dir = mits_cdb_restore_trailing_slash(DIR_FS_ADMIN . 'backups');
    }
    if (is_dir($admin_dir) && realpath($admin_dir) !== realpath($module_dir)) {
        $dirs['admin'] = array(
            'key' => 'admin',
            'path' => $admin_dir,
            'label' => TEXT_MITS_CDB_RESTORE_DIR_ADMIN,
        );
    }

    return $dirs;
}

function mits_cdb_restore_file_allowed($filename)
{
    return (bool)preg_match('/^[A-Za-z0-9_.-]+\.(sql|sql\.gz|zip)$/i', $filename);
}

function mits_cdb_restore_folder_allowed($foldername)
{
    return (bool)preg_match('/^[A-Za-z0-9_.-]+_tables$/i', $foldername);
}

function mits_cdb_restore_table_file_allowed($filename)
{
    return (bool)preg_match('/^[A-Za-z0-9_.-]+\.(sql|sql\.gz)$/i', $filename);
}

function mits_cdb_restore_directory_size($dir)
{
    $size = 0;
    foreach (glob(rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . '*') as $file) {
        if (is_link($file)) {
            continue;
        }
        if (is_file($file)) {
            $size += filesize($file);
        }
    }
    return $size;
}

function mits_cdb_restore_directory_mtime($dir)
{
    $mtime = is_dir($dir) ? filemtime($dir) : 0;
    foreach (glob(rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . '*') as $file) {
        if (is_link($file)) {
            continue;
        }
        if (is_file($file)) {
            $mtime = max($mtime, filemtime($file));
        }
    }
    return $mtime;
}

function mits_cdb_restore_collect_table_files($dir)
{
    $files = array();
    $dir_real = realpath($dir);
    if ($dir_real === false || !is_dir($dir_real)) {
        return $files;
    }
    $dir_real_slash = mits_cdb_restore_trailing_slash($dir_real);

    foreach (array('*.sql.gz', '*.sql') as $pattern) {
        foreach (glob(rtrim($dir, '/\\') . DIRECTORY_SEPARATOR . $pattern) as $file) {
            if (!is_file($file) || is_link($file)) {
                continue;
            }
            $filename = basename($file);
            if (!mits_cdb_restore_table_file_allowed($filename) || strpos($filename, '.restore_tmp_') !== false) {
                continue;
            }
            $file_real = realpath($file);
            if ($file_real === false || strpos($file_real, $dir_real_slash) !== 0) {
                continue;
            }
            $files[] = array(
                'filename' => $filename,
                'path' => $file_real,
                'size' => filesize($file_real),
                'mtime' => filemtime($file_real),
                'compressed' => (substr(strtolower($filename), -3) === '.gz'),
            );
        }
    }
    usort($files, function ($a, $b) {
        return strcmp($a['filename'], $b['filename']);
    });
    return $files;
}

function mits_cdb_restore_table_file_label($filename)
{
    $label = preg_replace('/^[0-9]+_/', '', (string)$filename);
    $label = preg_replace('/\.sql(\.gz)?$/i', '', $label);
    return $label;
}

function mits_cdb_restore_get_tables()
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

function mits_cdb_restore_selected_tables($posted_tables)
{
    $selected = array();
    if (!is_array($posted_tables)) {
        return $selected;
    }
    $allowed = array_flip(mits_cdb_restore_get_tables());
    foreach ($posted_tables as $table) {
        $table = (string)$table;
        if (isset($allowed[$table])) {
            $selected[] = $table;
        }
    }
    return array_values(array_unique($selected));
}

function mits_cdb_restore_selected_table_files($backup, $posted_files)
{
    $available = array();
    foreach (mits_cdb_restore_collect_table_files($backup['path']) as $file) {
        $available[$file['filename']] = $file;
    }

    if (!is_array($posted_files) || empty($posted_files)) {
        return array();
    }

    $selected = array();
    foreach ($posted_files as $file) {
        $file = basename((string)$file);
        if (isset($available[$file])) {
            $selected[$file] = $available[$file];
        }
    }
    return array_values($selected);
}

function mits_cdb_restore_mysql_password_arg()
{
    if (defined('DB_SERVER_PASSWORD') && DB_SERVER_PASSWORD != '') {
        return ' -p' . escapeshellarg(DB_SERVER_PASSWORD);
    }
    return '';
}

function mits_cdb_restore_dump_comments_option()
{
    if (defined('MODULE_MITS_CRON_DATABASE_BACKUPS_SQL_COMMENTS') && MODULE_MITS_CRON_DATABASE_BACKUPS_SQL_COMMENTS == 'false') {
        return ' --skip-comments';
    }
    return ' --comments';
}

function mits_cdb_restore_dump_complete_insert_option()
{
    if (defined('MODULE_MITS_CRON_DATABASE_BACKUPS_COMPLETE_INSERT') && MODULE_MITS_CRON_DATABASE_BACKUPS_COMPLETE_INSERT == 'false') {
        return ' --complete-insert=FALSE';
    }
    return '';
}

function mits_cdb_restore_dump_extended_insert_option()
{
    if (defined('MODULE_MITS_CRON_DATABASE_BACKUPS_EXTENDED_INSERT') && MODULE_MITS_CRON_DATABASE_BACKUPS_EXTENDED_INSERT == 'false') {
        return ' --extended-insert=FALSE';
    }
    return '';
}

function mits_cdb_restore_safe_name($value)
{
    return preg_replace('/[^A-Za-z0-9_.-]/', '_', (string)$value);
}

function mits_cdb_restore_mysqldump_command($target_file, $tables = array())
{
    $command = 'mysqldump --opt'
      . mits_cdb_restore_dump_comments_option()
      . mits_cdb_restore_dump_complete_insert_option()
      . mits_cdb_restore_dump_extended_insert_option()
      . ' -h' . escapeshellarg(DB_SERVER)
      . ' -u' . escapeshellarg(DB_SERVER_USERNAME)
      . mits_cdb_restore_mysql_password_arg()
      . ' ' . escapeshellarg(DB_DATABASE);

    foreach ($tables as $table) {
        $command .= ' ' . escapeshellarg($table);
    }

    $command .= ' > ' . escapeshellarg($target_file) . ' 2>&1';
    return $command;
}

function mits_cdb_restore_sql_header($mode, $extra = '')
{
    if (defined('MODULE_MITS_CRON_DATABASE_BACKUPS_SQL_COMMENTS') && MODULE_MITS_CRON_DATABASE_BACKUPS_SQL_COMMENTS == 'false') {
        return '';
    }

    $lines = array(
      '-- ------------------------------------------------------------',
      '-- MITS Cron Database Backups',
      '-- Created: ' . date('Y-m-d H:i:s'),
      '-- Database: ' . (defined('DB_DATABASE') ? DB_DATABASE : ''),
      '-- Backup mode: ' . $mode,
    );
    if ($extra != '') {
        $lines[] = '-- ' . $extra;
    }
    $lines[] = '-- ------------------------------------------------------------';
    $lines[] = '';
    return implode("\n", $lines) . "\n";
}

function mits_cdb_restore_prepend_text($file, $text)
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

function mits_cdb_restore_collect_backups($dirs)
{
    $backups = array();
    foreach ($dirs as $dir_key => $dir) {
        foreach (array('*.sql', '*.sql.gz', '*.zip') as $pattern) {
            foreach (glob($dir['path'] . $pattern) as $file) {
                if (!is_file($file)) {
                    continue;
                }
                $filename = basename($file);
                if (!mits_cdb_restore_file_allowed($filename) || strpos($filename, '.restore_tmp_') !== false) {
                    continue;
                }
                $backups[] = array(
                    'id' => $dir_key . '|' . $filename,
                    'dir_key' => $dir_key,
                    'filename' => $filename,
                    'path' => $file,
                    'directory' => $dir['label'],
                    'size' => filesize($file),
                    'mtime' => filemtime($file),
                    'compressed' => (substr(strtolower($filename), -3) === '.gz'),
                    'archive' => (substr(strtolower($filename), -4) === '.zip'),
                    'tables_dir' => false,
                    'table_count' => 0,
                );
            }
        }

        foreach (glob($dir['path'] . '*', GLOB_ONLYDIR) as $folder) {
            $foldername = basename($folder);
            if (!mits_cdb_restore_folder_allowed($foldername)) {
                continue;
            }
            $table_files = mits_cdb_restore_collect_table_files($folder);
            if (empty($table_files)) {
                continue;
            }
            $backups[] = array(
                'id' => $dir_key . '|dir:' . $foldername,
                'dir_key' => $dir_key,
                'filename' => $foldername,
                'path' => realpath($folder),
                'directory' => $dir['label'],
                'size' => mits_cdb_restore_directory_size($folder),
                'mtime' => mits_cdb_restore_directory_mtime($folder),
                'compressed' => true,
                'archive' => false,
                'tables_dir' => true,
                'table_count' => count($table_files),
            );
        }
    }

    usort($backups, function ($a, $b) {
        if ($a['mtime'] == $b['mtime']) {
            return strcmp($a['filename'], $b['filename']);
        }
        return ($a['mtime'] < $b['mtime']) ? 1 : -1;
    });

    return $backups;
}

function mits_cdb_restore_resolve_backup($id, $dirs)
{
    $parts = explode('|', (string)$id, 2);
    if (count($parts) != 2) {
        return false;
    }

    $dir_key = $parts[0];
    $backup_name = $parts[1];
    if (!isset($dirs[$dir_key])) {
        return false;
    }

    if (strpos($backup_name, 'dir:') === 0) {
        $foldername = basename(substr($backup_name, 4));
        if (!mits_cdb_restore_folder_allowed($foldername)) {
            return false;
        }
        $dir_real = realpath($dirs[$dir_key]['path']);
        $folder_real = realpath($dirs[$dir_key]['path'] . $foldername);
        if ($dir_real === false || $folder_real === false || !is_dir($folder_real)) {
            return false;
        }
        $dir_real = mits_cdb_restore_trailing_slash($dir_real);
        if (strpos($folder_real, $dir_real) !== 0) {
            return false;
        }
        $table_files = mits_cdb_restore_collect_table_files($folder_real);
        if (empty($table_files)) {
            return false;
        }
        return array(
            'id' => $dir_key . '|dir:' . $foldername,
            'dir_key' => $dir_key,
            'filename' => $foldername,
            'path' => $folder_real,
            'directory' => $dirs[$dir_key]['label'],
            'size' => mits_cdb_restore_directory_size($folder_real),
            'mtime' => mits_cdb_restore_directory_mtime($folder_real),
            'compressed' => true,
            'archive' => false,
            'tables_dir' => true,
            'table_count' => count($table_files),
        );
    }

    $filename = basename($backup_name);
    if (!mits_cdb_restore_file_allowed($filename)) {
        return false;
    }

    $dir_real = realpath($dirs[$dir_key]['path']);
    $file_real = realpath($dirs[$dir_key]['path'] . $filename);
    if ($dir_real === false || $file_real === false || !is_file($file_real)) {
        return false;
    }

    $dir_real = mits_cdb_restore_trailing_slash($dir_real);
    if (strpos($file_real, $dir_real) !== 0) {
        return false;
    }

    return array(
        'id' => $dir_key . '|' . $filename,
        'dir_key' => $dir_key,
        'filename' => $filename,
        'path' => $file_real,
        'directory' => $dirs[$dir_key]['label'],
        'size' => filesize($file_real),
        'mtime' => filemtime($file_real),
        'compressed' => (substr(strtolower($filename), -3) === '.gz'),
        'archive' => (substr(strtolower($filename), -4) === '.zip'),
        'tables_dir' => false,
        'table_count' => 0,
    );
}

function mits_cdb_restore_send_download($backup)
{
    if (!is_array($backup) || !empty($backup['tables_dir']) || empty($backup['path']) || !is_file($backup['path']) || !is_readable($backup['path'])) {
        return false;
    }

    $filename = basename($backup['filename']);
    if (!mits_cdb_restore_file_allowed($filename)) {
        return false;
    }

    if (function_exists('apache_setenv')) {
        @apache_setenv('no-gzip', '1');
    }
    @ini_set('zlib.output_compression', 'Off');

    while (ob_get_level() > 0) {
        @ob_end_clean();
    }

    header('Content-Type: application/octet-stream');
    header('Content-Description: File Transfer');
    header('Content-Disposition: attachment; filename="' . $filename . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
    header('Content-Transfer-Encoding: binary');
    header('Content-Length: ' . filesize($backup['path']));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    $handle = fopen($backup['path'], 'rb');
    if ($handle === false) {
        return false;
    }

    while (!feof($handle)) {
        echo fread($handle, 1048576);
        flush();
    }
    fclose($handle);
    exit;
}

function mits_cdb_restore_resolve_table_download($backup, $filename)
{
    if (!is_array($backup) || empty($backup['tables_dir']) || empty($backup['path']) || !is_dir($backup['path'])) {
        return false;
    }
    $filename = basename((string)$filename);
    if (!mits_cdb_restore_table_file_allowed($filename)) {
        return false;
    }
    $dir_real = realpath($backup['path']);
    $file_real = realpath($backup['path'] . DIRECTORY_SEPARATOR . $filename);
    if ($dir_real === false || $file_real === false || !is_file($file_real)) {
        return false;
    }
    $dir_real = mits_cdb_restore_trailing_slash($dir_real);
    if (strpos($file_real, $dir_real) !== 0) {
        return false;
    }
    return array(
        'filename' => $filename,
        'path' => $file_real,
        'size' => filesize($file_real),
    );
}

function mits_cdb_restore_send_table_download($table_file)
{
    if (!is_array($table_file) || empty($table_file['path']) || !is_file($table_file['path']) || !is_readable($table_file['path'])) {
        return false;
    }
    $filename = basename($table_file['filename']);
    if (!mits_cdb_restore_table_file_allowed($filename)) {
        return false;
    }

    if (function_exists('apache_setenv')) {
        @apache_setenv('no-gzip', '1');
    }
    @ini_set('zlib.output_compression', 'Off');

    while (ob_get_level() > 0) {
        @ob_end_clean();
    }

    header('Content-Type: application/octet-stream');
    header('Content-Description: File Transfer');
    header('Content-Disposition: attachment; filename="' . $filename . '"; filename*=UTF-8\'\'' . rawurlencode($filename));
    header('Content-Transfer-Encoding: binary');
    header('Content-Length: ' . filesize($table_file['path']));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');

    $handle = fopen($table_file['path'], 'rb');
    if ($handle === false) {
        return false;
    }
    while (!feof($handle)) {
        echo fread($handle, 1048576);
        flush();
    }
    fclose($handle);
    exit;
}

function mits_cdb_restore_delete_backup_item($backup, &$messages)
{
    if (!is_array($backup) || empty($backup['path'])) {
        $messages[] = TEXT_MITS_CDB_RESTORE_ERROR_INVALID_FILE;
        return false;
    }

    if (!empty($backup['tables_dir'])) {
        if (!is_dir($backup['path']) || !mits_cdb_restore_folder_allowed($backup['filename'])) {
            $messages[] = TEXT_MITS_CDB_RESTORE_ERROR_INVALID_FILE;
            return false;
        }
        $name = $backup['filename'];
        if (mits_cdb_restore_delete_directory($backup['path'])) {
            $messages[] = sprintf(mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_DELETE_SUCCESS', 'Backup wurde gel&ouml;scht: %s'), $name);
            return true;
        }
        $messages[] = sprintf(mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_DELETE_ERROR', 'Backup konnte nicht gel&ouml;scht werden: %s'), $name);
        return false;
    }

    $filename = basename($backup['filename']);
    if (!mits_cdb_restore_file_allowed($filename) || !is_file($backup['path'])) {
        $messages[] = TEXT_MITS_CDB_RESTORE_ERROR_INVALID_FILE;
        return false;
    }
    if (@unlink($backup['path'])) {
        $messages[] = sprintf(mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_DELETE_SUCCESS', 'Backup wurde gel&ouml;scht: %s'), $filename);
        return true;
    }
    $messages[] = sprintf(mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_DELETE_ERROR', 'Backup konnte nicht gel&ouml;scht werden: %s'), $filename);
    return false;
}

function mits_cdb_restore_delete_table_file($backup, $filename, &$messages)
{
    $table_file = mits_cdb_restore_resolve_table_download($backup, $filename);
    if ($table_file === false) {
        $messages[] = TEXT_MITS_CDB_RESTORE_ERROR_INVALID_FILE;
        return false;
    }

    if (!@unlink($table_file['path'])) {
        $messages[] = sprintf(mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_DELETE_TABLE_ERROR', 'Tabellendatei konnte nicht gel&ouml;scht werden: %s'), $table_file['filename']);
        return false;
    }

    $messages[] = sprintf(mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_DELETE_TABLE_SUCCESS', 'Tabellendatei wurde gel&ouml;scht: %s'), $table_file['filename']);

    if (empty(mits_cdb_restore_collect_table_files($backup['path']))) {
        mits_cdb_restore_delete_directory($backup['path']);
        $messages[] = mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_DELETE_TABLE_FOLDER_EMPTY', 'Der Tabellen-Backupordner enthielt keine weiteren Tabellendateien und wurde entfernt.');
    }

    return true;
}


function mits_cdb_restore_format_filesize($bytes)
{
    $bytes = (float)$bytes;
    $units = array('B', 'KB', 'MB', 'GB');
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return number_format($bytes, ($i == 0 ? 0 : 2), ',', '.') . ' ' . $units[$i];
}

function mits_cdb_restore_mysql_args()
{
    $args = '';
    if (defined('DB_SERVER') && DB_SERVER != '') {
        $args .= ' -h' . escapeshellarg(DB_SERVER);
    }
    if (defined('DB_SERVER_USERNAME') && DB_SERVER_USERNAME != '') {
        $args .= ' -u' . escapeshellarg(DB_SERVER_USERNAME);
    }
    if (defined('DB_SERVER_PASSWORD') && DB_SERVER_PASSWORD != '') {
        $args .= ' -p' . escapeshellarg(DB_SERVER_PASSWORD);
    }
    $args .= ' ' . escapeshellarg(DB_DATABASE);
    return $args;
}

function mits_cdb_restore_create_safety_backup($target_dir, &$messages)
{
    $target_dir = mits_cdb_restore_trailing_slash($target_dir);
    if (!is_dir($target_dir) || !is_writable($target_dir)) {
        $messages[] = TEXT_MITS_CDB_RESTORE_ERROR_SAFETY_DIR;
        return false;
    }

    $safe_database = preg_replace('/[^A-Za-z0-9_.-]/', '_', DB_DATABASE);
    $target_file = $target_dir . $safe_database . '_before_restore_' . date('Y-m-d_H-i-s') . '.sql';

    $complete_insert = '';
    if (defined('MODULE_MITS_CRON_DATABASE_BACKUPS_COMPLETE_INSERT') && MODULE_MITS_CRON_DATABASE_BACKUPS_COMPLETE_INSERT == 'false') {
        $complete_insert = ' --complete-insert=FALSE';
    }

    $extended_insert = '';
    if (defined('MODULE_MITS_CRON_DATABASE_BACKUPS_EXTENDED_INSERT') && MODULE_MITS_CRON_DATABASE_BACKUPS_EXTENDED_INSERT == 'false') {
        $extended_insert = ' --extended-insert=FALSE';
    }

    $password_arg = '';
    if (defined('DB_SERVER_PASSWORD') && DB_SERVER_PASSWORD != '') {
        $password_arg = ' -p' . escapeshellarg(DB_SERVER_PASSWORD);
    }

    $command = 'mysqldump --opt' . $complete_insert . $extended_insert
        . ' -h' . escapeshellarg(DB_SERVER)
        . ' -u' . escapeshellarg(DB_SERVER_USERNAME)
        . $password_arg
        . ' ' . escapeshellarg(DB_DATABASE)
        . ' > ' . escapeshellarg($target_file)
        . ' 2>&1';

    $output = array();
    $return = 1;
    exec($command, $output, $return);

    if ($return !== 0 || !is_file($target_file) || filesize($target_file) < 1) {
        @unlink($target_file);
        $messages[] = TEXT_MITS_CDB_RESTORE_ERROR_SAFETY_BACKUP;
        if (!empty($output)) {
            $messages[] = implode("\n", $output);
        }
        return false;
    }

    $final_file = $target_file;
    if (defined('MODULE_MITS_CRON_DATABASE_BACKUPS_GZIP') && MODULE_MITS_CRON_DATABASE_BACKUPS_GZIP == 'true') {
        $gzip_output = array();
        $gzip_return = 1;
        exec('gzip -f ' . escapeshellarg($target_file) . ' 2>&1', $gzip_output, $gzip_return);

        if ($gzip_return !== 0 || !is_file($target_file . '.gz') || filesize($target_file . '.gz') < 1) {
            @unlink($target_file . '.gz');
            $messages[] = TEXT_MITS_CDB_RESTORE_ERROR_SAFETY_GZIP;
            if (!empty($gzip_output)) {
                $messages[] = implode("\n", $gzip_output);
            }
            return false;
        }

        $final_file = $target_file . '.gz';
    }

    $messages[] = sprintf(TEXT_MITS_CDB_RESTORE_SAFETY_BACKUP_CREATED, basename($final_file));
    return $final_file;
}

function mits_cdb_restore_decompress_gz($source, $target, &$messages)
{
    if (!function_exists('gzopen')) {
        $messages[] = TEXT_MITS_CDB_RESTORE_ERROR_ZLIB;
        return false;
    }

    $in = @gzopen($source, 'rb');
    if (!$in) {
        $messages[] = TEXT_MITS_CDB_RESTORE_ERROR_GZ_OPEN;
        return false;
    }

    $out = @fopen($target, 'wb');
    if (!$out) {
        gzclose($in);
        $messages[] = TEXT_MITS_CDB_RESTORE_ERROR_TEMPFILE;
        return false;
    }

    while (!gzeof($in)) {
        $data = gzread($in, 1024 * 1024);
        if ($data === false) {
            fclose($out);
            gzclose($in);
            @unlink($target);
            $messages[] = TEXT_MITS_CDB_RESTORE_ERROR_GZ_READ;
            return false;
        }
        if ($data !== '') {
            fwrite($out, $data);
        }
    }

    fclose($out);
    gzclose($in);

    if (!is_file($target) || filesize($target) < 1) {
        @unlink($target);
        $messages[] = TEXT_MITS_CDB_RESTORE_ERROR_TEMPFILE;
        return false;
    }

    return true;
}


function mits_cdb_restore_delete_directory($dir)
{
    if (!file_exists($dir)) {
        return true;
    }

    if (is_link($dir) || !is_dir($dir)) {
        return @unlink($dir);
    }

    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        $path = $dir . DIRECTORY_SEPARATOR . $file;
        if (is_link($path)) {
            @unlink($path);
        } elseif (is_dir($path)) {
            mits_cdb_restore_delete_directory($path);
        } else {
            @unlink($path);
        }
    }

    return @rmdir($dir);
}

function mits_cdb_restore_extract_zip_sql($source, $temp_dir, $target_file, &$messages)
{
    if (!class_exists('ZipArchive')) {
        $messages[] = mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_ERROR_ZIPARCHIVE', 'Die PHP-Erweiterung ZipArchive ist nicht aktiv. ZIP-Backups k&ouml;nnen deshalb nicht entpackt werden.');
        return false;
    }

    $zip = new ZipArchive();
    if ($zip->open($source) !== true) {
        $messages[] = mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_ERROR_ZIP_OPEN', 'Die ZIP-Datei konnte nicht ge&ouml;ffnet werden.');
        return false;
    }

    @mkdir($temp_dir, 0777, true);
    if (!is_dir($temp_dir) || !is_writable($temp_dir)) {
        $zip->close();
        $messages[] = TEXT_MITS_CDB_RESTORE_ERROR_TEMPFILE;
        return false;
    }

    $sql_files = array();
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entry = $zip->getNameIndex($i);
        $entry_name = basename($entry);
        if ($entry_name == '' || $entry_name != $entry || !preg_match('/^[A-Za-z0-9_.-]+\.sql$/i', $entry_name)) {
            continue;
        }
        if (!$zip->extractTo($temp_dir, $entry)) {
            continue;
        }
        $entry_path = $temp_dir . DIRECTORY_SEPARATOR . $entry_name;
        if (is_file($entry_path) && filesize($entry_path) > 0) {
            $sql_files[] = $entry_path;
        }
    }
    $zip->close();

    if (empty($sql_files)) {
        $messages[] = mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_ERROR_ZIP_EMPTY', 'In der ZIP-Datei wurden keine SQL-Dateien gefunden.');
        return false;
    }

    sort($sql_files, SORT_STRING);
    $out = @fopen($target_file, 'wb');
    if (!$out) {
        $messages[] = TEXT_MITS_CDB_RESTORE_ERROR_TEMPFILE;
        return false;
    }

    fwrite($out, "SET FOREIGN_KEY_CHECKS=0;\n\n");
    foreach ($sql_files as $sql_file) {
        $in = @fopen($sql_file, 'rb');
        if (!$in) {
            fclose($out);
            @unlink($target_file);
            $messages[] = TEXT_MITS_CDB_RESTORE_ERROR_TEMPFILE;
            return false;
        }
        fwrite($out, "\n-- MITS ZIP part: " . basename($sql_file) . "\n");
        while (!feof($in)) {
            fwrite($out, fread($in, 1048576));
        }
        fclose($in);
        fwrite($out, "\n");
    }
    fwrite($out, "\nSET FOREIGN_KEY_CHECKS=1;\n");
    fclose($out);

    if (!is_file($target_file) || filesize($target_file) < 1) {
        @unlink($target_file);
        $messages[] = TEXT_MITS_CDB_RESTORE_ERROR_TEMPFILE;
        return false;
    }

    $messages[] = mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_ZIP_UNPACKED', 'ZIP-Backup wurde tempor&auml;r entpackt und f&uuml;r den Import vorbereitet.');
    return true;
}

function mits_cdb_restore_create_manual_backup($target_dir, $mode, $selected_tables, &$messages)
{
    $target_dir = mits_cdb_restore_trailing_slash($target_dir);
    if (!is_dir($target_dir)) {
        @mkdir($target_dir, 0777, true);
    }
    if (!is_dir($target_dir) || !is_writable($target_dir)) {
        $messages[] = mits_cdb_restore_text('TEXT_MITS_CDB_BACKUP_ERROR_DIR', 'Der Backupordner ist nicht beschreibbar.');
        return false;
    }

    if (empty($selected_tables)) {
        $messages[] = mits_cdb_restore_text('TEXT_MITS_CDB_BACKUP_ERROR_NO_TABLES', 'Es wurden keine Tabellen ausgew&auml;hlt.');
        return false;
    }

    $safe_database = mits_cdb_restore_safe_name(DB_DATABASE);
    $backup_base = $safe_database . '_' . date('Y-m-d_H-i-s');
    $mode = ($mode == 'tables') ? 'tables' : 'single';

    if ($mode == 'tables') {
        $backup_dir = $target_dir . $backup_base . '_tables';
        mits_cdb_restore_delete_directory($backup_dir);
        @mkdir($backup_dir, 0777, true);
        if (!is_dir($backup_dir) || !is_writable($backup_dir)) {
            $messages[] = mits_cdb_restore_text('TEXT_MITS_CDB_BACKUP_ERROR_DIR', 'Der Backupordner ist nicht beschreibbar.');
            return false;
        }

        $manifest = array(
          '<?php die(\'Direct Access to this location is not allowed.\'); ?>',
          '# MITS Cron Database Backups',
          '# Created: ' . date('Y-m-d H:i:s'),
          '# Database: ' . DB_DATABASE,
          '# Tables: ' . count($selected_tables),
          ''
        );
        @file_put_contents($backup_dir . DIRECTORY_SEPARATOR . 'mits_manifest.php', implode("\n", $manifest));

        $counter = 1;
        foreach ($selected_tables as $table) {
            $sql_file = $backup_dir . DIRECTORY_SEPARATOR . sprintf('%04d_%s.sql', $counter, mits_cdb_restore_safe_name($table));
            $output = array();
            $return = 1;
            exec(mits_cdb_restore_mysqldump_command($sql_file, array($table)), $output, $return);
            if ($return !== 0 || !is_file($sql_file) || filesize($sql_file) < 1) {
                mits_cdb_restore_delete_directory($backup_dir);
                $messages[] = sprintf(mits_cdb_restore_text('TEXT_MITS_CDB_BACKUP_ERROR_TABLE', 'Die Tabelle %s konnte nicht gesichert werden.'), $table);
                if (!empty($output)) {
                    $messages[] = implode("\n", $output);
                }
                return false;
            }

            $header = mits_cdb_restore_sql_header('tables', 'Table: ' . $table) . "SET FOREIGN_KEY_CHECKS=0;\n\n";
            if (!mits_cdb_restore_prepend_text($sql_file, $header)) {
                mits_cdb_restore_delete_directory($backup_dir);
                $messages[] = mits_cdb_restore_text('TEXT_MITS_CDB_BACKUP_ERROR_HEADER', 'Der SQL-Kopf konnte nicht eingef&uuml;gt werden.');
                return false;
            }
            @file_put_contents($sql_file, "\nSET FOREIGN_KEY_CHECKS=1;\n", FILE_APPEND);

            $gzip_output = array();
            $gzip_return = 1;
            exec('gzip -f ' . escapeshellarg($sql_file) . ' 2>&1', $gzip_output, $gzip_return);
            if ($gzip_return !== 0 || !is_file($sql_file . '.gz') || filesize($sql_file . '.gz') < 1) {
                mits_cdb_restore_delete_directory($backup_dir);
                $messages[] = sprintf(mits_cdb_restore_text('TEXT_MITS_CDB_BACKUP_ERROR_TABLE', 'Die Tabelle %s konnte nicht gesichert werden.'), $table);
                if (!empty($gzip_output)) {
                    $messages[] = implode("\n", $gzip_output);
                }
                return false;
            }
            $counter++;
        }

        $messages[] = sprintf(mits_cdb_restore_text('TEXT_MITS_CDB_BACKUP_SUCCESS_TABLES', 'Tabellen-Backup wurde erstellt: %s (%s Tabellen)'), basename($backup_dir), count($selected_tables));
        return $backup_dir;
    }

    $sql_file = $target_dir . $backup_base . '.sql';
    $output = array();
    $return = 1;
    exec(mits_cdb_restore_mysqldump_command($sql_file, $selected_tables), $output, $return);
    if ($return !== 0 || !is_file($sql_file) || filesize($sql_file) < 1) {
        @unlink($sql_file);
        $messages[] = mits_cdb_restore_text('TEXT_MITS_CDB_BACKUP_ERROR_CREATE', 'Die manuelle Datenbanksicherung konnte nicht erstellt werden.');
        if (!empty($output)) {
            $messages[] = implode("\n", $output);
        }
        return false;
    }

    $header = mits_cdb_restore_sql_header('single', count($selected_tables) . ' tables selected');
    if (!mits_cdb_restore_prepend_text($sql_file, $header)) {
        @unlink($sql_file);
        $messages[] = mits_cdb_restore_text('TEXT_MITS_CDB_BACKUP_ERROR_HEADER', 'Der SQL-Kopf konnte nicht eingef&uuml;gt werden.');
        return false;
    }

    $final_file = $sql_file;
    if (defined('MODULE_MITS_CRON_DATABASE_BACKUPS_GZIP') && MODULE_MITS_CRON_DATABASE_BACKUPS_GZIP == 'true') {
        $gzip_output = array();
        $gzip_return = 1;
        exec('gzip -f ' . escapeshellarg($sql_file) . ' 2>&1', $gzip_output, $gzip_return);
        if ($gzip_return !== 0 || !is_file($sql_file . '.gz') || filesize($sql_file . '.gz') < 1) {
            @unlink($sql_file . '.gz');
            $messages[] = mits_cdb_restore_text('TEXT_MITS_CDB_BACKUP_ERROR_GZIP', 'Die GZIP-Komprimierung der manuellen Sicherung ist fehlgeschlagen.');
            if (!empty($gzip_output)) {
                $messages[] = implode("\n", $gzip_output);
            }
            return false;
        }
        $final_file = $sql_file . '.gz';
    }

    $messages[] = sprintf(mits_cdb_restore_text('TEXT_MITS_CDB_BACKUP_SUCCESS_SINGLE', 'Manuelles Backup wurde erstellt: %s'), basename($final_file));
    return $final_file;
}

function mits_cdb_restore_import_sql_file($file, &$messages)
{
    $command = 'mysql --binary-mode=1' . mits_cdb_restore_mysql_args() . ' < ' . escapeshellarg($file) . ' 2>&1';
    $output = array();
    $return = 1;
    exec($command, $output, $return);
    if ($return === 0) {
        return true;
    }
    if (!empty($output)) {
        $messages[] = implode("\n", $output);
    }
    return false;
}

function mits_cdb_restore_db_link()
{
    global $db_link;
    return (isset($db_link) && is_object($db_link)) ? $db_link : false;
}

function mits_cdb_restore_sql_uncommented_start($sql)
{
    $sql = ltrim((string)$sql);
    $changed = true;
    while ($changed && $sql != '') {
        $changed = false;
        $sql = ltrim($sql);
        if (substr($sql, 0, 3) === "\xEF\xBB\xBF") {
            $sql = substr($sql, 3);
            $changed = true;
            continue;
        }
        if (substr($sql, 0, 2) === '/*') {
            $pos = strpos($sql, '*/');
            if ($pos !== false) {
                $sql = substr($sql, $pos + 2);
                $changed = true;
                continue;
            }
        }
        if (substr($sql, 0, 2) === '--' && (strlen($sql) == 2 || preg_match('/\s/', substr($sql, 2, 1)))) {
            $pos = strpos($sql, "\n");
            $sql = ($pos === false) ? '' : substr($sql, $pos + 1);
            $changed = true;
            continue;
        }
        if (substr($sql, 0, 1) === '#') {
            $pos = strpos($sql, "\n");
            $sql = ($pos === false) ? '' : substr($sql, $pos + 1);
            $changed = true;
            continue;
        }
    }
    return ltrim($sql);
}

function mits_cdb_restore_sql_statement_keyword($sql)
{
    $sql = mits_cdb_restore_sql_uncommented_start($sql);
    if (preg_match('/^([a-zA-Z_]+)/', $sql, $match)) {
        return strtoupper($match[1]);
    }
    return '';
}

function mits_cdb_restore_sql_is_readonly($sql)
{
    return in_array(mits_cdb_restore_sql_statement_keyword($sql), array('SELECT', 'SHOW', 'DESCRIBE', 'DESC', 'EXPLAIN'));
}

function mits_cdb_restore_sql_uses_server_files($sql)
{
    $clean = mits_cdb_restore_sql_uncommented_start($sql);
    return (bool)preg_match('/\bLOAD_FILE\s*\(|\bINTO\s+(OUTFILE|DUMPFILE)\b|^\s*LOAD\s+DATA\b/i', $clean);
}

function mits_cdb_restore_split_sql($sql)
{
    $sql = str_replace("\r\n", "\n", (string)$sql);
    $sql = str_replace("\r", "\n", $sql);

    $statements = array();
    $buffer = '';
    $length = strlen($sql);
    $quote = '';
    $in_line_comment = false;
    $in_block_comment = false;

    for ($i = 0; $i < $length; $i++) {
        $char = $sql[$i];
        $next = ($i + 1 < $length) ? $sql[$i + 1] : '';

        if ($in_line_comment) {
            $buffer .= $char;
            if ($char === "\n") {
                $in_line_comment = false;
            }
            continue;
        }

        if ($in_block_comment) {
            $buffer .= $char;
            if ($char === '*' && $next === '/') {
                $buffer .= $next;
                $i++;
                $in_block_comment = false;
            }
            continue;
        }

        if ($quote != '') {
            $buffer .= $char;
            if ($char === '\\') {
                if ($i + 1 < $length) {
                    $buffer .= $sql[$i + 1];
                    $i++;
                }
                continue;
            }
            if ($char === $quote) {
                if (($quote === "'" || $quote === '"') && $next === $quote) {
                    $buffer .= $next;
                    $i++;
                    continue;
                }
                $quote = '';
            }
            continue;
        }

        if ($char === '-' && $next === '-' && ($i + 2 >= $length || preg_match('/\s/', $sql[$i + 2]))) {
            $buffer .= $char . $next;
            $i++;
            $in_line_comment = true;
            continue;
        }

        if ($char === '#') {
            $buffer .= $char;
            $in_line_comment = true;
            continue;
        }

        if ($char === '/' && $next === '*') {
            $buffer .= $char . $next;
            $i++;
            $in_block_comment = true;
            continue;
        }

        if ($char === "'" || $char === '"' || $char === '`') {
            $buffer .= $char;
            $quote = $char;
            continue;
        }

        if ($char === ';') {
            if (trim($buffer) != '') {
                $statements[] = trim($buffer);
            }
            $buffer = '';
            continue;
        }

        $buffer .= $char;
    }

    if (trim($buffer) != '') {
        $statements[] = trim($buffer);
    }

    return $statements;
}

function mits_cdb_restore_sql_short_statement($sql)
{
    $sql = trim(preg_replace('/\s+/', ' ', (string)$sql));
    if (strlen($sql) > 500) {
        return substr($sql, 0, 500) . ' ...';
    }
    return $sql;
}

function mits_cdb_restore_sql_row_limit($value)
{
    $value = (int)$value;
    $allowed = array(25, 50, 100, 250, 500);
    return in_array($value, $allowed) ? $value : 100;
}

function mits_cdb_restore_run_sql_box($sql, $allow_write, $row_limit, &$messages, &$results)
{
    $sql = trim((string)$sql);
    $row_limit = mits_cdb_restore_sql_row_limit($row_limit);
    $results = array();

    if ($sql == '') {
        $messages[] = mits_cdb_restore_text('TEXT_MITS_CDB_SQL_ERROR_EMPTY', 'Es wurde kein SQL-Code eingegeben.');
        return false;
    }

    $link = mits_cdb_restore_db_link();
    if ($link === false) {
        $messages[] = mits_cdb_restore_text('TEXT_MITS_CDB_SQL_ERROR_CONNECTION', 'Die Datenbankverbindung ist nicht verf&uuml;gbar.');
        return false;
    }

    $statements = mits_cdb_restore_split_sql($sql);
    if (empty($statements)) {
        $messages[] = mits_cdb_restore_text('TEXT_MITS_CDB_SQL_ERROR_EMPTY', 'Es wurde kein SQL-Code eingegeben.');
        return false;
    }

    $counter = 0;
    foreach ($statements as $statement) {
        $counter++;
        $entry = array(
            'number' => $counter,
            'statement' => mits_cdb_restore_sql_short_statement($statement),
            'success' => false,
            'message' => '',
            'fields' => array(),
            'rows' => array(),
            'total_rows' => 0,
            'displayed_rows' => 0,
            'affected_rows' => null,
            'insert_id' => null,
        );

        if (mits_cdb_restore_sql_uses_server_files($statement)) {
            $entry['message'] = mits_cdb_restore_text('TEXT_MITS_CDB_SQL_ERROR_FILE_OPERATION', 'SQL-Anweisungen mit LOAD_FILE, LOAD DATA, INTO OUTFILE oder INTO DUMPFILE werden aus Sicherheitsgr&uuml;nden nicht ausgef&uuml;hrt.');
            $results[] = $entry;
            $messages[] = sprintf(mits_cdb_restore_text('TEXT_MITS_CDB_SQL_ERROR_STATEMENT', 'SQL #%s fehlgeschlagen: %s'), $counter, $entry['message']);
            return false;
        }

        if (!mits_cdb_restore_sql_is_readonly($statement) && !$allow_write) {
            $entry['message'] = mits_cdb_restore_text('TEXT_MITS_CDB_SQL_ERROR_WRITE_CONFIRM', 'Schreibende SQL-Anweisungen wurden nicht ausgef&uuml;hrt, weil die Freigabe nicht aktiviert wurde.');
            $results[] = $entry;
            $messages[] = sprintf(mits_cdb_restore_text('TEXT_MITS_CDB_SQL_ERROR_STATEMENT', 'SQL #%s fehlgeschlagen: %s'), $counter, $entry['message']);
            return false;
        }

        try {
            $query_result = mysqli_query($link, $statement);
        } catch (Exception $e) {
            $entry['message'] = $e->getMessage();
            $results[] = $entry;
            $messages[] = sprintf(mits_cdb_restore_text('TEXT_MITS_CDB_SQL_ERROR_STATEMENT', 'SQL #%s fehlgeschlagen: %s'), $counter, $e->getMessage());
            return false;
        }

        if ($query_result === false) {
            $error = mysqli_error($link);
            $entry['message'] = $error;
            $results[] = $entry;
            $messages[] = sprintf(mits_cdb_restore_text('TEXT_MITS_CDB_SQL_ERROR_STATEMENT', 'SQL #%s fehlgeschlagen: %s'), $counter, $error);
            return false;
        }

        $entry['success'] = true;
        if (is_object($query_result)) {
            while ($field = mysqli_fetch_field($query_result)) {
                $entry['fields'][] = $field->name;
            }
            $entry['total_rows'] = mysqli_num_rows($query_result);
            while ($row = mysqli_fetch_assoc($query_result)) {
                if ($entry['displayed_rows'] < $row_limit) {
                    $entry['rows'][] = $row;
                    $entry['displayed_rows']++;
                }
            }
            mysqli_free_result($query_result);
            $entry['message'] = sprintf(mits_cdb_restore_text('TEXT_MITS_CDB_SQL_SUCCESS_ROWS', '%s Datens&auml;tze gefunden, %s angezeigt.'), (int)$entry['total_rows'], (int)$entry['displayed_rows']);
        } else {
            $entry['affected_rows'] = mysqli_affected_rows($link);
            $entry['insert_id'] = mysqli_insert_id($link);
            $entry['message'] = sprintf(mits_cdb_restore_text('TEXT_MITS_CDB_SQL_SUCCESS_AFFECTED', 'SQL-Anweisung ausgef&uuml;hrt. Betroffene Zeilen: %s.'), (int)$entry['affected_rows']);
            if ((int)$entry['insert_id'] > 0) {
                $entry['message'] .= ' ' . sprintf(mits_cdb_restore_text('TEXT_MITS_CDB_SQL_SUCCESS_INSERT_ID', 'Insert-ID: %s.'), (int)$entry['insert_id']);
            }
        }

        $results[] = $entry;
    }

    $messages[] = sprintf(mits_cdb_restore_text('TEXT_MITS_CDB_SQL_SUCCESS_SUMMARY', '%s SQL-Anweisung(en) erfolgreich ausgef&uuml;hrt.'), count($statements));
    return true;
}

function mits_cdb_restore_run_tables_dir($backup, $selected_table_files, $dirs, &$messages)
{
    $success = false;
    $temp_files = array();

    if (empty($selected_table_files)) {
        $messages[] = mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_ERROR_NO_TABLES', 'Es wurden keine Tabellen f&uuml;r die R&uuml;cksicherung ausgew&auml;hlt.');
        return false;
    }

    foreach ($selected_table_files as $table_file) {
        $import_file = $table_file['path'];
        if (!empty($table_file['compressed'])) {
            $temp_file = $dirs[$backup['dir_key']]['path'] . basename($table_file['filename'], '.gz') . '.restore_tmp_' . date('YmdHis') . '_' . mt_rand(1000, 9999) . '.sql';
            if (!mits_cdb_restore_decompress_gz($table_file['path'], $temp_file, $messages)) {
                foreach ($temp_files as $old_temp_file) {
                    if (is_file($old_temp_file)) {
                        @unlink($old_temp_file);
                    }
                }
                return false;
            }
            $temp_files[] = $temp_file;
            $import_file = $temp_file;
        }

        if (!mits_cdb_restore_import_sql_file($import_file, $messages)) {
            $messages[] = sprintf(mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_ERROR_IMPORT_TABLE', 'Der Import der Tabellendatei %s ist fehlgeschlagen.'), $table_file['filename']);
            foreach ($temp_files as $old_temp_file) {
                if (is_file($old_temp_file)) {
                    @unlink($old_temp_file);
                }
            }
            return false;
        }
        $messages[] = sprintf(mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_TABLE_IMPORTED', 'Tabellendatei importiert: %s'), $table_file['filename']);
        $success = true;
    }

    foreach ($temp_files as $old_temp_file) {
        if (is_file($old_temp_file)) {
            @unlink($old_temp_file);
        }
    }

    return $success;
}


function mits_cdb_restore_lock_file($dirs)
{
    if (defined('DIR_FS_CATALOG') && is_dir(DIR_FS_CATALOG . 'cache') && is_writable(DIR_FS_CATALOG . 'cache')) {
        return DIR_FS_CATALOG . 'cache/mits_cron_database_restore.lock';
    }
    foreach ($dirs as $dir) {
        if (is_writable($dir['path'])) {
            return $dir['path'] . 'mits_cron_database_restore.lock';
        }
    }
    return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mits_cron_database_restore.lock';
}

function mits_cdb_restore_acquire_lock($lock_file)
{
    if (is_file($lock_file) && filemtime($lock_file) < (time() - 12 * 60 * 60)) {
        @unlink($lock_file);
    }
    $handle = @fopen($lock_file, 'x');
    if (!$handle) {
        return false;
    }
    fwrite($handle, date('c') . PHP_EOL);
    fclose($handle);
    return true;
}

function mits_cdb_restore_write_log($messages)
{
    if (!defined('DIR_FS_LOG') || !is_dir(DIR_FS_LOG) || !is_writable(DIR_FS_LOG)) {
        return;
    }
    $line = '[' . date('Y-m-d H:i:s') . '] ' . implode(' | ', array_map('strip_tags', $messages)) . PHP_EOL;
    @error_log($line, 3, DIR_FS_LOG . 'mits_cron_database_restore_' . date('Y-m') . '.log');
}

function mits_cdb_restore_destroy_admin_session()
{
    if (function_exists('session_name') && session_id() != '') {
        $_SESSION = array();

        if ((bool)ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            $cookie_params = array(
                'expires' => time() - 42000,
                'path' => isset($params['path']) ? $params['path'] : '/',
                'domain' => isset($params['domain']) ? $params['domain'] : '',
                'secure' => isset($params['secure']) ? (bool)$params['secure'] : false,
                'httponly' => isset($params['httponly']) ? (bool)$params['httponly'] : true,
            );

            if (defined('PHP_VERSION_ID') && PHP_VERSION_ID >= 70300) {
                if (isset($params['samesite'])) {
                    $cookie_params['samesite'] = $params['samesite'];
                }
                @setcookie(session_name(), '', $cookie_params);
            } else {
                @setcookie(session_name(), '', $cookie_params['expires'], $cookie_params['path'], $cookie_params['domain'], $cookie_params['secure'], $cookie_params['httponly']);
            }
        }

        @session_destroy();
    }
}

function mits_cdb_restore_render_relogin_page($messages)
{
    $charset = mits_cdb_restore_get_charset();
    $title = mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_RELOGIN_TITLE', 'Restore completed - login required');
    $text = mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_RELOGIN_TEXT', 'The restore was completed. For security reasons, the current admin session has been closed. Please log in again.');
    $button = mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_RELOGIN_BUTTON', 'Go to admin login');
    $note = mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_RELOGIN_NOTE', 'This is intentional because session, admin user and permission data may have been restored to the state of the backup.');
    $login_url = xtc_catalog_href_link('login_admin.php');
    $message_text = implode("\n", $messages);

    mits_cdb_restore_destroy_admin_session();

    if (!headers_sent()) {
        header('Content-Type: text/html; charset=' . $charset);
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }

    echo '<!doctype html>' . "\n";
    echo '<html><head><meta charset="' . mits_cdb_restore_plain_html($charset, true) . '">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . mits_cdb_restore_plain_html($title, true) . '</title>';
    echo '<style>'
        . 'body{font-family:Arial,Helvetica,sans-serif;background:#f4f8f6;margin:0;padding:32px;color:#444}'
        . '.mits-relogin{--mits-ci-primary:#6a9;--mits-ci-primary-dark:#4f8e7e;--mits-ci-primary-soft:#edf7f4;--mits-ci-line:#d4e7e0;--mits-ci-heading:#30534b;--mits-ci-muted:#6d7b77;--mits-ci-shadow:rgba(76,110,101,.12);max-width:960px;margin:42px auto}'
        . '.mits-relogin__card{background:#fff;border:1px solid var(--mits-ci-line);border-radius:22px;box-shadow:0 16px 36px var(--mits-ci-shadow);overflow:hidden}'
        . '.mits-relogin__hero{padding:28px 32px;background:linear-gradient(135deg,#fff 0%,var(--mits-ci-primary-soft) 100%);border-bottom:1px solid var(--mits-ci-line)}'
        . '.mits-relogin__badge{display:inline-flex;align-items:center;padding:7px 12px;border-radius:999px;background:#e9f7f1;color:#2c715d;font-weight:700;font-size:12px;margin-bottom:14px}'
        . '.mits-relogin h1{margin:0 0 8px;font-size:28px;line-height:1.2;color:var(--mits-ci-heading)}'
        . '.mits-relogin p{margin:0;color:var(--mits-ci-muted);line-height:1.55}'
        . '.mits-relogin__body{padding:24px 32px}'
        . '.mits-relogin__note{margin:16px 0;padding:14px 16px;border:1px solid #d4e7e0;border-radius:14px;background:#f7fbfa;color:#536661}'
        . '.mits-relogin__log{white-space:pre-wrap;margin:16px 0;padding:14px 16px;border:1px solid var(--mits-ci-line);border-radius:14px;background:#fcfefd;max-height:300px;overflow:auto;color:#444}'
        . '.mits-relogin__button,.mits-relogin__button:link,.mits-relogin__button:visited{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:0 18px;border-radius:12px;border:1px solid var(--mits-ci-primary-dark);background:var(--mits-ci-primary-dark);color:#fff;text-decoration:none;font-weight:700}'
        . '.mits-relogin__button:hover{background:#467d70;border-color:#467d70}'
        . '</style>';
    echo '</head><body><div class="mits-relogin"><div class="mits-relogin__card">';
    echo '<div class="mits-relogin__hero"><span class="mits-relogin__badge">' . mits_cdb_restore_plain_html(mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_RESULT_SUCCESS', 'Restore completed'), true) . '</span><h1>' . mits_cdb_restore_plain_html($title, true) . '</h1><p>' . mits_cdb_restore_plain_html($text) . '</p></div>';
    echo '<div class="mits-relogin__body"><div class="mits-relogin__note">' . mits_cdb_restore_plain_html($note) . '</div>';
    if ($message_text != '') {
        echo '<div class="mits-relogin__log">' . mits_cdb_restore_plain_html($message_text) . '</div>';
    }
    echo '<a class="mits-relogin__button" href="' . mits_cdb_restore_plain_html($login_url, true) . '">' . mits_cdb_restore_plain_html($button, true) . '</a>';
    echo '</div></div></div></body></html>';
    exit;
}

function mits_cdb_restore_run($backup, $dirs, &$messages, $selected_table_files = array())
{
    if (!mits_cdb_restore_exec_enabled()) {
        $messages[] = TEXT_MITS_CDB_RESTORE_ERROR_EXEC_DISABLED;
        return false;
    }

    $lock_file = mits_cdb_restore_lock_file($dirs);
    if (!mits_cdb_restore_acquire_lock($lock_file)) {
        $messages[] = TEXT_MITS_CDB_RESTORE_ERROR_LOCKED;
        return false;
    }

    $temp_file = '';
    $temp_dir = '';
    $success = false;

    try {
        $safety_backup = mits_cdb_restore_create_safety_backup($dirs[$backup['dir_key']]['path'], $messages);
        if ($safety_backup !== false) {
            if (!empty($backup['tables_dir'])) {
                $success = mits_cdb_restore_run_tables_dir($backup, $selected_table_files, $dirs, $messages);
                if ($success) {
                    $messages[] = sprintf(TEXT_MITS_CDB_RESTORE_SUCCESS, $backup['filename']);
                }
            } else {
                $import_file = $backup['path'];

                if (!empty($backup['archive'])) {
                    $temp_dir = $dirs[$backup['dir_key']]['path'] . basename($backup['filename'], '.zip') . '.restore_tmp_' . date('YmdHis') . '_' . mt_rand(1000, 9999);
                    $temp_file = $dirs[$backup['dir_key']]['path'] . basename($backup['filename'], '.zip') . '.restore_tmp_' . date('YmdHis') . '_' . mt_rand(1000, 9999) . '.sql';
                    if (mits_cdb_restore_extract_zip_sql($backup['path'], $temp_dir, $temp_file, $messages)) {
                        $import_file = $temp_file;
                    } else {
                        $import_file = '';
                    }
                } elseif ($backup['compressed']) {
                    $temp_file = $dirs[$backup['dir_key']]['path'] . basename($backup['filename'], '.gz') . '.restore_tmp_' . date('YmdHis') . '_' . mt_rand(1000, 9999) . '.sql';
                    if (mits_cdb_restore_decompress_gz($backup['path'], $temp_file, $messages)) {
                        $messages[] = TEXT_MITS_CDB_RESTORE_GZ_UNPACKED;
                        $import_file = $temp_file;
                    } else {
                        $import_file = '';
                    }
                }

                if ($import_file != '') {
                    $command = 'mysql --binary-mode=1' . mits_cdb_restore_mysql_args() . ' < ' . escapeshellarg($import_file) . ' 2>&1';
                    $output = array();
                    $return = 1;
                    exec($command, $output, $return);

                    if ($return === 0) {
                        $messages[] = sprintf(TEXT_MITS_CDB_RESTORE_SUCCESS, $backup['filename']);
                        $success = true;
                    } else {
                        $messages[] = sprintf(TEXT_MITS_CDB_RESTORE_ERROR_IMPORT, $backup['filename']);
                        if (!empty($output)) {
                            $messages[] = implode("\n", $output);
                        }
                    }
                }
            }
        }
    } catch (Exception $e) {
        $messages[] = $e->getMessage();
    }

    if ($temp_file != '' && is_file($temp_file)) {
        @unlink($temp_file);
    }
    if ($temp_dir != '' && is_dir($temp_dir)) {
        mits_cdb_restore_delete_directory($temp_dir);
    }
    if (is_file($lock_file)) {
        @unlink($lock_file);
    }

    return $success;
}

$action = (isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : ''));
$dirs = mits_cdb_restore_backup_dirs();
$backups = mits_cdb_restore_collect_backups($dirs);
$selected_backup = false;
$messages = array();
$restore_done = false;
$restore_success = false;
$backup_done = false;
$backup_success = false;
$delete_done = false;
$delete_success = false;
$sql_done = false;
$sql_success = false;
$sql_results = array();
$sql_code = '';
$sql_row_limit = 100;
$selected_restore_table_file = '';

if ($action == 'download' && isset($_GET['backup'])) {
    $selected_backup = mits_cdb_restore_resolve_backup($_GET['backup'], $dirs);

    if (!mits_cdb_restore_check_token(isset($_GET['mits_restore_token']) ? $_GET['mits_restore_token'] : '')) {
        $messageStack->add(TEXT_MITS_CDB_RESTORE_ERROR_TOKEN, 'error');
        $action = '';
    } elseif ($selected_backup === false) {
        $messageStack->add(TEXT_MITS_CDB_RESTORE_ERROR_INVALID_FILE, 'error');
        $action = '';
    } elseif (!mits_cdb_restore_send_download($selected_backup)) {
        $messageStack->add(mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_ERROR_DOWNLOAD', 'Die Backup-Datei konnte nicht heruntergeladen werden.'), 'error');
        $action = '';
    }
}

if ($action == 'download_table' && isset($_GET['backup']) && isset($_GET['file'])) {
    $selected_backup = mits_cdb_restore_resolve_backup($_GET['backup'], $dirs);
    $table_file = ($selected_backup !== false) ? mits_cdb_restore_resolve_table_download($selected_backup, $_GET['file']) : false;

    if (!mits_cdb_restore_check_token(isset($_GET['mits_restore_token']) ? $_GET['mits_restore_token'] : '')) {
        $messageStack->add(TEXT_MITS_CDB_RESTORE_ERROR_TOKEN, 'error');
        $action = '';
    } elseif ($selected_backup === false || $table_file === false) {
        $messageStack->add(TEXT_MITS_CDB_RESTORE_ERROR_INVALID_FILE, 'error');
        $action = '';
    } elseif (!mits_cdb_restore_send_table_download($table_file)) {
        $messageStack->add(mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_ERROR_DOWNLOAD', 'Die Backup-Datei konnte nicht heruntergeladen werden.'), 'error');
        $action = '';
    }
}

if ($action == 'confirm' && isset($_GET['backup'])) {
    $selected_backup = mits_cdb_restore_resolve_backup($_GET['backup'], $dirs);
    if ($selected_backup === false) {
        $messageStack->add(TEXT_MITS_CDB_RESTORE_ERROR_INVALID_FILE, 'error');
        $action = '';
    } elseif (isset($_GET['file']) && $_GET['file'] != '') {
        $table_file = !empty($selected_backup['tables_dir']) ? mits_cdb_restore_resolve_table_download($selected_backup, $_GET['file']) : false;
        if ($table_file === false) {
            $messageStack->add(TEXT_MITS_CDB_RESTORE_ERROR_INVALID_FILE, 'error');
            $selected_backup = false;
            $action = '';
        } else {
            $selected_restore_table_file = $table_file['filename'];
        }
    }
}

if ($action == 'restore' && isset($_POST['backup'])) {
    $restore_done = true;
    $selected_backup = mits_cdb_restore_resolve_backup($_POST['backup'], $dirs);

    if (!mits_cdb_restore_check_admin_csrf()) {
        $messages[] = TEXT_MITS_CDB_RESTORE_ERROR_TOKEN;
    } elseif (!mits_cdb_restore_check_token(isset($_POST['mits_restore_token']) ? $_POST['mits_restore_token'] : '')) {
        $messages[] = TEXT_MITS_CDB_RESTORE_ERROR_TOKEN;
    } elseif ($selected_backup === false) {
        $messages[] = TEXT_MITS_CDB_RESTORE_ERROR_INVALID_FILE;
    } elseif (!isset($_POST['confirm_text']) || strtoupper(trim($_POST['confirm_text'])) !== 'RESTORE') {
        $messages[] = TEXT_MITS_CDB_RESTORE_ERROR_CONFIRM;
    } else {
        $selected_table_files = !empty($selected_backup['tables_dir']) ? mits_cdb_restore_selected_table_files($selected_backup, isset($_POST['restore_tables']) ? $_POST['restore_tables'] : array()) : array();
        $restore_success = mits_cdb_restore_run($selected_backup, $dirs, $messages, $selected_table_files);
    }

    mits_cdb_restore_write_log($messages);
    if ($restore_success) {
        mits_cdb_restore_render_relogin_page($messages);
    }
    mits_cdb_restore_rotate_token();
}

if ($action == 'backup') {
    $backup_done = true;

    if (!mits_cdb_restore_check_admin_csrf()) {
        $messages[] = TEXT_MITS_CDB_RESTORE_ERROR_TOKEN;
    } elseif (!mits_cdb_restore_check_token(isset($_POST['mits_restore_token']) ? $_POST['mits_restore_token'] : '')) {
        $messages[] = TEXT_MITS_CDB_RESTORE_ERROR_TOKEN;
    } elseif (!mits_cdb_restore_exec_enabled()) {
        $messages[] = TEXT_MITS_CDB_RESTORE_ERROR_EXEC_DISABLED;
    } else {
        $selected_tables = mits_cdb_restore_selected_tables(isset($_POST['backup_tables']) ? $_POST['backup_tables'] : array());
        $backup_mode = (isset($_POST['backup_mode']) && $_POST['backup_mode'] == 'tables') ? 'tables' : 'single';
        $backup_target_dir = DIR_FS_DOCUMENT_ROOT . 'export/mits_cron_database_backups';
        $backup_success = (mits_cdb_restore_create_manual_backup($backup_target_dir, $backup_mode, $selected_tables, $messages) !== false);
    }

    mits_cdb_restore_write_log($messages);
    mits_cdb_restore_rotate_token();
    $dirs = mits_cdb_restore_backup_dirs();
    $backups = mits_cdb_restore_collect_backups($dirs);
}

if ($action == 'delete') {
    $delete_done = true;
    $selected_backup = mits_cdb_restore_resolve_backup(isset($_POST['backup']) ? $_POST['backup'] : '', $dirs);

    if (!mits_cdb_restore_check_admin_csrf()) {
        $messages[] = TEXT_MITS_CDB_RESTORE_ERROR_TOKEN;
    } elseif (!mits_cdb_restore_check_token(isset($_POST['mits_restore_token']) ? $_POST['mits_restore_token'] : '')) {
        $messages[] = TEXT_MITS_CDB_RESTORE_ERROR_TOKEN;
    } elseif ($selected_backup === false) {
        $messages[] = TEXT_MITS_CDB_RESTORE_ERROR_INVALID_FILE;
    } else {
        $delete_success = mits_cdb_restore_delete_backup_item($selected_backup, $messages);
    }

    mits_cdb_restore_write_log($messages);
    mits_cdb_restore_rotate_token();
    $dirs = mits_cdb_restore_backup_dirs();
    $backups = mits_cdb_restore_collect_backups($dirs);
}

if ($action == 'delete_selected') {
    $delete_done = true;
    $selected_ids = array();
    $raw_selected = (isset($_POST['backups']) && is_array($_POST['backups'])) ? $_POST['backups'] : array();

    foreach ($raw_selected as $raw_id) {
        $raw_id = (string)$raw_id;
        if ($raw_id != '' && !in_array($raw_id, $selected_ids, true)) {
            $selected_ids[] = $raw_id;
        }
    }

    if (!mits_cdb_restore_check_admin_csrf()) {
        $messages[] = TEXT_MITS_CDB_RESTORE_ERROR_TOKEN;
    } elseif (!mits_cdb_restore_check_token(isset($_POST['mits_restore_token']) ? $_POST['mits_restore_token'] : '')) {
        $messages[] = TEXT_MITS_CDB_RESTORE_ERROR_TOKEN;
    } elseif (empty($selected_ids)) {
        $messages[] = mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_DELETE_SELECTED_NONE', 'Es wurden keine Sicherungen zum Loeschen ausgewaehlt.');
    } else {
        $deleted_count = 0;
        $failed_count = 0;
        foreach ($selected_ids as $selected_id) {
            $selected_backup = mits_cdb_restore_resolve_backup($selected_id, $dirs);
            if ($selected_backup === false) {
                $failed_count++;
                $messages[] = TEXT_MITS_CDB_RESTORE_ERROR_INVALID_FILE;
                continue;
            }
            if (mits_cdb_restore_delete_backup_item($selected_backup, $messages)) {
                $deleted_count++;
            } else {
                $failed_count++;
            }
        }
        if ($deleted_count > 0) {
            $messages[] = sprintf(mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_DELETE_SELECTED_SUMMARY', '%s Sicherung(en) geloescht, %s fehlgeschlagen.'), $deleted_count, $failed_count);
        }
        $delete_success = ($deleted_count > 0 && $failed_count == 0);
    }

    mits_cdb_restore_write_log($messages);
    mits_cdb_restore_rotate_token();
    $dirs = mits_cdb_restore_backup_dirs();
    $backups = mits_cdb_restore_collect_backups($dirs);
}

if ($action == 'delete_table') {
    $delete_done = true;
    $selected_backup = mits_cdb_restore_resolve_backup(isset($_POST['backup']) ? $_POST['backup'] : '', $dirs);

    if (!mits_cdb_restore_check_admin_csrf()) {
        $messages[] = TEXT_MITS_CDB_RESTORE_ERROR_TOKEN;
    } elseif (!mits_cdb_restore_check_token(isset($_POST['mits_restore_token']) ? $_POST['mits_restore_token'] : '')) {
        $messages[] = TEXT_MITS_CDB_RESTORE_ERROR_TOKEN;
    } elseif ($selected_backup === false || empty($selected_backup['tables_dir'])) {
        $messages[] = TEXT_MITS_CDB_RESTORE_ERROR_INVALID_FILE;
    } else {
        $delete_success = mits_cdb_restore_delete_table_file($selected_backup, isset($_POST['file']) ? $_POST['file'] : '', $messages);
    }

    mits_cdb_restore_write_log($messages);
    mits_cdb_restore_rotate_token();
    $dirs = mits_cdb_restore_backup_dirs();
    $backups = mits_cdb_restore_collect_backups($dirs);
}

if ($action == 'sql') {
    $sql_done = true;
    $sql_code = isset($_POST['sql_code']) ? (string)$_POST['sql_code'] : '';
    $sql_row_limit = mits_cdb_restore_sql_row_limit(isset($_POST['sql_row_limit']) ? $_POST['sql_row_limit'] : 100);
    $allow_write = (isset($_POST['sql_confirm_write']) && $_POST['sql_confirm_write'] == '1');

    if (!mits_cdb_restore_check_admin_csrf()) {
        $messages[] = TEXT_MITS_CDB_RESTORE_ERROR_TOKEN;
    } elseif (!mits_cdb_restore_check_token(isset($_POST['mits_restore_token']) ? $_POST['mits_restore_token'] : '')) {
        $messages[] = TEXT_MITS_CDB_RESTORE_ERROR_TOKEN;
    } else {
        $sql_success = mits_cdb_restore_run_sql_box($sql_code, $allow_write, $sql_row_limit, $messages, $sql_results);
    }

    mits_cdb_restore_write_log($messages);
    mits_cdb_restore_rotate_token();
}

$available_tables = mits_cdb_restore_get_tables();

$backup_count = count($backups);
$total_backup_size = 0;
$compressed_count = 0;
$archive_count = 0;
$latest_backup_text = '-';
foreach ($backups as $backup_stat) {
    $total_backup_size += (int)$backup_stat['size'];
    if (!empty($backup_stat['compressed'])) {
        $compressed_count++;
    }
    if (!empty($backup_stat['archive'])) {
        $archive_count++;
    }
}
if (!empty($backups)) {
    $latest_backup_text = date('d.m.Y H:i', $backups[0]['mtime']);
}
$exec_enabled = mits_cdb_restore_exec_enabled();

require(DIR_WS_INCLUDES . 'head.php');
?>
<style>
.mits-admin{--mits-ci-primary:#6a9;--mits-ci-primary-dark:#4f8e7e;--mits-ci-primary-soft:#edf7f4;--mits-ci-primary-soft-2:#f7fbfa;--mits-ci-line:#d4e7e0;--mits-ci-line-strong:#b9d6cb;--mits-ci-ink:#444;--mits-ci-heading:#30534b;--mits-ci-muted:#6d7b77;--mits-ci-shadow:rgba(76,110,101,.10);--mits-ci-danger-bg:#fdeeed;--mits-ci-danger-text:#a3483f;--mits-ci-warning-bg:#fff8e5;--mits-ci-warning-line:#f0d28a;--mits-ci-warning-text:#7a5a00;--mits-ci-success-bg:#e9f7f1;--mits-ci-success-text:#2c715d;padding:18px 18px 28px;color:var(--mits-ci-ink);font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:1.45}
.mits-admin *{box-sizing:border-box}
.mits-admin input,.mits-admin select,.mits-admin textarea,.mits-admin button{font-family:Arial,Helvetica,sans-serif}
.mits-admin__hero{display:flex;justify-content:space-between;gap:18px;align-items:flex-start;padding:24px;border:1px solid var(--mits-ci-line);border-radius:20px;background:linear-gradient(135deg,#ffffff 0%,var(--mits-ci-primary-soft) 100%);box-shadow:0 10px 25px var(--mits-ci-shadow);margin-bottom:18px}
.mits-admin__hero h1{margin:0 0 7px;font-size:26px;line-height:1.2;color:var(--mits-ci-heading)}
.mits-admin__hero p{margin:0;color:var(--mits-ci-muted);max-width:820px;line-height:1.55}
.mits-admin__hero-actions{display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end}
.mits-admin__stats{display:grid;grid-template-columns:repeat(4,minmax(170px,1fr));gap:14px;margin-bottom:18px}
.mits-stat{padding:18px;border-radius:18px;border:1px solid var(--mits-ci-line);background:#fff;box-shadow:0 8px 22px var(--mits-ci-shadow)}
.mits-stat__label{display:block;font-size:12px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:var(--mits-ci-muted);margin-bottom:8px}
.mits-stat__value{display:block;font-size:25px;font-weight:700;color:var(--mits-ci-heading);line-height:1.1;word-break:break-word}
.mits-stat__meta{display:block;margin-top:8px;color:var(--mits-ci-muted);font-size:12px}
.mits-admin__layout{display:grid;grid-template-columns:minmax(0,1fr) 360px;gap:18px;align-items:start}
.mits-admin__stack{display:grid;gap:18px}
.mits-card{background:#fff;border:1px solid var(--mits-ci-line);border-radius:20px;box-shadow:0 10px 24px var(--mits-ci-shadow);overflow:hidden}
.mits-card__header{padding:18px 22px;border-bottom:1px solid var(--mits-ci-line);background:var(--mits-ci-primary-soft-2)}
.mits-card__title{margin:0;font-size:18px;color:var(--mits-ci-heading)}
.mits-card__subtitle{margin:6px 0 0;color:var(--mits-ci-muted);font-size:13px;line-height:1.5}
.mits-card__body{padding:20px 22px}
.mits-alert{padding:15px 16px;border-radius:16px;border:1px solid var(--mits-ci-line);background:#fff;margin-bottom:16px;line-height:1.55}
.mits-alert strong{display:block;margin-bottom:4px;color:var(--mits-ci-heading)}
.mits-alert--warning{border-color:var(--mits-ci-warning-line);background:var(--mits-ci-warning-bg);color:var(--mits-ci-warning-text)}
.mits-alert--danger{border-color:#f1cec9;background:var(--mits-ci-danger-bg);color:var(--mits-ci-danger-text)}
.mits-alert--success{border-color:#c7dfd6;background:var(--mits-ci-success-bg);color:var(--mits-ci-success-text)}
.mits-table-wrap{overflow:auto;border:1px solid var(--mits-ci-line);border-radius:16px;background:#fff}
.mits-table{width:100%;border-collapse:separate;border-spacing:0;min-width:760px}
.mits-table th,.mits-table td{padding:13px 14px;border-bottom:1px solid var(--mits-ci-line);text-align:left;vertical-align:middle}
.mits-table-select{width:46px;text-align:center !important}
.mits-table-select input{margin:0}
.mits-table th{background:#f7fbfa;color:var(--mits-ci-muted);font-size:12px;text-transform:uppercase;letter-spacing:.04em}
.mits-table tbody tr:hover td{background:#fcfefd}
.mits-table tbody tr:last-child td{border-bottom:0}
.mits-file{display:flex;align-items:center;gap:10px;min-width:260px}
.mits-file__icon{display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:12px;border:1px solid var(--mits-ci-line);background:var(--mits-ci-primary-soft);color:var(--mits-ci-heading);font-weight:800;font-size:12px;flex:0 0 auto}
.mits-file__name{font-weight:700;color:var(--mits-ci-heading);word-break:break-all}
.mits-file__meta{display:block;margin-top:3px;color:var(--mits-ci-muted);font-size:12px}
.mits-badge{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;font-size:12px;font-weight:700;background:#eef4f2;color:#536661;white-space:nowrap}
.mits-badge--success{background:var(--mits-ci-success-bg);color:var(--mits-ci-success-text)}
.mits-badge--danger{background:var(--mits-ci-danger-bg);color:var(--mits-ci-danger-text)}
.mits-badge--warning{background:var(--mits-ci-warning-bg);color:var(--mits-ci-warning-text)}
.mits-button,.mits-button:link,.mits-button:visited{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:40px;padding:0 16px;border-radius:12px;border:1px solid var(--mits-ci-line-strong);background:#fff;color:var(--mits-ci-heading);font-weight:700;text-decoration:none;cursor:pointer;transition:all .15s ease;font-family:inherit;font-size:13px}
.mits-button:hover,.mits-button:focus{border-color:var(--mits-ci-primary-dark);background:var(--mits-ci-primary-soft);color:var(--mits-ci-heading) !important;text-decoration:none !important}
.mits-button.mits-button--primary,.mits-button.mits-button--primary:link,.mits-button.mits-button--primary:visited{background:var(--mits-ci-primary-dark);border-color:var(--mits-ci-primary-dark);color:#fff !important}
.mits-button.mits-button--primary:hover,.mits-button.mits-button--primary:focus{background:#467d70;border-color:#467d70;color:#fff !important}
.mits-button.mits-button--soft,.mits-button.mits-button--soft:link,.mits-button.mits-button--soft:visited{background:var(--mits-ci-primary-soft);border-color:var(--mits-ci-line);color:var(--mits-ci-heading) !important}
.mits-button.mits-button--soft:hover,.mits-button.mits-button--soft:focus{background:#e5f4ef;border-color:var(--mits-ci-line-strong);color:var(--mits-ci-heading) !important}
.mits-button.mits-button--danger,.mits-button.mits-button--danger:link,.mits-button.mits-button--danger:visited{background:var(--mits-ci-danger-bg);border-color:#f1cec9;color:var(--mits-ci-danger-text) !important}
.mits-button.mits-button--danger:hover,.mits-button.mits-button--danger:focus{background:#f9d9d5;border-color:#e2aaa3;color:var(--mits-ci-danger-text) !important;text-decoration:none !important}
.mits-button--icon{width:40px;min-width:40px;padding:0;font-size:17px;line-height:1}
.mits-button--icon-sm{width:32px;min-width:32px;min-height:30px;padding:0;font-size:15px;line-height:1}
.mits-inline-form{display:inline-flex;margin:0}
.mits-bulk-actions{display:flex;align-items:center;gap:10px;justify-content:space-between;margin-top:12px;flex-wrap:wrap}
.mits-actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center;justify-content:flex-end}
.mits-subtle{color:var(--mits-ci-muted);font-size:12px;line-height:1.5}
.mits-detail-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-bottom:16px}
.mits-detail{padding:14px;border:1px solid var(--mits-ci-line);border-radius:16px;background:#fcfefd}
.mits-detail__label{display:block;margin-bottom:6px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--mits-ci-muted)}
.mits-detail__value{display:block;font-weight:700;color:var(--mits-ci-heading);word-break:break-all}
.mits-confirm-input{width:260px;max-width:100%;padding:11px 13px;border:1px solid var(--mits-ci-line-strong);border-radius:12px;background:#fff;color:var(--mits-ci-ink);font-weight:700;letter-spacing:.04em}
.mits-confirm-input:focus{border-color:var(--mits-ci-primary-dark);box-shadow:0 0 0 3px rgba(102,170,153,.16);outline:none}
.mits-log{white-space:pre-wrap;padding:14px 16px;border:1px solid var(--mits-ci-line);border-radius:14px;background:#fcfefd;max-height:280px;overflow:auto;text-align:left;color:#444;margin-top:12px}
.mits-steps{display:grid;gap:10px;margin:0;padding:0;list-style:none}
.mits-steps li{display:flex;gap:10px;align-items:flex-start;padding:12px;border:1px solid var(--mits-ci-line);border-radius:14px;background:#fff}
.mits-steps span{display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:999px;background:var(--mits-ci-primary-soft);color:var(--mits-ci-heading);font-weight:800;flex:0 0 auto}
.mits-empty{padding:24px;border:1px dashed var(--mits-ci-line-strong);border-radius:16px;background:#fcfefd;text-align:center;color:var(--mits-ci-muted)}
.mits-check-toolbar{display:flex;align-items:center;justify-content:space-between;gap:10px;margin:10px 0 8px;flex-wrap:wrap}
.mits-check-list{max-height:320px;overflow:auto;border:1px solid var(--mits-ci-line);border-radius:14px;background:#fff;margin:12px 0;padding:8px}
.mits-check-row{display:flex;align-items:center;gap:8px;padding:7px 8px;border-radius:10px;color:var(--mits-ci-heading);word-break:break-all}
.mits-check-row:hover{background:var(--mits-ci-primary-soft-2)}
.mits-check-row input{margin:0;flex:0 0 auto}
.mits-table-files{border:1px solid var(--mits-ci-line);border-radius:14px;background:#fcfefd;overflow:hidden}
.mits-table-files summary{display:flex;align-items:center;gap:10px;padding:12px 14px;cursor:pointer;font-weight:700;color:var(--mits-ci-heading);background:var(--mits-ci-primary-soft-2);list-style:none}
.mits-table-files summary::-webkit-details-marker{display:none}
.mits-table-files summary:before{content:'+';display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;border-radius:999px;background:var(--mits-ci-primary-soft);border:1px solid var(--mits-ci-line);font-weight:800;color:var(--mits-ci-heading);flex:0 0 auto}
.mits-table-files[open] summary:before{content:'-'}
.mits-table-files__body{padding:10px 12px 12px}
.mits-table-files__row{display:flex;align-items:center;gap:10px;padding:8px 6px;border-bottom:1px solid var(--mits-ci-line);word-break:break-all}
.mits-table-files__row:last-child{border-bottom:0}
.mits-table-files__name{font-weight:700;color:var(--mits-ci-heading)}
.mits-table-files__meta{margin-left:auto;color:var(--mits-ci-muted);font-size:12px;white-space:nowrap}
.mits-table-files__download{margin-left:8px;min-height:30px;padding:0 10px;white-space:nowrap}
.mits-table-files__actions{display:flex;gap:6px;align-items:center;margin-left:8px;flex:0 0 auto}
.mits-field{margin:0 0 12px}
.mits-field label{display:block;margin:0 0 6px;font-weight:700;color:var(--mits-ci-heading)}
.mits-field select{width:100%;padding:10px 12px;border:1px solid var(--mits-ci-line-strong);border-radius:12px;background:#fff;color:var(--mits-ci-ink)}
.mits-sql-card{overflow:hidden}
.mits-sql-card>summary{display:block;cursor:pointer;list-style:none;position:relative;padding-right:52px}
.mits-sql-card>summary::-webkit-details-marker{display:none}
.mits-sql-card>summary:after{content:'+';position:absolute;right:18px;top:50%;transform:translateY(-50%);display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;border-radius:999px;background:var(--mits-ci-primary-soft);border:1px solid var(--mits-ci-line);font-weight:800;color:var(--mits-ci-heading)}
.mits-sql-card[open]>summary:after{content:'-'}
.mits-sql-textarea{width:100%;min-height:180px;resize:vertical;padding:12px 13px;border:1px solid var(--mits-ci-line-strong);border-radius:12px;background:#fff;color:var(--mits-ci-ink);font-family:Consolas,Monaco,monospace !important;font-size:12px;line-height:1.45}
.mits-sql-textarea:focus{border-color:var(--mits-ci-primary-dark);box-shadow:0 0 0 3px rgba(102,170,153,.16);outline:none}
.mits-sql-option{display:flex;align-items:flex-start;gap:8px;margin:10px 0;color:var(--mits-ci-heading);line-height:1.45}
.mits-sql-option input{margin-top:2px;flex:0 0 auto}
.mits-sql-result{border:1px solid var(--mits-ci-line);border-radius:16px;background:#fcfefd;margin:14px 0 0;overflow:hidden}
.mits-sql-result__head{padding:12px 14px;border-bottom:1px solid var(--mits-ci-line);background:var(--mits-ci-primary-soft-2)}
.mits-sql-result__title{font-weight:700;color:var(--mits-ci-heading);margin:0 0 5px}
.mits-sql-result__statement{white-space:pre-wrap;font-family:Consolas,Monaco,monospace;font-size:12px;line-height:1.45;color:#445;margin:8px 0 0;padding:10px;border:1px solid var(--mits-ci-line);border-radius:10px;background:#fff;max-height:140px;overflow:auto}
.mits-sql-result__body{padding:12px 14px;overflow:auto}
.mits-sql-result-table{width:100%;border-collapse:collapse;min-width:520px;background:#fff}
.mits-sql-result-table th,.mits-sql-result-table td{padding:8px 10px;border:1px solid var(--mits-ci-line);text-align:left;vertical-align:top;font-size:12px;max-width:340px;word-break:break-word}
.mits-sql-result-table th{background:#f7fbfa;color:var(--mits-ci-muted);font-weight:700}
@media (max-width:1100px){.mits-admin__stats{grid-template-columns:repeat(2,minmax(170px,1fr))}.mits-admin__layout{grid-template-columns:1fr}.mits-admin__hero{display:block}.mits-admin__hero-actions{justify-content:flex-start;margin-top:14px}}
@media (max-width:640px){.mits-admin{padding:12px}.mits-admin__stats,.mits-detail-grid{grid-template-columns:1fr}.mits-card__body,.mits-card__header,.mits-admin__hero{padding:16px}.mits-table{min-width:680px}}
</style>
</head>
<body>
<!-- header //--> 
<?php require(DIR_WS_INCLUDES . 'header.php'); ?>
<!-- header_eof //--> 

<div class="mits-admin">
  <div class="mits-admin__hero">
    <div>
      <h1><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_PAGE_TITLE', 'MITS Cron Database Backups - Datenbank-Werkzeuge'); ?></h1>
      <p><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_HERO_TEXT', TEXT_MITS_CDB_RESTORE_INTRO); ?></p>
    </div>
    <div class="mits-admin__hero-actions">
      <a class="mits-button mits-button--soft" href="<?php echo xtc_href_link((defined('FILENAME_MODULE_EXPORT') ? FILENAME_MODULE_EXPORT : 'modules.php'), 'set=system&module=mits_cron_database_backups'); ?>"><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_MODULE_SETTINGS', 'Moduleinstellungen'); ?></a>
      <a class="mits-button mits-button--soft" href="<?php echo xtc_href_link('mits_cron_database_restore.php'); ?>"><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_REFRESH', 'Liste aktualisieren'); ?></a>
    </div>
  </div>

  <div class="mits-admin__stats">
    <div class="mits-stat">
      <span class="mits-stat__label"><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_STAT_BACKUPS', 'Backups'); ?></span>
      <span class="mits-stat__value"><?php echo (int)$backup_count; ?></span>
      <span class="mits-stat__meta"><?php echo sprintf(mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_STAT_COMPRESSED', '%s komprimiert'), (int)$compressed_count); ?></span>
    </div>
    <div class="mits-stat">
      <span class="mits-stat__label"><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_STAT_TOTAL_SIZE', 'Gesamtgr&ouml;&szlig;e'); ?></span>
      <span class="mits-stat__value"><?php echo mits_cdb_restore_format_filesize($total_backup_size); ?></span>
      <span class="mits-stat__meta"><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_STAT_TOTAL_SIZE_META', 'alle gefundenen Dateien'); ?></span>
    </div>
    <div class="mits-stat">
      <span class="mits-stat__label"><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_STAT_LATEST', 'Letztes Backup'); ?></span>
      <span class="mits-stat__value"><?php echo mits_cdb_restore_html($latest_backup_text); ?></span>
      <span class="mits-stat__meta"><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_STAT_LATEST_META', 'neueste Datei zuerst'); ?></span>
    </div>
    <div class="mits-stat">
      <span class="mits-stat__label"><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_STAT_SYSTEM', 'Systemstatus'); ?></span>
      <span class="mits-stat__value"><span class="mits-badge <?php echo $exec_enabled ? 'mits-badge--success' : 'mits-badge--danger'; ?>"><?php echo $exec_enabled ? mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_EXEC_AVAILABLE', 'mysql bereit') : mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_EXEC_NOT_AVAILABLE', 'exec deaktiviert'); ?></span></span>
      <span class="mits-stat__meta"><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_STAT_SYSTEM_META', 'Import per Server-Client'); ?></span>
    </div>
  </div>

  <?php if ($sql_done) { ?>
    <div class="mits-card" style="margin-bottom:18px;">
      <div class="mits-card__header">
        <h2 class="mits-card__title"><?php echo $sql_success ? mits_cdb_restore_text('TEXT_MITS_CDB_SQL_RESULT_SUCCESS', 'SQL ausgef&uuml;hrt') : mits_cdb_restore_text('TEXT_MITS_CDB_SQL_RESULT_ERROR', 'SQL fehlgeschlagen'); ?></h2>
        <p class="mits-card__subtitle"><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_SQL_RESULT_SUBTITLE', 'Das Ergebnis der SQL-Ausf&uuml;hrung wurde protokolliert.'); ?></p>
      </div>
      <div class="mits-card__body">
        <div class="mits-alert <?php echo ($sql_success ? 'mits-alert--success' : 'mits-alert--danger'); ?>">
          <strong><?php echo $sql_success ? mits_cdb_restore_text('TEXT_MITS_CDB_SQL_RESULT_SUCCESS', 'SQL ausgef&uuml;hrt') : mits_cdb_restore_text('TEXT_MITS_CDB_SQL_RESULT_ERROR', 'SQL fehlgeschlagen'); ?></strong>
          <?php if (!empty($messages)) { ?>
            <div class="mits-log"><?php echo mits_cdb_restore_plain_html(implode("\n", $messages)); ?></div>
          <?php } ?>
        </div>

        <?php if (!empty($sql_results)) { ?>
          <?php foreach ($sql_results as $sql_result) { ?>
            <div class="mits-sql-result">
              <div class="mits-sql-result__head">
                <div class="mits-sql-result__title">
                  <?php echo sprintf(mits_cdb_restore_text('TEXT_MITS_CDB_SQL_RESULT_STATEMENT_TITLE', 'SQL #%s'), (int)$sql_result['number']); ?>
                  <span class="mits-badge <?php echo !empty($sql_result['success']) ? 'mits-badge--success' : 'mits-badge--danger'; ?>"><?php echo !empty($sql_result['success']) ? mits_cdb_restore_text('TEXT_MITS_CDB_SQL_STATUS_OK', 'OK') : mits_cdb_restore_text('TEXT_MITS_CDB_SQL_STATUS_ERROR', 'Fehler'); ?></span>
                </div>
                <div class="mits-subtle"><?php echo mits_cdb_restore_plain_html($sql_result['message']); ?></div>
                <div class="mits-sql-result__statement"><?php echo mits_cdb_restore_plain_html($sql_result['statement']); ?></div>
              </div>
              <?php if (!empty($sql_result['fields'])) { ?>
                <div class="mits-sql-result__body">
                  <?php if (!empty($sql_result['rows'])) { ?>
                    <table class="mits-sql-result-table">
                      <thead>
                        <tr>
                          <?php foreach ($sql_result['fields'] as $field) { ?>
                            <th><?php echo mits_cdb_restore_html($field); ?></th>
                          <?php } ?>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($sql_result['rows'] as $row) { ?>
                          <tr>
                            <?php foreach ($sql_result['fields'] as $field) { ?>
                              <td><?php echo mits_cdb_restore_plain_html(isset($row[$field]) ? $row[$field] : ''); ?></td>
                            <?php } ?>
                          </tr>
                        <?php } ?>
                      </tbody>
                    </table>
                  <?php } else { ?>
                    <div class="mits-empty"><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_SQL_RESULT_EMPTY', 'Die Abfrage lieferte keine Datens&auml;tze.'); ?></div>
                  <?php } ?>
                </div>
              <?php } ?>
            </div>
          <?php } ?>
        <?php } ?>
      </div>
    </div>
  <?php } ?>

  <?php if ($delete_done) { ?>
    <div class="mits-card" style="margin-bottom:18px;">
      <div class="mits-card__header">
        <h2 class="mits-card__title"><?php echo $delete_success ? mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_DELETE_RESULT_SUCCESS', 'Backup gel&ouml;scht') : mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_DELETE_RESULT_ERROR', 'L&ouml;schen fehlgeschlagen'); ?></h2>
        <p class="mits-card__subtitle"><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_DELETE_RESULT_SUBTITLE', 'Das Ergebnis der L&ouml;schaktion wurde protokolliert.'); ?></p>
      </div>
      <div class="mits-card__body">
        <div class="mits-alert <?php echo ($delete_success ? 'mits-alert--success' : 'mits-alert--danger'); ?>">
          <strong><?php echo $delete_success ? mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_DELETE_RESULT_SUCCESS', 'Backup gel&ouml;scht') : mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_DELETE_RESULT_ERROR', 'L&ouml;schen fehlgeschlagen'); ?></strong>
          <?php if (!empty($messages)) { ?>
            <div class="mits-log"><?php echo mits_cdb_restore_plain_html(implode("\n", $messages)); ?></div>
          <?php } ?>
        </div>
      </div>
    </div>
  <?php } ?>

  <?php if ($backup_done) { ?>
    <div class="mits-card">
      <div class="mits-card__header">
        <h2 class="mits-card__title"><?php echo $backup_success ? mits_cdb_restore_text('TEXT_MITS_CDB_BACKUP_RESULT_SUCCESS', 'Backup erstellt') : mits_cdb_restore_text('TEXT_MITS_CDB_BACKUP_RESULT_ERROR', 'Backup fehlgeschlagen'); ?></h2>
        <p class="mits-card__subtitle"><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_BACKUP_RESULT_SUBTITLE', 'Das Ergebnis der manuellen Sicherung wurde protokolliert.'); ?></p>
      </div>
      <div class="mits-card__body">
        <div class="mits-alert <?php echo ($backup_success ? 'mits-alert--success' : 'mits-alert--danger'); ?>">
          <strong><?php echo $backup_success ? mits_cdb_restore_text('TEXT_MITS_CDB_BACKUP_RESULT_SUCCESS', 'Backup erstellt') : mits_cdb_restore_text('TEXT_MITS_CDB_BACKUP_RESULT_ERROR', 'Backup fehlgeschlagen'); ?></strong>
          <?php if (!empty($messages)) { ?>
            <div class="mits-log"><?php echo mits_cdb_restore_plain_html(implode("
", $messages)); ?></div>
          <?php } ?>
        </div>
        <a class="mits-button mits-button--primary" href="<?php echo xtc_href_link('mits_cron_database_restore.php'); ?>"><?php echo BUTTON_BACK; ?></a>
      </div>
    </div>
  <?php } elseif ($restore_done) { ?>
    <div class="mits-card">
      <div class="mits-card__header">
        <h2 class="mits-card__title"><?php echo ($restore_success ? TEXT_MITS_CDB_RESTORE_RESULT_SUCCESS : TEXT_MITS_CDB_RESTORE_RESULT_ERROR); ?></h2>
        <p class="mits-card__subtitle"><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_RESULT_SUBTITLE', 'Das Ergebnis der R&uuml;cksicherung wurde protokolliert.'); ?></p>
      </div>
      <div class="mits-card__body">
        <div class="mits-alert <?php echo ($restore_success ? 'mits-alert--success' : 'mits-alert--danger'); ?>">
          <strong><?php echo ($restore_success ? TEXT_MITS_CDB_RESTORE_RESULT_SUCCESS : TEXT_MITS_CDB_RESTORE_RESULT_ERROR); ?></strong>
          <?php if (!empty($messages)) { ?>
            <div class="mits-log"><?php echo mits_cdb_restore_plain_html(implode("\n", $messages)); ?></div>
          <?php } ?>
        </div>
        <a class="mits-button mits-button--primary" href="<?php echo xtc_href_link('mits_cron_database_restore.php'); ?>"><?php echo BUTTON_BACK; ?></a>
      </div>
    </div>
  <?php } elseif ($action == 'confirm' && is_array($selected_backup)) { ?>
    <div class="mits-admin__layout">
      <div class="mits-card">
        <div class="mits-card__header">
          <h2 class="mits-card__title"><?php echo TEXT_MITS_CDB_RESTORE_CONFIRM_TITLE; ?></h2>
          <p class="mits-card__subtitle"><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_CONFIRM_SUBTITLE', 'Bitte pr&uuml;fen Sie die Datei und best&auml;tigen Sie die R&uuml;cksicherung bewusst.'); ?></p>
        </div>
        <div class="mits-card__body">
          <div class="mits-detail-grid">
            <div class="mits-detail">
              <span class="mits-detail__label"><?php echo TEXT_MITS_CDB_RESTORE_FILE; ?></span>
              <span class="mits-detail__value"><?php echo mits_cdb_restore_html($selected_backup['filename']); ?></span>
            </div>
            <div class="mits-detail">
              <span class="mits-detail__label"><?php echo TEXT_MITS_CDB_RESTORE_DIRECTORY; ?></span>
              <span class="mits-detail__value"><?php echo mits_cdb_restore_html($selected_backup['directory']); ?></span>
            </div>
            <div class="mits-detail">
              <span class="mits-detail__label"><?php echo TEXT_MITS_CDB_RESTORE_SIZE; ?></span>
              <span class="mits-detail__value"><?php echo mits_cdb_restore_format_filesize($selected_backup['size']); ?></span>
            </div>
            <div class="mits-detail">
              <span class="mits-detail__label"><?php echo TEXT_MITS_CDB_RESTORE_DATE; ?></span>
              <span class="mits-detail__value"><?php echo date('d.m.Y H:i:s', $selected_backup['mtime']); ?></span>
            </div>
          </div>

          <div class="mits-alert mits-alert--warning">
            <strong><?php echo TEXT_MITS_CDB_RESTORE_WARNING_TITLE; ?></strong>
            <?php echo TEXT_MITS_CDB_RESTORE_CONFIRM_WARNING; ?>
          </div>

          <?php echo xtc_draw_form('mits_cdb_restore', 'mits_cron_database_restore.php', '', 'post'); ?>
            <?php echo mits_cdb_restore_csrf_hidden_fields(); ?>
            <?php echo xtc_draw_hidden_field('action', 'restore'); ?>
            <?php echo xtc_draw_hidden_field('backup', $selected_backup['id']); ?>
            <?php if (!empty($selected_backup['tables_dir'])) { $restore_table_files = mits_cdb_restore_collect_table_files($selected_backup['path']); ?>
              <div class="mits-alert mits-alert--success">
                <strong><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_TABLE_SELECTION_TITLE', 'Tabellen f&uuml;r die R&uuml;cksicherung ausw&auml;hlen'); ?></strong>
                <?php echo mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_TABLE_SELECTION_INFO', 'Bei einem Tabellen-Backup k&ouml;nnen einzelne Tabellendateien gezielt ausgew&auml;hlt oder heruntergeladen werden.'); ?>
                <?php if ($selected_restore_table_file != '') { ?><br /><?php echo sprintf(mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_TABLE_PRESELECTED_INFO', 'Vorausgew&auml;hlt ist nur die Tabellendatei: %s'), mits_cdb_restore_html(mits_cdb_restore_table_file_label($selected_restore_table_file))); ?><?php } ?>
              </div>
              <div class="mits-check-list">
                <?php foreach ($restore_table_files as $table_file) { ?>
                  <label class="mits-check-row">
                    <input type="checkbox" name="restore_tables[]" value="<?php echo mits_cdb_restore_html($table_file['filename']); ?>"<?php echo ($selected_restore_table_file == '' || $selected_restore_table_file == $table_file['filename']) ? ' checked="checked"' : ''; ?> />
                    <span><?php echo mits_cdb_restore_html(mits_cdb_restore_table_file_label($table_file['filename'])); ?> <span class="mits-subtle">(<?php echo mits_cdb_restore_format_filesize($table_file['size']); ?>)</span></span>
                    <a class="mits-button mits-button--soft" style="margin-left:auto;min-height:30px;padding:0 10px;" href="<?php echo xtc_href_link('mits_cron_database_restore.php', 'action=download_table&backup=' . rawurlencode($selected_backup['id']) . '&file=' . rawurlencode($table_file['filename']) . '&mits_restore_token=' . rawurlencode(mits_cdb_restore_ensure_token())); ?>"><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_DOWNLOAD', 'Download'); ?></a>
                  </label>
                <?php } ?>
              </div>
            <?php } ?>
            <p class="mits-subtle"><?php echo TEXT_MITS_CDB_RESTORE_CONFIRM_INPUT; ?></p>
            <p><input class="mits-confirm-input" type="text" name="confirm_text" value="" autocomplete="off" /></p>
            <div class="mits-actions" style="justify-content:flex-start;">
              <button type="submit" class="mits-button mits-button--danger"><?php echo BUTTON_RESTORE; ?></button>
              <a class="mits-button" href="<?php echo xtc_href_link('mits_cron_database_restore.php'); ?>"><?php echo BUTTON_CANCEL; ?></a>
            </div>
          </form>
        </div>
      </div>

      <div class="mits-card">
        <div class="mits-card__header">
          <h2 class="mits-card__title"><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_PROCESS_TITLE', 'Ablauf der R&uuml;cksicherung'); ?></h2>
          <p class="mits-card__subtitle"><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_PROCESS_SUBTITLE', 'Diese Sicherheitsstufen werden vor und w&auml;hrend des Imports ausgef&uuml;hrt.'); ?></p>
        </div>
        <div class="mits-card__body">
          <ol class="mits-steps">
            <li><span>1</span><div><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_STEP_BACKUP', 'Aktuelle Datenbank wird als Sicherheitsbackup gesichert.'); ?></div></li>
            <li><span>2</span><div><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_STEP_UNPACK', 'SQL.GZ-Dateien und Tabellen-Backups werden bei Bedarf tempor&auml;r entpackt.'); ?></div></li>
            <li><span>3</span><div><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_STEP_IMPORT', 'Der Import erfolgt per mysql-Client des Servers.'); ?></div></li>
            <li><span>4</span><div><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_STEP_RELOGIN', 'Nach erfolgreicher R&uuml;cksicherung wird die Admin-Session beendet.'); ?></div></li>
          </ol>
        </div>
      </div>
    </div>
  <?php } else { ?>
    <div class="mits-admin__layout">
      <div class="mits-card">
        <div class="mits-card__header">
          <h2 class="mits-card__title"><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_CARD_BACKUPS', 'Verf&uuml;gbare Backups'); ?></h2>
          <p class="mits-card__subtitle"><?php echo TEXT_MITS_CDB_RESTORE_INTRO; ?></p>
        </div>
        <div class="mits-card__body">
          <?php if (!$exec_enabled) { ?>
            <div class="mits-alert mits-alert--danger"><strong><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_EXEC_NOT_AVAILABLE', 'exec deaktiviert'); ?></strong><?php echo TEXT_MITS_CDB_RESTORE_ERROR_EXEC_DISABLED; ?></div>
          <?php } ?>

          <?php if (empty($backups)) { ?>
            <div class="mits-empty"><?php echo TEXT_MITS_CDB_RESTORE_NO_BACKUPS; ?></div>
          <?php } else { ?>
            <div class="mits-table-wrap">
              <table class="mits-table">
                <thead>
                  <tr>
                    <th class="mits-table-select"><input type="checkbox" id="mits-cdb-select-all" title="<?php echo mits_cdb_restore_html(mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_SELECT_ALL', 'Alle ausw&auml;hlen / abw&auml;hlen')); ?>" aria-label="<?php echo mits_cdb_restore_html(mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_SELECT_ALL', 'Alle ausw&auml;hlen / abw&auml;hlen')); ?>" /></th>
                    <th><?php echo TEXT_MITS_CDB_RESTORE_FILE; ?></th>
                    <th><?php echo TEXT_MITS_CDB_RESTORE_DIRECTORY; ?></th>
                    <th><?php echo TEXT_MITS_CDB_RESTORE_SIZE; ?></th>
                    <th><?php echo TEXT_MITS_CDB_RESTORE_DATE; ?></th>
                    <th><?php echo TEXT_MITS_CDB_RESTORE_TYPE; ?></th>
                    <th>&nbsp;</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($backups as $backup) { ?>
                    <tr>
                      <td class="mits-table-select"><input type="checkbox" class="mits-backup-select" form="mits_cdb_bulk_delete_form" name="backups[]" value="<?php echo mits_cdb_restore_html($backup['id']); ?>" aria-label="<?php echo mits_cdb_restore_html(sprintf(mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_SELECT_BACKUP', 'Sicherung ausw&auml;hlen: %s'), $backup['filename'])); ?>" /></td>
                      <td>
                        <div class="mits-file">
                          <span class="mits-file__icon"><?php echo (!empty($backup['tables_dir']) ? 'TAB' : (!empty($backup['archive']) ? 'ZIP' : 'SQL')); ?></span>
                          <div>
                            <span class="mits-file__name"><?php echo mits_cdb_restore_html($backup['filename']); ?></span>
                            <span class="mits-file__meta"><?php echo !empty($backup['tables_dir']) ? sprintf(mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_TABLE_COUNT', '%s Tabellen'), (int)$backup['table_count']) : mits_cdb_restore_html($backup['id']); ?></span>
                          </div>
                        </div>
                      </td>
                      <td><?php echo mits_cdb_restore_html($backup['directory']); ?></td>
                      <td><?php echo mits_cdb_restore_format_filesize($backup['size']); ?></td>
                      <td><?php echo date('d.m.Y H:i:s', $backup['mtime']); ?></td>
                      <td><span class="mits-badge <?php echo (!empty($backup['compressed']) || !empty($backup['archive']) || !empty($backup['tables_dir']) ? 'mits-badge--success' : ''); ?>"><?php echo (!empty($backup['tables_dir']) ? sprintf(mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_TYPE_TABLES', 'Tabellen (%s)'), (int)$backup['table_count']) : (!empty($backup['archive']) ? 'ZIP' : ($backup['compressed'] ? 'SQL.GZ' : 'SQL'))); ?></span></td>
                      <td>
                        <div class="mits-actions">
                          <?php if (empty($backup['tables_dir'])) { ?>
                            <a class="mits-button mits-button--soft" href="<?php echo xtc_href_link('mits_cron_database_restore.php', 'action=download&backup=' . rawurlencode($backup['id']) . '&mits_restore_token=' . rawurlencode(mits_cdb_restore_ensure_token())); ?>"><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_DOWNLOAD', 'Download'); ?></a>
                          <?php } ?>
                          <a class="mits-button mits-button--danger" href="<?php echo xtc_href_link('mits_cron_database_restore.php', 'action=confirm&backup=' . rawurlencode($backup['id'])); ?>"><?php echo BUTTON_RESTORE; ?></a>
                          <?php echo xtc_draw_form('mits_cdb_delete_' . md5($backup['id']), 'mits_cron_database_restore.php', '', 'post', 'class="mits-inline-form" onsubmit="return confirm(this.getAttribute(\'data-confirm\').replace(/\\n/g, String.fromCharCode(10)));" data-confirm="' . mits_cdb_restore_html(sprintf(mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_DELETE_CONFIRM', 'Dieses Backup wirklich l&ouml;schen?\n\n%s'), $backup['filename'])) . '"'); ?>
                            <?php echo mits_cdb_restore_csrf_hidden_fields(); ?>
                            <?php echo xtc_draw_hidden_field('action', 'delete'); ?>
                            <?php echo xtc_draw_hidden_field('backup', $backup['id']); ?>
                            <button type="submit" class="mits-button mits-button--danger mits-button--icon" title="<?php echo mits_cdb_restore_html(mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_DELETE', 'L&ouml;schen')); ?>" aria-label="<?php echo mits_cdb_restore_html(mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_DELETE', 'L&ouml;schen')); ?>"><span aria-hidden="true">&#128465;</span></button>
                          </form>
                        </div>
                      </td>
                    </tr>
                    <?php if (!empty($backup['tables_dir'])) { $list_table_files = mits_cdb_restore_collect_table_files($backup['path']); ?>
                      <tr>
                        <td colspan="7">
                          <details class="mits-table-files">
                            <summary>
                              <?php echo mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_TABLE_DOWNLOADS_TOGGLE', 'Einzelne Tabellendateien anzeigen'); ?>
                              <span class="mits-subtle"><?php echo sprintf(mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_TABLE_DOWNLOADS_COUNT', '%s Dateien'), count($list_table_files)); ?></span>
                            </summary>
                            <div class="mits-table-files__body">
                              <div class="mits-subtle" style="margin:0 0 8px;"><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_TABLE_DOWNLOADS_INFO', 'Jede Tabellendatei geh&ouml;rt zu diesem Tabellen-Backup und kann separat heruntergeladen, wiederhergestellt oder gel&ouml;scht werden.'); ?></div>
                              <?php foreach ($list_table_files as $table_file) { ?>
                                <div class="mits-table-files__row">
                                  <span class="mits-table-files__name"><?php echo mits_cdb_restore_html(mits_cdb_restore_table_file_label($table_file['filename'])); ?></span>
                                  <span class="mits-subtle"><?php echo mits_cdb_restore_html($table_file['filename']); ?></span>
                                  <span class="mits-table-files__meta"><?php echo mits_cdb_restore_format_filesize($table_file['size']); ?></span>
                                  <span class="mits-table-files__actions">
                                    <a class="mits-button mits-button--danger mits-table-files__restore" href="<?php echo xtc_href_link('mits_cron_database_restore.php', 'action=confirm&backup=' . rawurlencode($backup['id']) . '&file=' . rawurlencode($table_file['filename'])); ?>" title="<?php echo mits_cdb_restore_html(mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_TABLE_FILE_RESTORE', 'Diese Tabellendatei wiederherstellen')); ?>"><?php echo BUTTON_RESTORE; ?></a>
                                    <a class="mits-button mits-button--soft mits-table-files__download" href="<?php echo xtc_href_link('mits_cron_database_restore.php', 'action=download_table&backup=' . rawurlencode($backup['id']) . '&file=' . rawurlencode($table_file['filename']) . '&mits_restore_token=' . rawurlencode(mits_cdb_restore_ensure_token())); ?>"><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_DOWNLOAD', 'Download'); ?></a>
                                    <?php echo xtc_draw_form('mits_cdb_delete_table_' . md5($backup['id'] . '|' . $table_file['filename']), 'mits_cron_database_restore.php', '', 'post', 'class="mits-inline-form" onsubmit="return confirm(this.getAttribute(\'data-confirm\').replace(/\\n/g, String.fromCharCode(10)));" data-confirm="' . mits_cdb_restore_html(sprintf(mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_DELETE_TABLE_CONFIRM', 'Diese Tabellendatei wirklich l&ouml;schen?\n\n%s'), $table_file['filename'])) . '"'); ?>
                                      <?php echo mits_cdb_restore_csrf_hidden_fields(); ?>
                                      <?php echo xtc_draw_hidden_field('action', 'delete_table'); ?>
                                      <?php echo xtc_draw_hidden_field('backup', $backup['id']); ?>
                                      <?php echo xtc_draw_hidden_field('file', $table_file['filename']); ?>
                                      <button type="submit" class="mits-button mits-button--danger mits-button--icon-sm" title="<?php echo mits_cdb_restore_html(mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_DELETE', 'L&ouml;schen')); ?>" aria-label="<?php echo mits_cdb_restore_html(mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_DELETE', 'L&ouml;schen')); ?>"><span aria-hidden="true">&#128465;</span></button>
                                    </form>
                                  </span>
                                </div>
                              <?php } ?>
                            </div>
                          </details>
                        </td>
                      </tr>
                    <?php } ?>
                  <?php } ?>
                </tbody>
              </table>
            </div>
            <form id="mits_cdb_bulk_delete_form" action="<?php echo xtc_href_link('mits_cron_database_restore.php'); ?>" method="post">
              <?php echo mits_cdb_restore_csrf_hidden_fields(); ?>
              <?php echo xtc_draw_hidden_field('action', 'delete_selected'); ?>
            </form>
            <div class="mits-bulk-actions">
              <button type="submit" form="mits_cdb_bulk_delete_form" class="mits-button mits-button--danger" onclick="return confirm('<?php echo mits_cdb_restore_html(mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_DELETE_SELECTED_CONFIRM', 'Ausgew&auml;hlte Sicherungen wirklich endg&uuml;ltig l&ouml;schen?')); ?>');"><span aria-hidden="true">&#128465;</span><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_DELETE_SELECTED', 'Ausgew&auml;hlte l&ouml;schen'); ?></button>
              <span class="mits-subtle"><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_DELETE_SELECTED_INFO', 'Ausgew&auml;hlte Tabellen-Backups werden komplett inklusive aller enthaltenen Tabellendateien gel&ouml;scht.'); ?></span>
            </div>
          <?php } ?>
        </div>
      </div>

      <div class="mits-admin__stack">
        <div class="mits-card">
          <div class="mits-card__header">
            <h2 class="mits-card__title"><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_BACKUP_MANUAL_TITLE', 'Manuelle Sicherung'); ?></h2>
            <p class="mits-card__subtitle"><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_BACKUP_MANUAL_SUBTITLE', 'Tabellen ausw&auml;hlen und direkt ein Backup erstellen.'); ?></p>
          </div>
          <div class="mits-card__body">
            <?php echo xtc_draw_form('mits_cdb_backup', 'mits_cron_database_restore.php', '', 'post'); ?>
              <?php echo mits_cdb_restore_csrf_hidden_fields(); ?>
              <?php echo xtc_draw_hidden_field('action', 'backup'); ?>
              <div class="mits-field">
                <label for="mits-backup-mode"><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_BACKUP_MODE', 'Backup-Modus'); ?></label>
                <select id="mits-backup-mode" name="backup_mode">
                  <option value="single"<?php echo (!defined('MODULE_MITS_CRON_DATABASE_BACKUPS_BACKUP_MODE') || MODULE_MITS_CRON_DATABASE_BACKUPS_BACKUP_MODE != 'tables') ? ' selected="selected"' : ''; ?>><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_BACKUP_MODE_SINGLE', 'Eine SQL-/SQL.GZ-Datei'); ?></option>
                  <option value="tables"<?php echo (defined('MODULE_MITS_CRON_DATABASE_BACKUPS_BACKUP_MODE') && MODULE_MITS_CRON_DATABASE_BACKUPS_BACKUP_MODE == 'tables') ? ' selected="selected"' : ''; ?>><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_BACKUP_MODE_TABLES', 'Ordner mit einer SQL.GZ pro Tabelle'); ?></option>
                </select>
              </div>
              <div class="mits-subtle"><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_BACKUP_TABLES_INFO', 'Alle Tabellen sind vorausgew&auml;hlt. Entfernen Sie Haken, wenn nur bestimmte Tabellen gesichert werden sollen.'); ?></div>
              <div class="mits-check-toolbar">
                <label class="mits-check-row" for="mits-cdb-backup-tables-select-all">
                  <input type="checkbox" id="mits-cdb-backup-tables-select-all" checked="checked" />
                  <span><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_BACKUP_TABLES_SELECT_ALL', 'Alle Tabellen ausw&auml;hlen / abw&auml;hlen'); ?></span>
                </label>
              </div>
              <div class="mits-check-list">
                <?php foreach ($available_tables as $table_name) { ?>
                  <label class="mits-check-row">
                    <input type="checkbox" class="mits-backup-table-select" name="backup_tables[]" value="<?php echo mits_cdb_restore_html($table_name); ?>" checked="checked" />
                    <span><?php echo mits_cdb_restore_html($table_name); ?></span>
                  </label>
                <?php } ?>
              </div>
              <div class="mits-actions" style="justify-content:flex-start;">
                <button type="submit" class="mits-button mits-button--primary"><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_BACKUP_START', 'Backup erstellen'); ?></button>
              </div>
            </form>
          </div>
        </div>

        <details class="mits-card mits-sql-card"<?php echo $sql_done ? ' open="open"' : ''; ?>>
          <summary class="mits-card__header">
            <h2 class="mits-card__title"><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_SQL_TITLE', 'SQL direkt ausf&uuml;hren'); ?></h2>
            <p class="mits-card__subtitle"><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_SQL_SUBTITLE', 'Query-Box f&uuml;r einzelne SQL-Anweisungen.'); ?></p>
          </summary>
          <div class="mits-card__body">
            <div class="mits-alert mits-alert--warning">
              <strong><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_SQL_WARNING_TITLE', 'Direkter Datenbankzugriff'); ?></strong>
              <?php echo mits_cdb_restore_text('TEXT_MITS_CDB_SQL_WARNING', 'SQL-Code wird direkt auf der aktuellen Shopdatenbank ausgef&uuml;hrt. Erstellen Sie vorher ein Backup. Schreibende Anweisungen m&uuml;ssen unten zus&auml;tzlich erlaubt werden.'); ?>
            </div>
            <?php echo xtc_draw_form('mits_cdb_sql', 'mits_cron_database_restore.php', '', 'post'); ?>
              <?php echo mits_cdb_restore_csrf_hidden_fields(); ?>
              <?php echo xtc_draw_hidden_field('action', 'sql'); ?>
              <div class="mits-field">
                <label for="mits-sql-code"><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_SQL_CODE', 'SQL-Code'); ?></label>
                <textarea id="mits-sql-code" name="sql_code" class="mits-sql-textarea" spellcheck="false" placeholder="SELECT * FROM configuration LIMIT 10;"><?php echo mits_cdb_restore_plain_html($sql_code); ?></textarea>
              </div>
              <div class="mits-field">
                <label for="mits-sql-row-limit"><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_SQL_ROW_LIMIT', 'Maximal angezeigte Ergebniszeilen'); ?></label>
                <select id="mits-sql-row-limit" name="sql_row_limit">
                  <?php foreach (array(25, 50, 100, 250, 500) as $limit_value) { ?>
                    <option value="<?php echo (int)$limit_value; ?>"<?php echo ((int)$sql_row_limit === (int)$limit_value) ? ' selected="selected"' : ''; ?>><?php echo (int)$limit_value; ?></option>
                  <?php } ?>
                </select>
              </div>
              <label class="mits-sql-option">
                <input type="checkbox" name="sql_confirm_write" value="1" />
                <span><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_SQL_CONFIRM_WRITE', 'Schreibende SQL-Anweisungen wie INSERT, UPDATE, DELETE, ALTER oder DROP erlauben.'); ?></span>
              </label>
              <div class="mits-actions" style="justify-content:flex-start;">
                <button type="submit" class="mits-button mits-button--primary"><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_SQL_RUN', 'SQL ausf&uuml;hren'); ?></button>
              </div>
            </form>
          </div>
        </details>

        <div class="mits-card">
          <div class="mits-card__header">
            <h2 class="mits-card__title"><?php echo TEXT_MITS_CDB_RESTORE_WARNING_TITLE; ?></h2>
            <p class="mits-card__subtitle"><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_SAFETY_SUBTITLE', 'Bitte vor dem Start genau pr&uuml;fen.'); ?></p>
          </div>
          <div class="mits-card__body">
            <div class="mits-alert mits-alert--warning" style="margin-bottom:0;">
              <?php echo TEXT_MITS_CDB_RESTORE_WARNING; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  <?php } ?>
</div>
<script>
(function() {
  function bindSelectAll(masterId, itemSelector) {
    var master = document.getElementById(masterId);
    var boxes = document.querySelectorAll(itemSelector);
    if (!master) {
      return;
    }
    if (!boxes.length) {
      master.disabled = true;
      return;
    }
    function updateMaster() {
      var checked = 0;
      for (var i = 0; i < boxes.length; i++) {
        if (boxes[i].checked) {
          checked++;
        }
      }
      master.checked = checked === boxes.length;
      master.indeterminate = checked > 0 && checked < boxes.length;
    }
    master.addEventListener('change', function() {
      for (var i = 0; i < boxes.length; i++) {
        boxes[i].checked = master.checked;
      }
      updateMaster();
    });
    for (var j = 0; j < boxes.length; j++) {
      boxes[j].addEventListener('change', updateMaster);
    }
    updateMaster();
  }

  bindSelectAll('mits-cdb-select-all', '.mits-backup-select');
  bindSelectAll('mits-cdb-backup-tables-select-all', '.mits-backup-table-select');
})();
</script>

<!-- footer //--> 
<?php require(DIR_WS_INCLUDES . 'footer.php'); ?>
<!-- footer_eof //--> 
</body>
</html>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>
