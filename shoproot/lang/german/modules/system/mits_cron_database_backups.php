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

defined('MODULE_MITS_CRON_DATABASE_BACKUPS_HASH') or define('MODULE_MITS_CRON_DATABASE_BACKUPS_HASH', '3p7R9VAZcbtUCptYH212u4n7jtVBg4Wy');

if (defined('MODULE_MITS_CRON_DATABASE_BACKUPS_STATUS') && MODULE_MITS_CRON_DATABASE_BACKUPS_STATUS == 'true') {
  $mits_db_backup_cronjoburl = '<hr /><h3>CronJob-URL:</h3><textarea style="width: 100%;height:auto;">' . xtc_catalog_href_link('callback/mits_cron_database_backups/mits_cron_database_backups.php', 'pw=' . MODULE_MITS_CRON_DATABASE_BACKUPS_HASH, 'SSL') . '</textarea><p>Tragen Sie in Ihren CronJobs diese URL ein!</p><p>Der Parameter <strong style="color:#900">pw</strong> ist durch den gesetzten HASH-Wert zu ersetzen. Sollten Sie also den HASH-Wert &auml;ndern, dann m&uuml;ssen Sie die dadurch ge&auml;nderte URL auch bei Ihrem angelegten CronJob anpassen.</p>';
  $mits_db_backup_button = '<hr /><div style="text-align:center;padding:10px;"><a href="'.xtc_catalog_href_link('callback/mits_cron_database_backups/mits_cron_database_backups.php', '', 'SSL').'?pw='.MODULE_MITS_CRON_DATABASE_BACKUPS_HASH.'" class="button" onclick="this.blur();"><strong>Datenbank-Backup starten</strong></a></div><hr />';
} else {
  $mits_db_backup_cronjoburl = '';
  $mits_db_backup_button = '';
}
$mits_exec_enabled = function_exists('exec') && !in_array('exec', array_map('trim', explode(', ', ini_get('disable_functions')))) && strtolower(ini_get('safe_mode')) != 1;
$mits_no_exec = (!$mits_exec_enabled) ? '<div style="padding:6px;background:#ff0;font-size:14px;border:1px solid #900;color:#900;"><strong>Ihr Server verf&uuml;gt nicht &uuml;ber die notwendigen Berechtigungen. Die Funktion <i>exec()</i> ist deaktiviert. Bitte kontaktieren Sie ihren Provider zur Aktivierung oder wechseln sie zu einem Provider mit aktivierter exec-Funktion, z.B. <a href="https://all-inkl.com/?partner=293050">all-inkl.com</a></strong></div>' : '';

