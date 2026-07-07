<?php
/**
 * --------------------------------------------------------------
 * File: mits_cron_database_backups.php
 * Created by PhpStorm
 * Date: 23.05.2018
 * Time: 16:38
 *
 * Author: Hetfield
 * Copyright: (c) 2018 - MerZ IT-SerVice
 * Web: https://www.merz-it-service.de
 * Contact: info@merz-it-service.de
 *
 * Released under the GNU General Public License
 * --------------------------------------------------------------
 */

$modulname = strtoupper('mits_cron_database_backups');
$module_key = 'MODULE_' . $modulname;
$mits_default_hash = '3p7R9VAZcbtUCptYH212u4n7jtVBg4Wy';
$mits_hash = defined($module_key . '_HASH') ? constant($module_key . '_HASH') : $mits_default_hash;

if (defined($module_key . '_STATUS') && constant($module_key . '_STATUS') == 'true') {
  $mits_db_backup_cronjoburl = '<hr /><h3>CronJob URL:</h3><textarea style="width: 100%;height:auto;">' . xtc_catalog_href_link('callback/mits_cron_database_backups/mits_cron_database_backups.php', 'pw=' . $mits_hash, 'SSL') . '</textarea><p>Use this URL in your cron jobs.</p><p>The parameter <strong style="color:#900">pw</strong> must be replaced by the configured hash value. If you change the hash value, you also have to update the URL in your cron job.</p>';
  $mits_db_backup_button = '<hr /><div style="text-align:center;padding:10px;"><a href="'.xtc_catalog_href_link('callback/mits_cron_database_backups/mits_cron_database_backups.php', '', 'SSL').'?pw='.$mits_hash.'" class="button" onclick="this.blur();"><strong>Start database backup</strong></a></div>';
  $mits_db_restore_button = '<div style="text-align:center;padding:10px;"><a href="'.xtc_href_link('mits_cron_database_restore.php', '', 'NONSSL').'" class="button" onclick="this.blur();"><strong>Open database tools</strong></a></div><hr />';
} else {
  $mits_db_backup_cronjoburl = '';
  $mits_db_backup_button = '';
  $mits_db_restore_button = '';
}

$mits_disabled_functions = array_map('trim', explode(',', (string)ini_get('disable_functions')));
$mits_exec_enabled = function_exists('exec') && !in_array('exec', $mits_disabled_functions) && strtolower((string)ini_get('safe_mode')) != 1;
$mits_curl_enabled = function_exists('curl_init');
$mits_no_curl = (!$mits_curl_enabled) ? '<div style="padding:6px;background:#ff0;font-size:14px;border:1px solid #900;color:#900;"><strong>The PHP cURL extension is not active. The modified Scheduled Task for this module requires cURL so the backup call can run isolated through the callback URL.</strong></div>' : '';
$mits_no_exec = (!$mits_exec_enabled) ? '<div style="padding:6px;background:#ff0;font-size:14px;border:1px solid #900;color:#900;"><strong>Your server does not provide the required permissions. The function <i>exec()</i> is disabled. Please contact your hosting provider to enable it or switch to a provider with exec enabled, e.g. <a href="https://all-inkl.com/?partner=293050">all-inkl.com</a>.</strong></div>' : '';

