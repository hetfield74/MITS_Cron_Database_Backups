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
  $mits_db_backup_cronjoburl = '<hr /><h3>CronJob-URL:</h3><textarea style="width: 100%;height:auto;">' . xtc_catalog_href_link('callback/mits_cron_database_backups/mits_cron_database_backups.php', 'pw=' . $mits_hash, 'SSL') . '</textarea><p>Tragen Sie in Ihren CronJobs diese URL ein!</p><p>Der Parameter <strong style="color:#900">pw</strong> ist durch den gesetzten HASH-Wert zu ersetzen. Sollten Sie also den HASH-Wert &auml;ndern, dann m&uuml;ssen Sie die dadurch ge&auml;nderte URL auch bei Ihrem angelegten CronJob anpassen.</p>';
  $mits_db_backup_button = '<hr /><div style="text-align:center;padding:10px;"><a href="'.xtc_catalog_href_link('callback/mits_cron_database_backups/mits_cron_database_backups.php', '', 'SSL').'?pw='.$mits_hash.'" class="button" onclick="this.blur();"><strong>Datenbank-Backup starten</strong></a></div>';
  $mits_db_restore_button = '<div style="text-align:center;padding:10px;"><a href="'.xtc_href_link('mits_cron_database_restore.php', '', 'NONSSL').'" class="button" onclick="this.blur();"><strong>Datenbank-Werkzeuge &ouml;ffnen</strong></a></div><hr />';
} else {
  $mits_db_backup_cronjoburl = '';
  $mits_db_backup_button = '';
  $mits_db_restore_button = '';
}

$mits_disabled_functions = array_map('trim', explode(',', (string)ini_get('disable_functions')));
$mits_exec_enabled = function_exists('exec') && !in_array('exec', $mits_disabled_functions) && strtolower((string)ini_get('safe_mode')) != 1;
$mits_curl_enabled = function_exists('curl_init');
$mits_no_curl = (!$mits_curl_enabled) ? '<div style="padding:6px;background:#ff0;font-size:14px;border:1px solid #900;color:#900;"><strong>Die PHP-cURL-Erweiterung ist nicht aktiv. Der modified Scheduled Task f&uuml;r dieses Modul ben&ouml;tigt cURL, damit der Backup-Aufruf isoliert &uuml;ber die Callback-URL ausgef&uuml;hrt werden kann.</strong></div>' : '';
$mits_no_exec = (!$mits_exec_enabled) ? '<div style="padding:6px;background:#ff0;font-size:14px;border:1px solid #900;color:#900;"><strong>Ihr Server verf&uuml;gt nicht &uuml;ber die notwendigen Berechtigungen. Die Funktion <i>exec()</i> ist deaktiviert. Bitte kontaktieren Sie ihren Provider zur Aktivierung oder wechseln sie zu einem Provider mit aktivierter exec-Funktion, z.B. <a href="https://all-inkl.com/?partner=293050">all-inkl.com</a></strong></div>' : '';