define('MODULE_MITS_CRON_DATABASE_BACKUPS_TEXT_TITLE', 'MITS CronDatabaseBackups <span style="white-space:nowrap;">&copy; by <span style="padding:2px;background:#ffe;color:#6a9;font-weight:bold;">Hetfield (<a href="https://www.merz-it-service.de/" target="_blank">MerZ IT-SerVice</a>)</span></span>');
define('MODULE_MITS_CRON_DATABASE_BACKUPS_TEXT_DESCRIPTION', '
   <div> 
    <a href="https://www.merz-it-service.de/" target="_blank">
        <img src="' . DIR_WS_CATALOG . 'callback/mits_cron_database_backups/merz-it-service.png" border="0" alt="" style="display:block;max-width:100%;height:auto;" />
    </a><br />
    <h3>Datenbanksicherung per CronJob</h3>
    ' . $mits_no_exec . ' 
    <div>    
      <p>Mit diesem Modul k&ouml;nnen Sie Ihre Shopdatenbank automatisch regelm&auml;&szlig;ig per CronJob oder bei Bedarf auch manuell sichern. </p>
      <ul>
        <li>Automatisch regelm&auml;&szlig;ige Datenbanksicherungen des Shops erstellen lassen</li>
        <li>Datenbanksicherung optional per E-Mail erhalten</li>
        <li>Datenbanksicherung optional per FTP auf einen anderen Backup-Server hochladen</li>
        <li>Optional alte Datenbanksicherungen automatisch nach x Tagen l&ouml;schen</li>
        <li>Optional alte LOG-Files vom Typ mod_notice, mod_deprecated und mod_strict des Shops automatisch nach x Tagen l&ouml;schen</li>
      </ul>
      <p>Bei Fragen, Problemen oder W&uuml;nschen zu diesem Modul oder auch zu anderen Anliegen rund um die modified eCommerce Shopsoftware nehmen Sie einfach Kontakt zu uns auf:</p> 
      <div style="text-align:center;"><a style="background:#6a9;color:#444" target="_blank" href="https://www.merz-it-service.de/Kontakt.html" class="button" onclick="this.blur();">Kontaktseite auf MerZ-IT-SerVice.de</strong></a></div>
    </div>
    ' . $mits_db_backup_cronjoburl . '        
    ' . $mits_db_backup_button . '
  </div>
');
define('MODULE_MITS_CRON_DATABASE_BACKUPS_STATUS_TITLE', 'Modul aktivieren?');
define('MODULE_MITS_CRON_DATABASE_BACKUPS_STATUS_DESC', 'Das Modul MITS CronDatabaseBackups aktivieren?');
define('MODULE_MITS_CRON_DATABASE_BACKUPS_HASH_TITLE','HASH-Wert');
define('MODULE_MITS_CRON_DATABASE_BACKUPS_HASH_DESC','Der Parameter <strong>pw</strong> ist durch den bei HASH eingetragenen Wert zu ersetzen, wie in dem URL-Beispiel zu sehen: pw=<strong style="color:#900">3p7R9VAZcbtUCptYH212u4n7jtVBg4Wy</strong>. Aus Sicherheitsgr&uuml;nden sollte der Wert h&auml;ufiger ge&auml;ndert werden. Der ge&auml;nderte HASH-Wert ist dann auch in der URL des Scriptaufrufs zu verwenden.');
define('MODULE_MITS_CRON_DATABASE_BACKUPS_GZIP_TITLE', 'GZIP-Komprimierung?');
define('MODULE_MITS_CRON_DATABASE_BACKUPS_GZIP_DESC', 'Soll die GZIP-Komprimierung f&uuml;r die Datenbanksicherung verwendet werden?');
define('MODULE_MITS_CRON_DATABASE_BACKUPS_COMPLETE_INSERT_TITLE', 'Option --complete-insert');
define('MODULE_MITS_CRON_DATABASE_BACKUPS_COMPLETE_INSERT_DESC', 'Complete inserts f&uuml;gen dem SQL-Dumps die Spaltennamen hinzu. Dieser Parameter verbessert die Lesbarkeit und Zuverl&auml;ssigkeit des Dumps. Das Hinzuf&uuml;gen der Spaltennamen erh&ouml;ht die Gr&ouml;sse des SQL-Dumps, aber wenn es mit Extended Insert kombiniert wird, ist es vernachl&auml;ssigbar.');
define('MODULE_MITS_CRON_DATABASE_BACKUPS_EXTENDED_INSERT_TITLE', 'Option --extended-insert');
define('MODULE_MITS_CRON_DATABASE_BACKUPS_EXTENDED_INSERT_DESC', 'Extended Insert kombiniert mehrere Datenzeilen in einer einzigen INSERT-Abfrage. Dies verringert signifikant die Dateigr&ouml;sse f&uuml;r grosse SQL-Dumps, erh&ouml;ht die INSERT-Geschwindigkeit beim Import und wird allgemein empfohlen.');
define('MODULE_MITS_CRON_DATABASE_BACKUPS_SENDMAIL_TITLE', 'Datenbanksicherung per E-Mail?');
define('MODULE_MITS_CRON_DATABASE_BACKUPS_SENDMAIL_DESC', 'Soll die Datenbanksicherung per E-Mail versendet werden?');
define('MODULE_MITS_CRON_DATABASE_BACKUPS_MAILADDRESS_TITLE', 'E-Mail-Adresse f&uuml;r Backup');
define('MODULE_MITS_CRON_DATABASE_BACKUPS_MAILADDRESS_DESC', 'Tragen Sie hier die E-Mail-Adresse ein, an die die Datenbanksicherung gesendet werden soll. Beachten Sie bitte eventuelle Beschr&auml;nkungen bei gro&szlig;en Dateianh&auml;ngen.');
define('MODULE_MITS_CRON_DATABASE_BACKUPS_SENDFTP_TITLE', 'Datenbanksicherung per FTP senden?');
define('MODULE_MITS_CRON_DATABASE_BACKUPS_SENDFTP_DESC', 'Soll die Datenbanksicherung per FTP auf einen anderen Server hochgeladen werden?');
define('MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_HOST_TITLE', 'FTP-Server');
define('MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_HOST_DESC', 'Servername des FTP-Servers.');
define('MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_USER_TITLE', 'FTP-Benutzername');
define('MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_USER_DESC', 'Tragen Sie hier den FTP-Benutzernamen ein.');
define('MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_PASS_TITLE', 'FTP-Passwort');
define('MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_PASS_DESC', 'Tragen Sie hier das FTP-Passwort ein.');
define('MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_PORT_TITLE', 'FTP-Port');
define('MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_PORT_DESC', 'Tragen Sie hier den FTP-Port (z.B. 21) ein.');
define('MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_PATH_TITLE', 'FTP-Serverpfad');
define('MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_PATH_DESC', 'Tragen Sie hier den kompletten Serverpfad des FTP-Servers ein, wo die Datenbanksicherung abgelegt werden soll.');
define('MODULE_MITS_CRON_DATABASE_BACKUPS_DELETEOLDBACKUPS_TITLE', 'Automatisches L&ouml;schen aktivieren?');
define('MODULE_MITS_CRON_DATABASE_BACKUPS_DELETEOLDBACKUPS_DESC', 'Automatisches L&ouml;schen f&uuml;r alte Datenbank-Sicherungen aktivieren? Bei <strong>ja</strong> werden alle Dateien mit der Endung <i>.sql</i> und <i>.sql.gz</i>, die &auml;lter sind als bei <i>Zeitraum automatisches L&ouml;schen</i> eingestellt automatisch aus dem Ordner <i>' . (defined('DIR_ADMIN') ? DIR_ADMIN : 'admin/') . 'backups</i> entfernt.');
define('MODULE_MITS_CRON_DATABASE_BACKUPS_DELETEOLDBACKUPS_DAYS_TITLE', 'Zeitraum automatisches L&ouml;schen');
define('MODULE_MITS_CRON_DATABASE_BACKUPS_DELETEOLDBACKUPS_DAYS_DESC', 'Nach wievielen Tagen sollen alte Datenbank-Sicherungen gel&ouml;scht werden? Angabe bitte nur als Tage und als Ziffer eingeben (nur bei <i>Automatisches L&ouml;schen aktivieren?</i> = <strong>ja</strong>)');
define('MODULE_MITS_CRON_DATABASE_BACKUPS_DELETELOGS_TITLE', 'Alte LOG-Files l&ouml;schen');
define('MODULE_MITS_CRON_DATABASE_BACKUPS_DELETELOGS_DESC', 'Sollen alte LOG-Files vom Typ mod_notice, mod_strict und mod_deprecated ebenfalls automatisch gel&ouml;scht werden? (nur bei <i>Automatisches L&ouml;schen aktivieren?</i> = <strong>ja</strong>, Zeitraum ist identisch mit der Angabe bei <i>Zeitraum automatisches L&ouml;schen</i>)');
