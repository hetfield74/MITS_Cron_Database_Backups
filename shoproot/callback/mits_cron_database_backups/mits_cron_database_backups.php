<?php
/**
 * --------------------------------------------------------------
 * File: mits_cron_database_backups.php
 * Date: 19.10.2019
 * Time: 13:04
 *
 * Author: Hetfield
 * Copyright: (c) 2019 - MerZ IT-SerVice
 * Web: https://www.merz-it-service.de
 * Contact: info@merz-it-service.de
 * --------------------------------------------------------------
 */

date_default_timezone_set("Europe/Berlin");
chdir('../../');

// https://www.domain.de/callback/mits_cron_database_backups/mits_cron_database_backups.php?pw=3p7R9VAZcbtUCptYH212u4n7jtVBg4Wy

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


function mits_cdb_backup_bool($constant, $default = 'false')
{
    $value = defined($constant) ? constant($constant) : $default;
    return ((string)$value === 'true');
}

function mits_cdb_backup_safe_name($value)
{
    return preg_replace('/[^A-Za-z0-9_.-]/', '_', (string)$value);
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
    $command = 'mysqldump --opt'
      . mits_cdb_backup_comments_option()
      . mits_cdb_backup_complete_insert_option()
      . mits_cdb_backup_extended_insert_option()
      . ' -h' . escapeshellarg(DB_SERVER)
      . ' -u' . escapeshellarg(DB_SERVER_USERNAME)
      . mits_cdb_backup_mysql_password_arg()
      . ' ' . escapeshellarg(DB_DATABASE);

    if ($table != '') {
        $command .= ' ' . escapeshellarg($table);
    }

    $command .= ' > ' . escapeshellarg($target_file) . ' 2>&1';
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

    if (!is_dir($backup_dir) || !is_writable($backup_dir)) {
        $dump_output = array('Der Backup-Ordner f&uuml;r die Tabellen konnte nicht erstellt werden.');
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
            mits_cdb_backup_delete_directory($backup_dir);
            return false;
        }

        if (!mits_cdb_backup_prepend_text($sql_file, mits_cdb_backup_table_sql_header('tables', $table_name))) {
            mits_cdb_backup_delete_directory($backup_dir);
            $dump_output = array('Der SQL-Kopf konnte nicht eingef&uuml;gt werden.');
            return false;
        }
        @file_put_contents($sql_file, "\nSET FOREIGN_KEY_CHECKS=1;\n", FILE_APPEND);

        $gzip_output = array();
        $gzip_result = 1;
        exec('gzip -f ' . escapeshellarg($sql_file) . ' 2>&1', $gzip_output, $gzip_result);
        if ($gzip_result !== 0 || !is_file($sql_file . '.gz') || filesize($sql_file . '.gz') < 1) {
            mits_cdb_backup_delete_directory($backup_dir);
            $dump_output = (!empty($gzip_output) ? $gzip_output : array('GZIP-Komprimierung der Tabelle ist fehlgeschlagen.'));
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

        $exec_enabled = function_exists('exec') && !in_array('exec', array_map('trim', explode(',', ini_get('disable_functions')))) && strtolower(ini_get('safe_mode')) != 1;
        $no_exec = '';
        $backup_success = false;

        if (is_dir('export/mits_cron_database_backups')) {
            $dir = 'export/mits_cron_database_backups/';
        } else {
            $dir = (defined('DIR_ADMIN') ? DIR_ADMIN : 'admin/') . 'backups/';
        }
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
                $backup_success = ($dump_result === 0 && is_file($backup_file));
                if ($backup_success) {
                    $backup_success = mits_cdb_backup_prepend_sql_header($backup_file, 'single');
                }
                if ($backup_success && defined('MODULE_MITS_CRON_DATABASE_BACKUPS_GZIP') && MODULE_MITS_CRON_DATABASE_BACKUPS_GZIP == 'true') {
                    exec('gzip -f ' . escapeshellarg($backup_file), $gzip_output, $gzip_result);
                    $backup_success = ($gzip_result === 0 && is_file($backup_file . '.gz'));
                    $backup_final_file = $backup_file . '.gz';
                    $backup_final_name = $sql_file . '.gz';
                }
            }
        } else {
            $no_exec = '<p style="padding:6px;color:#444;font-size:14px;"><strong>Ihr Server verf&uuml;gt nicht &uuml;ber die notwendigen Bereichtigungen. Die Funktion <i>exec()</i>ist deaktiviert.</strong></p>';
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
            echo '<p style="padding:6px;color:#444;font-size:14px;"><strong>Datenbank wurde erfolgreich gesichert!</strong></p>' . "\n<!-- MITS_CRON_DATABASE_BACKUPS_SUCCESS -->\n";

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