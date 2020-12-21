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

defined('MODULE_MITS_CRON_DATABASE_BACKUPS_STATUS') or define('MODULE_MITS_CRON_DATABASE_BACKUPS_STATUS', 'true');
defined('MODULE_MITS_CRON_DATABASE_BACKUPS_HASH') or define('MODULE_MITS_CRON_DATABASE_BACKUPS_HASH', '3p7R9VAZcbtUCptYH212u4n7jtVBg4Wy');
defined('MODULE_MITS_CRON_DATABASE_BACKUPS_GZIP') or define('MODULE_MITS_CRON_DATABASE_BACKUPS_GZIP', true);
defined('MODULE_MITS_CRON_DATABASE_BACKUPS_COMPLETE_INSERT') or define('MODULE_MITS_CRON_DATABASE_BACKUPS_COMPLETE_INSERT', 'true');
defined('MODULE_MITS_CRON_DATABASE_BACKUPS_EXTENDED_INSERT') or define('MODULE_MITS_CRON_DATABASE_BACKUPS_EXTENDED_INSERT', 'true');
defined('MODULE_MITS_CRON_DATABASE_BACKUPS_SENDMAIL') or define('MODULE_MITS_CRON_DATABASE_BACKUPS_SENDMAIL', 'false');
defined('MODULE_MITS_CRON_DATABASE_BACKUPS_MAILADDRESS') or define('MODULE_MITS_CRON_DATABASE_BACKUPS_MAILADDRESS', STORE_OWNER_EMAIL_ADDRESS);
defined('MODULE_MITS_CRON_DATABASE_BACKUPS_SENDFTP') or define('MODULE_MITS_CRON_DATABASE_BACKUPS_SENDFTP', 'false');
defined('MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_HOST') or define('MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_HOST', '');
defined('MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_USER') or define('MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_USER', '');
defined('MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_PASS') or define('MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_PASS', '');
defined('MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_PORT') or define('MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_PORT', '');
defined('MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_PATH') or define('MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_PATH', '/');
defined('MODULE_MITS_CRON_DATABASE_BACKUPS_GZIP') or define('MODULE_MITS_CRON_DATABASE_BACKUPS_DELETEOLDBACKUPS', 'true');
defined('MODULE_MITS_CRON_DATABASE_BACKUPS_HASH') or define('MODULE_MITS_CRON_DATABASE_BACKUPS_DELETEOLDBACKUPS_DAYS', '180');
defined('MODULE_MITS_CRON_DATABASE_BACKUPS_GZIP') or define('MODULE_MITS_CRON_DATABASE_BACKUPS_DELETELOGS', 'true');