$lang_array = array(
  'MODULE_' . $modulname . '_TITLE'       => 'MITS Cron Database Backups <span style="white-space:nowrap;">&copy; by <span style="padding:2px;background:#ffe;color:#6a9;font-weight:bold;">Hetfield (<a style="color:#6a9;font-size:unset;font-weight:bold;" href="https://www.merz-it-service.de/" target="_blank">MerZ IT-SerVice</a>)</span></span>',
  'MODULE_' . $modulname . '_DESCRIPTION' => '
   <div>
    <a href="https://www.merz-it-service.de/" target="_blank">
        <img src="' . DIR_WS_CATALOG . 'callback/mits_cron_database_backups/merz-it-service.png" border="0" alt="MerZ IT-SerVice" style="display:block;max-width:100%;height:auto;" />
    </a><br />
    <h3>Database backups by cron job</h3>
    ' . $mits_no_exec . '
    ' . $mits_no_curl . '
    <div>
      <p>This module lets you automatically back up your shop database on a regular schedule or start a backup manually when needed.</p>
      <ul>
        <li>Create regular automatic database backups of the shop</li>

        <li>Optionally create a table backup folder with one SQL.GZ file per table</li>
        <li>Convert the database engine of selected tables between MyISAM and InnoDB</li>
       <li>Fast restore of existing SQL/SQL.GZ backups in the admin area using the mysql client</li>
        <li>Optional modified Scheduled Task: calls the existing callback URL by cURL</li>
        <li>Optionally send database backups by email</li>
        <li>Optionally upload database backups by FTP to another backup server</li>
        <li>Optionally delete old database backups automatically after x days</li>
        <li>Optionally delete old shop log files of type mod_notice, mod_deprecated and mod_strict after x days</li>
      </ul>
      <div style="text-align:center;">
        <small>The latest module version is always available on GitHub.</small><br />
        <a style="background:#6a9;color:#444" target="_blank" href="https://github.com/hetfield74/MITS_Cron_Database_Backups" class="button" onclick="this.blur();">MITS_Cron_Database_Backups on GitHub</a>
      </div>
      <p>For questions, problems or feature requests about this module, or for any other topics related to the modified eCommerce shopsoftware, please contact us:</p>
      <div style="text-align:center;"><a style="background:#6a9;color:#444" target="_blank" href="https://www.merz-it-service.de/Kontakt.html" class="button" onclick="this.blur();">Contact MerZ-IT-SerVice.de</a></div>
    </div>
    ' . $mits_db_backup_cronjoburl . '
    ' . $mits_db_backup_button . '
    ' . $mits_db_restore_button . '
  </div>
',

  'MODULE_' . $modulname . '_STATUS_TITLE' => 'Status',
  'MODULE_' . $modulname . '_STATUS_DESC'  => 'Enable module',

  'MODULE_' . $modulname . '_HASH_TITLE' => 'Hash value',
  'MODULE_' . $modulname . '_HASH_DESC'  => 'The parameter <strong>pw</strong> must be replaced by the value entered as hash, as shown in this URL example: pw=<strong style="color:#900">' . $mits_default_hash . '</strong>. For security reasons this value should be changed from time to time. The changed hash value must also be used in the script call URL.',

  'MODULE_' . $modulname . '_GZIP_TITLE' => 'GZIP compression',
  'MODULE_' . $modulname . '_GZIP_DESC'  => 'Should GZIP compression be used for the database backup?',

  'MODULE_' . $modulname . '_COMPLETE_INSERT_TITLE' => 'Option --complete-insert',
  'MODULE_' . $modulname . '_COMPLETE_INSERT_DESC'  => 'Complete inserts add column names to the SQL dump. This improves readability and reliability. It increases the dump size, but in combination with extended inserts this is usually negligible.',

  'MODULE_' . $modulname . '_EXTENDED_INSERT_TITLE' => 'Option --extended-insert',
  'MODULE_' . $modulname . '_EXTENDED_INSERT_DESC'  => 'Extended insert combines several data rows into one INSERT query. This significantly reduces the file size for large SQL dumps, increases INSERT speed during import and is generally recommended.',

  'MODULE_' . $modulname . '_SQL_COMMENTS_TITLE' => 'Comments in SQL dump',
  'MODULE_' . $modulname . '_SQL_COMMENTS_DESC'  => 'Add comments to the SQL dump? In addition to the mysqldump comments, a short MITS header comment is added. Comments are ignored during restore.',

  'MODULE_' . $modulname . '_BACKUP_MODE_TITLE' => 'Backup mode',
  'MODULE_' . $modulname . '_BACKUP_MODE_DESC'  => '<strong>single</strong> creates one SQL/SQL.GZ file for the complete database. <strong>tables</strong> creates a dedicated backup folder with one SQL.GZ file per table.',

  'MODULE_' . $modulname . '_WRITE_LOG_TITLE' => 'Write log files',
  'MODULE_' . $modulname . '_WRITE_LOG_DESC'  => 'Should actions from the MITS database tools be written to the shop log directory? The log file is named <strong>mits_cron_database_backups_YYYY-MM.log</strong>.',

  'MODULE_' . $modulname . '_SENDMAIL_TITLE' => 'Send database backup by email',
  'MODULE_' . $modulname . '_SENDMAIL_DESC'  => 'Should the database backup be sent by email?',

  'MODULE_' . $modulname . '_MAILADDRESS_TITLE' => 'Email address for backup',
  'MODULE_' . $modulname . '_MAILADDRESS_DESC'  => 'Enter the email address to which the database backup should be sent. Please note possible size limits for large attachments.',

  'MODULE_' . $modulname . '_SENDFTP_TITLE' => 'Send database backup by FTP',
  'MODULE_' . $modulname . '_SENDFTP_DESC'  => 'Should the database backup be uploaded to another server by FTP?',

  'MODULE_' . $modulname . '_FTP_HOST_TITLE' => 'FTP server',
  'MODULE_' . $modulname . '_FTP_HOST_DESC'  => 'Hostname of the FTP server.',

  'MODULE_' . $modulname . '_FTP_USER_TITLE' => 'FTP username',
  'MODULE_' . $modulname . '_FTP_USER_DESC'  => 'Enter the FTP username.',

  'MODULE_' . $modulname . '_FTP_PASS_TITLE' => 'FTP password',
  'MODULE_' . $modulname . '_FTP_PASS_DESC'  => 'Enter the FTP password.',

  'MODULE_' . $modulname . '_FTP_PORT_TITLE' => 'FTP port',
  'MODULE_' . $modulname . '_FTP_PORT_DESC'  => 'Enter the FTP port, e.g. 21.',

  'MODULE_' . $modulname . '_FTP_PATH_TITLE' => 'FTP server path',
  'MODULE_' . $modulname . '_FTP_PATH_DESC'  => 'Enter the complete server path on the FTP server where the database backup should be stored.',

  'MODULE_' . $modulname . '_DELETEOLDBACKUPS_TITLE' => 'Enable automatic deletion',
  'MODULE_' . $modulname . '_DELETEOLDBACKUPS_DESC'  => 'Enable automatic deletion for old database backups? If set to <strong>yes</strong>, all files ending in <i>.sql</i> and <i>.sql.gz</i> that are older than the configured deletion period will be removed automatically from the folder <i>' . (defined('DIR_ADMIN') ? DIR_ADMIN : 'admin/') . 'backups</i>.',

  'MODULE_' . $modulname . '_DELETEOLDBACKUPS_DAYS_TITLE' => 'Automatic deletion period',
  'MODULE_' . $modulname . '_DELETEOLDBACKUPS_DAYS_DESC'  => 'After how many days should old database backups be deleted? Please enter the value as days and digits only. Only relevant if <i>Enable automatic deletion</i> is set to <strong>yes</strong>.',

  'MODULE_' . $modulname . '_DELETELOGS_TITLE' => 'Delete old log files',
  'MODULE_' . $modulname . '_DELETELOGS_DESC'  => 'Should old log files of type mod_notice, mod_strict and mod_deprecated also be deleted automatically? Only relevant if <i>Enable automatic deletion</i> is set to <strong>yes</strong>. The period is identical to the value configured for <i>Automatic deletion period</i>.',

  'MODULE_' . $modulname . '_UPDATE_AVAILABLE_TITLE' => ' <span style="font-weight:bold;color:#900;background:#ff6;padding:2px;border:1px solid #900;">Please update module!</span>',
  'MODULE_' . $modulname . '_UPDATE_AVAILABLE_DESC'  => '',
  'MODULE_' . $modulname . '_UPDATE_FINISHED'        => 'MITS Cron Database Backups has been updated.',
  'MODULE_' . $modulname . '_UPDATE_ERROR'           => 'Error',
  'MODULE_' . $modulname . '_UPDATE_MODUL'           => 'Update module',
  'MODULE_' . $modulname . '_DELETE_MODUL'           => 'Completely remove MITS Cron Database Backups from server',
  'MODULE_' . $modulname . '_CONFIRM_DELETE_MODUL'   => 'Do you really want to remove MITS Cron Database Backups including files from the server?',
  'MODULE_' . $modulname . '_DELETE_FINISHED'        => 'MITS Cron Database Backups has been removed from the server.',
);

foreach ($lang_array as $key => $val) {
    defined($key) || define($key, $val);
}