$lang_array = array(
  'MODULE_' . $modulname . '_TITLE'       => 'MITS Cron Database Backups <span style="white-space:nowrap;">&copy; by <span style="padding:2px;background:#ffe;color:#6a9;font-weight:bold;">Hetfield (<a href="https://www.merz-it-service.de/" target="_blank">MerZ IT-SerVice</a>)</span></span>',
  'MODULE_' . $modulname . '_DESCRIPTION' => '
   <div>
    <a href="https://www.merz-it-service.de/" target="_blank">
        <img src="' . DIR_WS_CATALOG . 'callback/mits_cron_database_backups/merz-it-service.png" border="0" alt="MerZ IT-SerVice" style="display:block;max-width:100%;height:auto;" />
    </a><br />
    <h3>Datenbanksicherung per CronJob</h3>
    ' . $mits_no_exec . '
    ' . $mits_no_curl . '
    <div>
      <p>Mit diesem Modul k&ouml;nnen Sie Ihre Shopdatenbank automatisch regelm&auml;&szlig;ig per CronJob oder bei Bedarf auch manuell sichern.</p>
      <ul>
        <li>Automatisch regelm&auml;&szlig;ige Datenbanksicherungen des Shops erstellen lassen</li>
        <li>Schnelle R&uuml;cksicherung vorhandener SQL-/SQL.GZ-Backups im Adminbereich per mysql-Client</li>
        <li>Optionaler modified Scheduled Task: ruft die bestehende Callback-URL per cURL auf</li>
        <li>Optional Tabellen-Backup als Ordner mit einer SQL.GZ-Datei pro Tabelle erstellen</li>
        <li>Datenbanksicherung optional per E-Mail erhalten</li>
        <li>Datenbanksicherung optional per FTP auf einen anderen Backup-Server hochladen</li>
        <li>Optional alte Datenbanksicherungen automatisch nach x Tagen l&ouml;schen</li>
        <li>Optional alte LOG-Files vom Typ mod_notice, mod_deprecated und mod_strict des Shops automatisch nach x Tagen l&ouml;schen</li>
      </ul>
      <div style="text-align:center;">
        <small>Nur auf Github gibt es immer die aktuellste Version des Moduls!</small><br />
        <a style="background:#6a9;color:#444" target="_blank" href="https://github.com/hetfield74/MITS_Cron_Database_Backups" class="button" onclick="this.blur();">MITS_Cron_Database_Backups on Github</a>
      </div>
      <p>Bei Fragen, Problemen oder W&uuml;nschen zu diesem Modul oder auch zu anderen Anliegen rund um die modified eCommerce Shopsoftware nehmen Sie einfach Kontakt zu uns auf:</p>
      <div style="text-align:center;"><a style="background:#6a9;color:#444" target="_blank" href="https://www.merz-it-service.de/Kontakt.html" class="button" onclick="this.blur();">Kontaktseite auf MerZ-IT-SerVice.de</a></div>
    </div>
    ' . $mits_db_backup_cronjoburl . '
    ' . $mits_db_backup_button . '
    ' . $mits_db_restore_button . '
  </div>
',

  'MODULE_' . $modulname . '_STATUS_TITLE' => 'Status',
  'MODULE_' . $modulname . '_STATUS_DESC'  => 'Modul aktivieren',

  'MODULE_' . $modulname . '_HASH_TITLE' => 'HASH-Wert',
  'MODULE_' . $modulname . '_HASH_DESC'  => 'Der Parameter <strong>pw</strong> ist durch den bei HASH eingetragenen Wert zu ersetzen, wie in dem URL-Beispiel zu sehen: pw=<strong style="color:#900">' . $mits_default_hash . '</strong>. Aus Sicherheitsgr&uuml;nden sollte der Wert h&auml;ufiger ge&auml;ndert werden. Der ge&auml;nderte HASH-Wert ist dann auch in der URL des Scriptaufrufs zu verwenden.',

  'MODULE_' . $modulname . '_GZIP_TITLE' => 'GZIP-Komprimierung',
  'MODULE_' . $modulname . '_GZIP_DESC'  => 'Soll die GZIP-Komprimierung f&uuml;r die Datenbanksicherung verwendet werden?',

  'MODULE_' . $modulname . '_COMPLETE_INSERT_TITLE' => 'Option --complete-insert',
  'MODULE_' . $modulname . '_COMPLETE_INSERT_DESC'  => 'Complete inserts f&uuml;gen dem SQL-Dump die Spaltennamen hinzu. Dieser Parameter verbessert die Lesbarkeit und Zuverl&auml;ssigkeit des Dumps. Das Hinzuf&uuml;gen der Spaltennamen erh&ouml;ht die Gr&ouml;&szlig;e des SQL-Dumps, ist in Kombination mit Extended Insert aber meist vernachl&auml;ssigbar.',

  'MODULE_' . $modulname . '_EXTENDED_INSERT_TITLE' => 'Option --extended-insert',
  'MODULE_' . $modulname . '_EXTENDED_INSERT_DESC'  => 'Extended Insert kombiniert mehrere Datenzeilen in einer einzigen INSERT-Abfrage. Dies verringert signifikant die Dateigr&ouml;&szlig;e f&uuml;r gro&szlig;e SQL-Dumps, erh&ouml;ht die INSERT-Geschwindigkeit beim Import und wird allgemein empfohlen.',

  'MODULE_' . $modulname . '_SQL_COMMENTS_TITLE' => 'Kommentare im SQL-Dump',
  'MODULE_' . $modulname . '_SQL_COMMENTS_DESC'  => 'Soll der SQL-Dump kommentiert werden? Zus&auml;tzlich zu den mysqldump-Kommentaren wird ein kurzer MITS-Kopfkommentar eingef&uuml;gt. Kommentare werden beim Restore ignoriert.',

  'MODULE_' . $modulname . '_BACKUP_MODE_TITLE' => 'Backup-Modus',
  'MODULE_' . $modulname . '_BACKUP_MODE_DESC'  => '<strong>single</strong> erstellt eine SQL-/SQL.GZ-Datei f&uuml;r die komplette Datenbank. <strong>tables</strong> erstellt einen eigenen Backupordner mit einer SQL.GZ-Datei pro Tabelle.',

  'MODULE_' . $modulname . '_SENDMAIL_TITLE' => 'Datenbanksicherung per E-Mail versenden',
  'MODULE_' . $modulname . '_SENDMAIL_DESC'  => 'Soll die Datenbanksicherung per E-Mail versendet werden?',

  'MODULE_' . $modulname . '_MAILADDRESS_TITLE' => 'E-Mail-Adresse f&uuml;r Backup',
  'MODULE_' . $modulname . '_MAILADDRESS_DESC'  => 'Tragen Sie hier die E-Mail-Adresse ein, an die die Datenbanksicherung gesendet werden soll. Beachten Sie bitte eventuelle Beschr&auml;nkungen bei gro&szlig;en Dateianh&auml;ngen.',

  'MODULE_' . $modulname . '_SENDFTP_TITLE' => 'Datenbanksicherung per FTP senden',
  'MODULE_' . $modulname . '_SENDFTP_DESC'  => 'Soll die Datenbanksicherung per FTP auf einen anderen Server hochgeladen werden?',

  'MODULE_' . $modulname . '_FTP_HOST_TITLE' => 'FTP-Server',
  'MODULE_' . $modulname . '_FTP_HOST_DESC'  => 'Servername des FTP-Servers.',

  'MODULE_' . $modulname . '_FTP_USER_TITLE' => 'FTP-Benutzername',
  'MODULE_' . $modulname . '_FTP_USER_DESC'  => 'Tragen Sie hier den FTP-Benutzernamen ein.',

  'MODULE_' . $modulname . '_FTP_PASS_TITLE' => 'FTP-Passwort',
  'MODULE_' . $modulname . '_FTP_PASS_DESC'  => 'Tragen Sie hier das FTP-Passwort ein.',

  'MODULE_' . $modulname . '_FTP_PORT_TITLE' => 'FTP-Port',
  'MODULE_' . $modulname . '_FTP_PORT_DESC'  => 'Tragen Sie hier den FTP-Port ein, z.B. 21.',

  'MODULE_' . $modulname . '_FTP_PATH_TITLE' => 'FTP-Serverpfad',
  'MODULE_' . $modulname . '_FTP_PATH_DESC'  => 'Tragen Sie hier den kompletten Serverpfad des FTP-Servers ein, in dem die Datenbanksicherung abgelegt werden soll.',

  'MODULE_' . $modulname . '_DELETEOLDBACKUPS_TITLE' => 'Automatisches L&ouml;schen aktivieren',
  'MODULE_' . $modulname . '_DELETEOLDBACKUPS_DESC'  => 'Automatisches L&ouml;schen f&uuml;r alte Datenbank-Sicherungen aktivieren? Bei <strong>ja</strong> werden alle Dateien mit der Endung <i>.sql</i> und <i>.sql.gz</i>, die &auml;lter sind als bei <i>Zeitraum automatisches L&ouml;schen</i> eingestellt, automatisch aus dem Ordner <i>' . (defined('DIR_ADMIN') ? DIR_ADMIN : 'admin/') . 'backups</i> entfernt.',

  'MODULE_' . $modulname . '_DELETEOLDBACKUPS_DAYS_TITLE' => 'Zeitraum automatisches L&ouml;schen',
  'MODULE_' . $modulname . '_DELETEOLDBACKUPS_DAYS_DESC'  => 'Nach wievielen Tagen sollen alte Datenbank-Sicherungen gel&ouml;scht werden? Angabe bitte nur als Tage und als Ziffer eingeben. Nur relevant, wenn <i>Automatisches L&ouml;schen aktivieren</i> auf <strong>ja</strong> steht.',

  'MODULE_' . $modulname . '_DELETELOGS_TITLE' => 'Alte LOG-Files l&ouml;schen',
  'MODULE_' . $modulname . '_DELETELOGS_DESC'  => 'Sollen alte LOG-Files vom Typ mod_notice, mod_strict und mod_deprecated ebenfalls automatisch gel&ouml;scht werden? Nur relevant, wenn <i>Automatisches L&ouml;schen aktivieren</i> auf <strong>ja</strong> steht. Der Zeitraum ist identisch mit der Angabe bei <i>Zeitraum automatisches L&ouml;schen</i>.',

  'MODULE_' . $modulname . '_UPDATE_AVAILABLE_TITLE' => ' <span style="font-weight:bold;color:#900;background:#ff6;padding:2px;border:1px solid #900;">Bitte Modulaktualisierung durchf&uuml;hren!</span>',
  'MODULE_' . $modulname . '_UPDATE_AVAILABLE_DESC'  => '',
  'MODULE_' . $modulname . '_UPDATE_FINISHED'        => 'MITS Cron Database Backups wurde aktualisiert.',
  'MODULE_' . $modulname . '_UPDATE_ERROR'           => 'Fehler',
  'MODULE_' . $modulname . '_UPDATE_MODUL'           => 'Modul aktualisieren',
  'MODULE_' . $modulname . '_DELETE_MODUL'           => 'MITS Cron Database Backups komplett vom Server entfernen',
  'MODULE_' . $modulname . '_CONFIRM_DELETE_MODUL'   => 'Soll das Modul MITS Cron Database Backups inklusive Dateien wirklich vom Server entfernt werden?',
  'MODULE_' . $modulname . '_DELETE_FINISHED'        => 'MITS Cron Database Backups wurde vom Server entfernt.',
);

foreach ($lang_array as $key => $val) {
    defined($key) || define($key, $val);
}
