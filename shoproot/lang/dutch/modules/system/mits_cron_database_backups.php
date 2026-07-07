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
  $mits_db_backup_cronjoburl = '<hr /><h3>CronJob-URL:</h3><textarea style="width: 100%;height:auto;">' . xtc_catalog_href_link('callback/mits_cron_database_backups/mits_cron_database_backups.php', 'pw=' . $mits_hash, 'SSL') . '</textarea><p>Gebruik deze URL in uw cronjobs.</p><p>De parameter <strong style="color:#900">pw</strong> moet worden vervangen door de ingestelde HASH-waarde. Als u de HASH-waarde wijzigt, moet u ook de URL in de cronjob aanpassen.</p>';
  $mits_db_backup_button = '<hr /><div style="text-align:center;padding:10px;"><a href="'.xtc_catalog_href_link('callback/mits_cron_database_backups/mits_cron_database_backups.php', '', 'SSL').'?pw='.$mits_hash.'" class="button" onclick="this.blur();"><strong>Databaseback-up starten</strong></a></div>';
  $mits_db_restore_button = '<div style="text-align:center;padding:10px;"><a href="'.xtc_href_link('mits_cron_database_restore.php', '', 'NONSSL').'" class="button" onclick="this.blur();"><strong>Databasetools openen</strong></a></div><hr />';
} else {
  $mits_db_backup_cronjoburl = '';
  $mits_db_backup_button = '';
  $mits_db_restore_button = '';
}

$mits_disabled_functions = array_map('trim', explode(',', (string)ini_get('disable_functions')));
$mits_exec_enabled = function_exists('exec') && !in_array('exec', $mits_disabled_functions) && strtolower((string)ini_get('safe_mode')) != 1;
$mits_curl_enabled = function_exists('curl_init');
$mits_no_curl = (!$mits_curl_enabled) ? '<div style="padding:6px;background:#ff0;font-size:14px;border:1px solid #900;color:#900;"><strong>De PHP-cURL-extensie is niet actief. De modified Scheduled Task voor deze module heeft cURL nodig, zodat de back-upaanroep ge&iuml;soleerd via de callback-URL kan worden uitgevoerd.</strong></div>' : '';
$mits_no_exec = (!$mits_exec_enabled) ? '<div style="padding:6px;background:#ff0;font-size:14px;border:1px solid #900;color:#900;"><strong>Uw server beschikt niet over de vereiste rechten. De functie <i>exec()</i> is uitgeschakeld. Neem contact op met uw provider om deze te activeren of kies een provider met ingeschakelde exec-functie, bijv. <a href="https://all-inkl.com/?partner=293050">all-inkl.com</a>.</strong></div>' : '';