if (MODULE_MITS_CRON_DATABASE_BACKUPS_STATUS == 'true') {

  if ($_GET) {
    $pw = $_GET['pw'];
  } elseif ($_REQUEST) {
    $pw = $_REQUEST['pw'];
  } else {
    $pw = $argv[1];
  }

  if (!empty($pw) && $pw !== MODULE_MITS_CRON_DATABASE_BACKUPS_HASH && MODULE_MITS_CRON_DATABASE_BACKUPS_STATUS == true) {
    echo 'Kein Zugriff erlaubt!';
    die;
  } else {
    @ini_set('display_errors', 1);
    @set_time_limit(0);

    if (is_dir('export/mits_cron_database_backups')) {
      $dir = 'export/mits_cron_database_backups/';
    } else {
      $dir = (defined('DIR_ADMIN') ? DIR_ADMIN : 'admin/') . 'backups/';
    }
    $sql_file = DB_DATABASE .  "_" . date("Y-m-d_H-i-s") . ".sql";
    if (defined('MODULE_MITS_CRON_DATABASE_BACKUPS_COMPLETE_INSERT') && MODULE_MITS_CRON_DATABASE_BACKUPS_COMPLETE_INSERT == 'false') {
      $complete_insert = " --complete-insert=FALSE";
    } else {
      $complete_insert = "";
    }
    if (defined('MODULE_MITS_CRON_DATABASE_BACKUPS_EXTENDED_INSERT') && MODULE_MITS_CRON_DATABASE_BACKUPS_EXTENDED_INSERT == 'false') {
      $extended_insert = " --extended-insert=FALSE";
    } else {
      $extended_insert = "";
    }
    exec("mysqldump --opt" . $complete_insert . $extended_insert . " -h" . DB_SERVER . " -u" . DB_SERVER_USERNAME . " -p" . DB_SERVER_PASSWORD . " " . DB_DATABASE . " > " . DIR_FS_DOCUMENT_ROOT . $dir . $sql_file);
    if (defined('MODULE_MITS_CRON_DATABASE_BACKUPS_GZIP') && MODULE_MITS_CRON_DATABASE_BACKUPS_GZIP == 'true') {
      exec("gzip  " . DIR_FS_DOCUMENT_ROOT . $dir . $sql_file);
    }

    echo '
      <!doctype html>
      <html>
      <head>
      <meta charset="' . $_SESSION['language_charset'] . '">
      <title>Datenbanksicherung</title>
      </head>
      <body style="text-align:center;background:#ffe;font-family: Arial, Helvetica, sans-serif">
      <div style="text-align:center">
        <a href="https://www.merz-it-service.de/">
          <img src="'.DIR_WS_CATALOG.'callback/mits_cron_database_backups/merz-it-service.png" border="0" alt="MerZ IT-SerVice" style="margin:0 auto;display:block;max-width:100%;height:auto;" />
        </a>
      </div>
      <h1 style="padding:6px;color:#444;font-size:18px;">MITS Datenbanksicherung per Cronjob</h1>
      <p style="padding:6px;color:#444;font-size:14px;"><strong>Datenbank wurde erfolgreich gesichert!</strong></p>
    ';

    if (defined('MODULE_MITS_CRON_DATABASE_BACKUPS_SENDMAIL') && MODULE_MITS_CRON_DATABASE_BACKUPS_SENDMAIL == 'true') {
      if (defined('MODULE_MITS_CRON_DATABASE_BACKUPS_MAILADDRESS') && MODULE_MITS_CRON_DATABASE_BACKUPS_MAILADDRESS != '') {
        require_once (DIR_FS_INC . 'xtc_validate_email.inc.php');
        if (xtc_validate_email(MODULE_MITS_CRON_DATABASE_BACKUPS_MAILADDRESS)) {
          $mail_file = DIR_FS_DOCUMENT_ROOT . $dir . $sql_file;
          if (defined(MODULE_MITS_CRON_DATABASE_BACKUPS_GZIP) && MODULE_MITS_CRON_DATABASE_BACKUPS_GZIP == 'true') {
            $mail_file = DIR_FS_DOCUMENT_ROOT . $dir . $sql_file . '.gz';
          }
          $mail_content_html = 'Im Anhang befindet sich die Datenbanksicherung des Shops ' . STORE_NAME . ' vom ' . date("d.m.Y H:i:s") . ' Uhr';
          $mail_content_txt = 'Im Anhang befindet sich die Datenbanksicherung des Shops ' . STORE_NAME . ' vom ' . date("d.m.Y H:i:s") . ' Uhr';
          xtc_php_mail(EMAIL_SUPPORT_ADDRESS,
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

    if (defined('MODULE_MITS_CRON_DATABASE_BACKUPS_SENDFTP') && MODULE_MITS_CRON_DATABASE_BACKUPS_SENDFTP == 'true') {
      if (defined('MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_HOST') && MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_HOST != '') {

        if (defined('MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_PORT') && MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_PORT != '' && is_numeric(MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_PORT)) {
          $ftp_conn_id = ftp_connect(MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_HOST, MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_PORT);
        } else {
          $ftp_conn_id = ftp_connect(MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_HOST);
        }

        if (defined('MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_USER') && MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_USER != ''
          && defined('MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_PASS') && MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_PASS != '') {
          $ftp_login_result = ftp_login($ftp_conn_id, MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_USER, MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_PASS);

          if ((!$ftp_conn_id) || (!$ftp_login_result)) {
            echo '<p style="padding:6px;color:#444;font-size:14px;"><strong>FTP-Verbindung ist fehlgeschlagen!</strong></p>';
          } else {
            echo '<p style="padding:6px;color:#444;font-size:14px;"><strong>Verbunden mit FTP-Server!</strong></p>';

            $ftp_path = MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_PATH;
            if (substr($ftp_path, -1) == '/') {
              $ftp_path = substr($ftp_path, 0, -1);
            }
            $destination_file = $ftp_path . '/' . $sql_file;
            $source_file = DIR_FS_DOCUMENT_ROOT . $dir . $sql_file;
            if (defined(MODULE_MITS_CRON_DATABASE_BACKUPS_GZIP) && MODULE_MITS_CRON_DATABASE_BACKUPS_GZIP == 'true') {
              $destination_file = $ftp_path . '/' . $sql_file . '.gz';
              $source_file = DIR_FS_DOCUMENT_ROOT . $dir . $sql_file . '.gz';
            }
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

    if (defined('MODULE_MITS_CRON_DATABASE_BACKUPS_DELETEOLDBACKUPS') && MODULE_MITS_CRON_DATABASE_BACKUPS_DELETEOLDBACKUPS == 'true' && is_numeric(MODULE_MITS_CRON_DATABASE_BACKUPS_DELETEOLDBACKUPS_DAYS)) {
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
          } else {
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

    echo '
      <a style="display:block;padding:6px;margin:0 auto;color:#fff;background:#444;width:60%;-webkit-border-radius:4px;-moz-border-radius:4px;border-radius:4px;text-decoration:none;" href="'.xtc_href_link_admin((defined('DIR_ADMIN') ? DIR_ADMIN : 'admin/').'module_export.php', 'set=system&module=mits_cron_database_backups', 'NONSSL').'"><strong>Zur&uuml;ck zum Modul &raquo;</strong></a>
      <p style="text-align:center;padding:6px;color:#555;font-size:11px;margin-top:50px;"> &copy; by <a href="https://www.merz-it-service.de/"><span style="padding:2px;background:#ffe;color:#6a9;font-weight:bold;">Hetfield (MerZ IT-SerVice)</span></a></p>
      </body>
      </html>
    ';

  }

}
?>