$lang_array = array(
  'MODULE_' . $modulname . '_TITLE'       => 'MITS Cron Database Backups <span style="white-space:nowrap;">&copy; by <span style="padding:2px;background:#ffe;color:#6a9;font-weight:bold;">Hetfield (<a style="color:#6a9;font-size:unset;font-weight:bold;" href="https://www.merz-it-service.de/" target="_blank">MerZ IT-SerVice</a>)</span></span>',
  'MODULE_' . $modulname . '_DESCRIPTION' => '
   <div>
    <a href="https://www.merz-it-service.de/" target="_blank">
        <img src="' . DIR_WS_CATALOG . 'callback/mits_cron_database_backups/merz-it-service.png" border="0" alt="MerZ IT-SerVice" style="display:block;max-width:100%;height:auto;" />
    </a><br />
    <h3>Databaseback-up via CronJob</h3>
    ' . $mits_no_exec . '
    ' . $mits_no_curl . '
    <div>
      <p>Met deze module kunt u de database van uw shop automatisch en regelmatig back-uppen of indien nodig handmatig een back-up starten.</p>
      <ul>
        <li>Automatisch regelmatige databaseback-ups van de shop laten maken</li>

        <li>Optioneel een tabel-back-up als map met &eacute;&eacute;n SQL.GZ-bestand per tabel maken</li>
        <li>Database-engine van geselecteerde tabellen tussen MyISAM en InnoDB converteren</li>
       <li>Snel herstel van bestaande SQL-/SQL.GZ-back-ups in de admin via de mysql-client</li>
        <li>Optionele modified Scheduled Task: roept de bestaande callback-URL via cURL aan</li>
        <li>Databaseback-up optioneel per e-mail ontvangen</li>
        <li>Databaseback-up optioneel via FTP naar een andere back-upserver uploaden</li>
        <li>Oude databaseback-ups optioneel automatisch na x dagen verwijderen</li>
        <li>Oude LOG-bestanden van het type mod_notice, mod_deprecated en mod_strict optioneel automatisch na x dagen verwijderen</li>
      </ul>
      <div style="text-align:center;">
        <small>De nieuwste versie van de module is altijd beschikbaar op GitHub.</small><br />
        <a style="background:#6a9;color:#444" target="_blank" href="https://github.com/hetfield74/MITS_Cron_Database_Backups" class="button" onclick="this.blur();">MITS_Cron_Database_Backups on GitHub</a>
      </div>
      <p>Neem voor vragen, problemen of wensen over deze module of andere zaken rond de modified eCommerce Shopsoftware contact met ons op:</p>
      <div style="text-align:center;"><a style="background:#6a9;color:#444" target="_blank" href="https://www.merz-it-service.de/Kontakt.html" class="button" onclick="this.blur();">Contactpagina op MerZ-IT-SerVice.de</a></div>
    </div>
    ' . $mits_db_backup_cronjoburl . '
    ' . $mits_db_backup_button . '
    ' . $mits_db_restore_button . '
  </div>
',

  'MODULE_' . $modulname . '_STATUS_TITLE' => 'Status',
  'MODULE_' . $modulname . '_STATUS_DESC'  => 'Module activeren',

  'MODULE_' . $modulname . '_HASH_TITLE' => 'HASH-waarde',
  'MODULE_' . $modulname . '_HASH_DESC'  => 'De parameter <strong>pw</strong> moet worden vervangen door de bij HASH ingevoerde waarde, zoals in dit URL-voorbeeld: pw=<strong style="color:#900">' . $mits_default_hash . '</strong>. Om veiligheidsredenen moet de waarde regelmatig worden gewijzigd. De gewijzigde HASH-waarde moet vervolgens ook in de URL van de scriptaanroep worden gebruikt.',

  'MODULE_' . $modulname . '_GZIP_TITLE' => 'GZIP-compressie',
  'MODULE_' . $modulname . '_GZIP_DESC'  => 'Moet GZIP-compressie worden gebruikt voor de databaseback-up?',

  'MODULE_' . $modulname . '_COMPLETE_INSERT_TITLE' => 'Option --complete-insert',
  'MODULE_' . $modulname . '_COMPLETE_INSERT_DESC'  => 'Complete inserts voegen de kolomnamen toe aan de SQL-dump. Deze parameter verbetert de leesbaarheid en betrouwbaarheid van de dump. Het toevoegen van kolomnamen vergroot de SQL-dump, maar is in combinatie met Extended Insert meestal verwaarloosbaar.',

  'MODULE_' . $modulname . '_EXTENDED_INSERT_TITLE' => 'Option --extended-insert',
  'MODULE_' . $modulname . '_EXTENDED_INSERT_DESC'  => 'Extended Insert combineert meerdere datarijen in &eacute;&eacute;n INSERT-query. Dit vermindert de bestandsgrootte bij grote SQL-dumps aanzienlijk, verhoogt de INSERT-snelheid bij import en wordt algemeen aanbevolen.',

  'MODULE_' . $modulname . '_SQL_COMMENTS_TITLE' => 'Commentaar in SQL-dump',
  'MODULE_' . $modulname . '_SQL_COMMENTS_DESC'  => 'Commentaar toevoegen aan de SQL-dump? Naast de mysqldump-commentaren wordt een korte MITS-kopcommentaar toegevoegd. Commentaar wordt bij het herstellen genegeerd.',

  'MODULE_' . $modulname . '_BACKUP_MODE_TITLE' => 'Back-upmodus',
  'MODULE_' . $modulname . '_BACKUP_MODE_DESC'  => '<strong>single</strong> maakt &eacute;&eacute;n SQL-/SQL.GZ-bestand voor de volledige database. <strong>tables</strong> maakt een eigen back-upmap met &eacute;&eacute;n SQL.GZ-bestand per tabel.',

  'MODULE_' . $modulname . '_WRITE_LOG_TITLE' => 'Logbestanden schrijven',
  'MODULE_' . $modulname . '_WRITE_LOG_DESC'  => 'Moeten acties van de MITS database-tools in de logmap van de shop worden vastgelegd? Het logbestand heet <strong>mits_cron_database_backups_YYYY-MM.log</strong>.',

  'MODULE_' . $modulname . '_SENDMAIL_TITLE' => 'Databaseback-up per e-mail verzenden',
  'MODULE_' . $modulname . '_SENDMAIL_DESC'  => 'Moet de databaseback-up per e-mail worden verzonden?',

  'MODULE_' . $modulname . '_MAILADDRESS_TITLE' => 'E-mailadres voor back-up',
  'MODULE_' . $modulname . '_MAILADDRESS_DESC'  => 'Voer hier het e-mailadres in waarnaar de databaseback-up moet worden verzonden. Houd rekening met eventuele beperkingen voor grote bijlagen.',

  'MODULE_' . $modulname . '_SENDFTP_TITLE' => 'Databaseback-up via FTP verzenden',
  'MODULE_' . $modulname . '_SENDFTP_DESC'  => 'Moet de databaseback-up via FTP naar een andere server worden ge&uuml;pload?',

  'MODULE_' . $modulname . '_FTP_HOST_TITLE' => 'FTP-server',
  'MODULE_' . $modulname . '_FTP_HOST_DESC'  => 'Servernaam van de FTP-server.',

  'MODULE_' . $modulname . '_FTP_USER_TITLE' => 'FTP-gebruikersnaam',
  'MODULE_' . $modulname . '_FTP_USER_DESC'  => 'Voer hier de FTP-gebruikersnaam in.',

  'MODULE_' . $modulname . '_FTP_PASS_TITLE' => 'FTP-wachtwoord',
  'MODULE_' . $modulname . '_FTP_PASS_DESC'  => 'Voer hier het FTP-wachtwoord in.',

  'MODULE_' . $modulname . '_FTP_PORT_TITLE' => 'FTP-poort',
  'MODULE_' . $modulname . '_FTP_PORT_DESC'  => 'Voer hier de FTP-poort in, bijv. 21.',

  'MODULE_' . $modulname . '_FTP_PATH_TITLE' => 'FTP-serverpad',
  'MODULE_' . $modulname . '_FTP_PATH_DESC'  => 'Voer hier het volledige serverpad op de FTP-server in waarin de databaseback-up moet worden opgeslagen.',

  'MODULE_' . $modulname . '_DELETEOLDBACKUPS_TITLE' => 'Automatisch verwijderen activeren',
  'MODULE_' . $modulname . '_DELETEOLDBACKUPS_DESC'  => 'Automatisch verwijderen voor oude databaseback-ups activeren? Bij <strong>ja</strong> worden alle bestanden met de extensie <i>.sql</i> en <i>.sql.gz</i> die ouder zijn dan de ingestelde periode automatisch verwijderd uit de map <i>' . (defined('DIR_ADMIN') ? DIR_ADMIN : 'admin/') . 'backups</i>.',

  'MODULE_' . $modulname . '_DELETEOLDBACKUPS_DAYS_TITLE' => 'Periode automatisch verwijderen',
  'MODULE_' . $modulname . '_DELETEOLDBACKUPS_DAYS_DESC'  => 'Na hoeveel dagen moeten oude databaseback-ups worden verwijderd? Voer alleen dagen als cijfer in. Alleen relevant als <i>Automatisch verwijderen activeren</i> op <strong>ja</strong> staat.',

  'MODULE_' . $modulname . '_DELETELOGS_TITLE' => 'Oude LOG-bestanden verwijderen',
  'MODULE_' . $modulname . '_DELETELOGS_DESC'  => 'Moeten oude LOG-bestanden van het type mod_notice, mod_strict en mod_deprecated ook automatisch worden verwijderd? Alleen relevant als <i>Automatisch verwijderen activeren</i> op <strong>ja</strong> staat. De periode is gelijk aan de waarde bij <i>Periode automatisch verwijderen</i>.',

  'MODULE_' . $modulname . '_UPDATE_AVAILABLE_TITLE' => ' <span style="font-weight:bold;color:#900;background:#ff6;padding:2px;border:1px solid #900;">Module-update uitvoeren!</span>',
  'MODULE_' . $modulname . '_UPDATE_AVAILABLE_DESC'  => '',
  'MODULE_' . $modulname . '_UPDATE_FINISHED'        => 'MITS Cron Database Backups is bijgewerkt.',
  'MODULE_' . $modulname . '_UPDATE_ERROR'           => 'Fout',
  'MODULE_' . $modulname . '_UPDATE_MODUL'           => 'Module bijwerken',
  'MODULE_' . $modulname . '_DELETE_MODUL'           => 'MITS Cron Database Backups volledig van de server verwijderen',
  'MODULE_' . $modulname . '_CONFIRM_DELETE_MODUL'   => 'MITS Cron Database Backups inclusief bestanden echt van de server verwijderen?',
  'MODULE_' . $modulname . '_DELETE_FINISHED'        => 'MITS Cron Database Backups is van de server verwijderd.',
);

foreach ($lang_array as $key => $val) {
    defined($key) || define($key, $val);
}
