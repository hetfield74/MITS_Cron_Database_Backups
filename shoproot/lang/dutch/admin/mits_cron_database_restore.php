<?php
/**
 * --------------------------------------------------------------
 * File: mits_cron_database_restore.php
 * Date: 12.06.2026
 *
 * Released under the GNU General Public License
 * --------------------------------------------------------------
 */

defined('HEADING_TITLE') or define('HEADING_TITLE', 'MITS databasetools');
defined('TEXT_MITS_CDB_RESTORE_PAGE_TITLE') or define('TEXT_MITS_CDB_RESTORE_PAGE_TITLE', 'MITS Cron Database Backups - databasetools');
defined('TEXT_MITS_CDB_RESTORE_WARNING_TITLE') or define('TEXT_MITS_CDB_RESTORE_WARNING_TITLE', 'Let op: risicovolle actie');
defined('TEXT_MITS_CDB_RESTORE_WARNING') or define('TEXT_MITS_CDB_RESTORE_WARNING', 'Een herstelactie vervangt gegevens in de huidige shopdatabase. Voer deze actie alleen uit als u zeker weet dat de gekozen back-up bij de huidige shopversie en database past. Voor de import wordt automatisch een extra veiligheidsback-up van de huidige database gemaakt.');
defined('TEXT_MITS_CDB_RESTORE_INTRO') or define('TEXT_MITS_CDB_RESTORE_INTRO', 'Selecteer een bestaande SQL-, SQL.GZ- of tabelback-up. Daarnaast kunt u handmatige back-ups maken, afzonderlijke back-upbestanden downloaden of verwijderen en SQL direct uitvoeren.');
defined('TEXT_MITS_CDB_RESTORE_NO_BACKUPS') or define('TEXT_MITS_CDB_RESTORE_NO_BACKUPS', 'Er zijn geen passende .sql-, .sql.gz- of .zip-back-ups gevonden.');
defined('TEXT_MITS_CDB_RESTORE_DIR_MODULE') or define('TEXT_MITS_CDB_RESTORE_DIR_MODULE', 'MITS back-upmap');
defined('TEXT_MITS_CDB_RESTORE_DIR_ADMIN') or define('TEXT_MITS_CDB_RESTORE_DIR_ADMIN', 'Shop back-upmap');
defined('TEXT_MITS_CDB_RESTORE_FILE') or define('TEXT_MITS_CDB_RESTORE_FILE', 'Bestand');
defined('TEXT_MITS_CDB_RESTORE_DIRECTORY') or define('TEXT_MITS_CDB_RESTORE_DIRECTORY', 'Map');
defined('TEXT_MITS_CDB_RESTORE_SIZE') or define('TEXT_MITS_CDB_RESTORE_SIZE', 'Grootte');
defined('TEXT_MITS_CDB_RESTORE_DATE') or define('TEXT_MITS_CDB_RESTORE_DATE', 'Datum');
defined('TEXT_MITS_CDB_RESTORE_TYPE') or define('TEXT_MITS_CDB_RESTORE_TYPE', 'Type');
defined('TEXT_MITS_CDB_RESTORE_DOWNLOAD') or define('TEXT_MITS_CDB_RESTORE_DOWNLOAD', 'Downloaden');
defined('TEXT_MITS_CDB_RESTORE_CONFIRM_TITLE') or define('TEXT_MITS_CDB_RESTORE_CONFIRM_TITLE', 'Herstel bevestigen');
defined('TEXT_MITS_CDB_RESTORE_CONFIRM_WARNING') or define('TEXT_MITS_CDB_RESTORE_CONFIRM_WARNING', 'Na de start mag de browser niet worden gesloten. De shop kan tijdens de import tijdelijk niet correct reageren.');
defined('TEXT_MITS_CDB_RESTORE_CONFIRM_INPUT') or define('TEXT_MITS_CDB_RESTORE_CONFIRM_INPUT', 'Voer ter bevestiging exact <strong>RESTORE</strong> in:');
defined('TEXT_MITS_CDB_RESTORE_RESULT_SUCCESS') or define('TEXT_MITS_CDB_RESTORE_RESULT_SUCCESS', 'Herstel voltooid');
defined('TEXT_MITS_CDB_RESTORE_RESULT_ERROR') or define('TEXT_MITS_CDB_RESTORE_RESULT_ERROR', 'Herstel mislukt');
defined('TEXT_MITS_CDB_RESTORE_SAFETY_BACKUP_CREATED') or define('TEXT_MITS_CDB_RESTORE_SAFETY_BACKUP_CREATED', 'Veiligheidsback-up voor het herstel is gemaakt: %s');
defined('TEXT_MITS_CDB_RESTORE_GZ_UNPACKED') or define('TEXT_MITS_CDB_RESTORE_GZ_UNPACKED', 'GZIP-back-up is tijdelijk uitgepakt.');
defined('TEXT_MITS_CDB_RESTORE_SUCCESS') or define('TEXT_MITS_CDB_RESTORE_SUCCESS', 'Back-up %s is succesvol in de database ge&iuml;mporteerd.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_EXEC_DISABLED') or define('TEXT_MITS_CDB_RESTORE_ERROR_EXEC_DISABLED', 'De PHP-functie exec() is uitgeschakeld. Snel herstel via mysql is daardoor niet mogelijk.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_LOCKED') or define('TEXT_MITS_CDB_RESTORE_ERROR_LOCKED', 'Er loopt al een herstelactie of er is een oud lockbestand aanwezig. Probeer het later opnieuw.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_TOKEN') or define('TEXT_MITS_CDB_RESTORE_ERROR_TOKEN', 'De beveiligingstoken is ongeldig. Laad de pagina opnieuw en probeer het nogmaals.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_INVALID_FILE') or define('TEXT_MITS_CDB_RESTORE_ERROR_INVALID_FILE', 'Het gekozen back-upbestand is ongeldig of niet meer aanwezig.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_CONFIRM') or define('TEXT_MITS_CDB_RESTORE_ERROR_CONFIRM', 'De bevestiging is onjuist. Het herstel is niet gestart.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_SAFETY_DIR') or define('TEXT_MITS_CDB_RESTORE_ERROR_SAFETY_DIR', 'De back-upmap is niet beschrijfbaar. De veiligheidsback-up voor het herstel kon niet worden gemaakt.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_SAFETY_BACKUP') or define('TEXT_MITS_CDB_RESTORE_ERROR_SAFETY_BACKUP', 'De veiligheidsback-up voor het herstel kon niet worden gemaakt. De import is afgebroken.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_ZLIB') or define('TEXT_MITS_CDB_RESTORE_ERROR_ZLIB', 'De PHP-Zlib-extensie is niet actief. SQL.GZ-bestanden kunnen niet worden uitgepakt.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_GZ_OPEN') or define('TEXT_MITS_CDB_RESTORE_ERROR_GZ_OPEN', 'Het SQL.GZ-bestand kon niet worden geopend.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_GZ_READ') or define('TEXT_MITS_CDB_RESTORE_ERROR_GZ_READ', 'Het SQL.GZ-bestand kon niet volledig worden gelezen.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_TEMPFILE') or define('TEXT_MITS_CDB_RESTORE_ERROR_TEMPFILE', 'Het tijdelijke SQL-bestand kon niet worden gemaakt.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_IMPORT') or define('TEXT_MITS_CDB_RESTORE_ERROR_IMPORT', 'De import van %s is mislukt.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_DOWNLOAD') or define('TEXT_MITS_CDB_RESTORE_ERROR_DOWNLOAD', 'Het back-upbestand kon niet worden gedownload.');

defined('TEXT_MITS_CDB_RESTORE_ERROR_ZIPARCHIVE') or define('TEXT_MITS_CDB_RESTORE_ERROR_ZIPARCHIVE', 'De PHP-extensie ZipArchive is niet actief. ZIP-back-ups kunnen niet worden uitgepakt.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_ZIP_OPEN') or define('TEXT_MITS_CDB_RESTORE_ERROR_ZIP_OPEN', 'Het ZIP-bestand kon niet worden geopend.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_ZIP_EMPTY') or define('TEXT_MITS_CDB_RESTORE_ERROR_ZIP_EMPTY', 'In het ZIP-bestand zijn geen SQL-bestanden gevonden.');
defined('TEXT_MITS_CDB_RESTORE_ZIP_UNPACKED') or define('TEXT_MITS_CDB_RESTORE_ZIP_UNPACKED', 'ZIP-back-up is tijdelijk uitgepakt en voorbereid voor import.');
defined('TEXT_MITS_CDB_RESTORE_RELOGIN_TITLE') or define('TEXT_MITS_CDB_RESTORE_RELOGIN_TITLE', 'Adminsessie is be&euml;indigd');
defined('TEXT_MITS_CDB_RESTORE_RELOGIN_TEXT') or define('TEXT_MITS_CDB_RESTORE_RELOGIN_TEXT', 'Het herstel is voltooid. Om veiligheidsredenen is de huidige adminsessie be&euml;indigd. Meld u opnieuw aan in de admin.');
defined('TEXT_MITS_CDB_RESTORE_RELOGIN_BUTTON') or define('TEXT_MITS_CDB_RESTORE_RELOGIN_BUTTON', 'Naar admin-login');
defined('TEXT_MITS_CDB_RESTORE_RELOGIN_NOTE') or define('TEXT_MITS_CDB_RESTORE_RELOGIN_NOTE', 'Dit is bewust gedaan, omdat sessie-, admingebruikers- en rechtengegevens door het herstel naar de stand van de back-up kunnen zijn teruggezet.');
defined('TEXT_MITS_CDB_RESTORE_HERO_TEXT') or define('TEXT_MITS_CDB_RESTORE_HERO_TEXT', 'Database back-uppen, back-ups downloaden, tabellen gericht herstellen, oude back-ups verwijderen en SQL-query\'s direct uitvoeren. Opmerking: v&oacute;&oacute;r herstel wordt automatisch een veiligheidsback-up gemaakt.');
defined('TEXT_MITS_CDB_RESTORE_MODULE_SETTINGS') or define('TEXT_MITS_CDB_RESTORE_MODULE_SETTINGS', 'Module-instellingen');
defined('TEXT_MITS_CDB_RESTORE_REFRESH') or define('TEXT_MITS_CDB_RESTORE_REFRESH', 'Lijst vernieuwen');
defined('TEXT_MITS_CDB_RESTORE_STAT_BACKUPS') or define('TEXT_MITS_CDB_RESTORE_STAT_BACKUPS', 'Back-ups');
defined('TEXT_MITS_CDB_RESTORE_STAT_COMPRESSED') or define('TEXT_MITS_CDB_RESTORE_STAT_COMPRESSED', '%s gecomprimeerd');
defined('TEXT_MITS_CDB_RESTORE_STAT_TOTAL_SIZE') or define('TEXT_MITS_CDB_RESTORE_STAT_TOTAL_SIZE', 'Totale grootte');
defined('TEXT_MITS_CDB_RESTORE_STAT_TOTAL_SIZE_META') or define('TEXT_MITS_CDB_RESTORE_STAT_TOTAL_SIZE_META', 'alle gevonden bestanden');
defined('TEXT_MITS_CDB_RESTORE_STAT_LATEST') or define('TEXT_MITS_CDB_RESTORE_STAT_LATEST', 'Laatste back-up');
defined('TEXT_MITS_CDB_RESTORE_STAT_LATEST_META') or define('TEXT_MITS_CDB_RESTORE_STAT_LATEST_META', 'nieuwste bestand eerst');
defined('TEXT_MITS_CDB_RESTORE_STAT_SYSTEM') or define('TEXT_MITS_CDB_RESTORE_STAT_SYSTEM', 'Systeemstatus');
defined('TEXT_MITS_CDB_RESTORE_STAT_SYSTEM_META') or define('TEXT_MITS_CDB_RESTORE_STAT_SYSTEM_META', 'Import via serverclient');
defined('TEXT_MITS_CDB_RESTORE_EXEC_AVAILABLE') or define('TEXT_MITS_CDB_RESTORE_EXEC_AVAILABLE', 'mysql gereed');
defined('TEXT_MITS_CDB_RESTORE_EXEC_NOT_AVAILABLE') or define('TEXT_MITS_CDB_RESTORE_EXEC_NOT_AVAILABLE', 'exec uitgeschakeld');
defined('TEXT_MITS_CDB_RESTORE_RESULT_SUBTITLE') or define('TEXT_MITS_CDB_RESTORE_RESULT_SUBTITLE', 'Het resultaat van het herstel is gelogd.');
defined('TEXT_MITS_CDB_RESTORE_CONFIRM_SUBTITLE') or define('TEXT_MITS_CDB_RESTORE_CONFIRM_SUBTITLE', 'Controleer het bestand en bevestig het herstel bewust.');
defined('TEXT_MITS_CDB_RESTORE_PROCESS_TITLE') or define('TEXT_MITS_CDB_RESTORE_PROCESS_TITLE', 'Verloop van het herstel');
defined('TEXT_MITS_CDB_RESTORE_PROCESS_SUBTITLE') or define('TEXT_MITS_CDB_RESTORE_PROCESS_SUBTITLE', 'Deze veiligheidsstappen worden voor en tijdens de import uitgevoerd.');
defined('TEXT_MITS_CDB_RESTORE_STEP_BACKUP') or define('TEXT_MITS_CDB_RESTORE_STEP_BACKUP', 'De huidige database wordt als veiligheidsback-up opgeslagen.');
defined('TEXT_MITS_CDB_RESTORE_STEP_UNPACK') or define('TEXT_MITS_CDB_RESTORE_STEP_UNPACK', 'SQL.GZ-bestanden en tabelback-ups worden indien nodig tijdelijk uitgepakt.');
defined('TEXT_MITS_CDB_RESTORE_STEP_IMPORT') or define('TEXT_MITS_CDB_RESTORE_STEP_IMPORT', 'De import gebeurt via de mysql-client van de server.');
defined('TEXT_MITS_CDB_RESTORE_STEP_RELOGIN') or define('TEXT_MITS_CDB_RESTORE_STEP_RELOGIN', 'Na succesvol herstel wordt de adminsessie be&euml;indigd.');
defined('TEXT_MITS_CDB_RESTORE_CARD_BACKUPS') or define('TEXT_MITS_CDB_RESTORE_CARD_BACKUPS', 'Beschikbare back-ups');
defined('TEXT_MITS_CDB_RESTORE_SAFETY_SUBTITLE') or define('TEXT_MITS_CDB_RESTORE_SAFETY_SUBTITLE', 'Controleer alles zorgvuldig voor de start.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_SAFETY_GZIP') or define('TEXT_MITS_CDB_RESTORE_ERROR_SAFETY_GZIP', 'GZIP-compressie van de veiligheidsback-up is mislukt. Het herstel is niet gestart.');
defined('TEXT_MITS_CDB_RESTORE_TABLE_DOWNLOADS_TOGGLE') or define('TEXT_MITS_CDB_RESTORE_TABLE_DOWNLOADS_TOGGLE', 'Afzonderlijke tabelbestanden tonen');
defined('TEXT_MITS_CDB_RESTORE_TABLE_DOWNLOADS_COUNT') or define('TEXT_MITS_CDB_RESTORE_TABLE_DOWNLOADS_COUNT', '%s bestanden');
defined('TEXT_MITS_CDB_RESTORE_TABLE_DOWNLOADS_INFO') or define('TEXT_MITS_CDB_RESTORE_TABLE_DOWNLOADS_INFO', 'Elk tabelbestand hoort bij deze tabelback-up en kan afzonderlijk worden gedownload, hersteld of verwijderd.');
defined('TEXT_MITS_CDB_RESTORE_TABLE_FILE_RESTORE') or define('TEXT_MITS_CDB_RESTORE_TABLE_FILE_RESTORE', 'Dit tabelbestand herstellen.');
defined('TEXT_MITS_CDB_RESTORE_TABLE_PRESELECTED_INFO') or define('TEXT_MITS_CDB_RESTORE_TABLE_PRESELECTED_INFO', 'Alleen dit tabelbestand is voorgeselecteerd: %s');

defined('TEXT_MITS_CDB_SQL_TITLE') or define('TEXT_MITS_CDB_SQL_TITLE', 'SQL direct uitvoeren');
defined('TEXT_MITS_CDB_SQL_SUBTITLE') or define('TEXT_MITS_CDB_SQL_SUBTITLE', 'Query-box voor afzonderlijke SQL-instructies.');
defined('TEXT_MITS_CDB_SQL_WARNING_TITLE') or define('TEXT_MITS_CDB_SQL_WARNING_TITLE', 'Directe databasetoegang');
defined('TEXT_MITS_CDB_SQL_WARNING') or define('TEXT_MITS_CDB_SQL_WARNING', 'SQL-code wordt direct op de huidige shopdatabase uitgevoerd. Maak eerst een backup. Schrijvende instructies moeten hieronder expliciet worden toegestaan.');
defined('TEXT_MITS_CDB_SQL_CODE') or define('TEXT_MITS_CDB_SQL_CODE', 'SQL-code');
defined('TEXT_MITS_CDB_SQL_ROW_LIMIT') or define('TEXT_MITS_CDB_SQL_ROW_LIMIT', 'Maximaal weergegeven resultaatregels');
defined('TEXT_MITS_CDB_SQL_CONFIRM_WRITE') or define('TEXT_MITS_CDB_SQL_CONFIRM_WRITE', 'Schrijvende SQL-instructies zoals INSERT, UPDATE, DELETE, ALTER of DROP expliciet toestaan.');
defined('TEXT_MITS_CDB_SQL_RUN') or define('TEXT_MITS_CDB_SQL_RUN', 'SQL uitvoeren');
defined('TEXT_MITS_CDB_SQL_RESULT_SUCCESS') or define('TEXT_MITS_CDB_SQL_RESULT_SUCCESS', 'SQL uitgevoerd');
defined('TEXT_MITS_CDB_SQL_RESULT_ERROR') or define('TEXT_MITS_CDB_SQL_RESULT_ERROR', 'SQL mislukt');
defined('TEXT_MITS_CDB_SQL_RESULT_SUBTITLE') or define('TEXT_MITS_CDB_SQL_RESULT_SUBTITLE', 'Het resultaat van de SQL-uitvoering is gelogd.');
defined('TEXT_MITS_CDB_SQL_RESULT_STATEMENT_TITLE') or define('TEXT_MITS_CDB_SQL_RESULT_STATEMENT_TITLE', 'SQL #%s');
defined('TEXT_MITS_CDB_SQL_STATUS_OK') or define('TEXT_MITS_CDB_SQL_STATUS_OK', 'OK');
defined('TEXT_MITS_CDB_SQL_STATUS_ERROR') or define('TEXT_MITS_CDB_SQL_STATUS_ERROR', 'Fout');
defined('TEXT_MITS_CDB_SQL_RESULT_EMPTY') or define('TEXT_MITS_CDB_SQL_RESULT_EMPTY', 'De query heeft geen records teruggegeven.');
defined('TEXT_MITS_CDB_SQL_ERROR_EMPTY') or define('TEXT_MITS_CDB_SQL_ERROR_EMPTY', 'Er is geen SQL-code ingevoerd.');
defined('TEXT_MITS_CDB_SQL_ERROR_CONNECTION') or define('TEXT_MITS_CDB_SQL_ERROR_CONNECTION', 'De databaseverbinding is niet beschikbaar.');
defined('TEXT_MITS_CDB_SQL_ERROR_WRITE_CONFIRM') or define('TEXT_MITS_CDB_SQL_ERROR_WRITE_CONFIRM', 'Schrijvende SQL-instructies zijn niet uitgevoerd omdat toestemming niet was geactiveerd.');
defined('TEXT_MITS_CDB_SQL_ERROR_STATEMENT') or define('TEXT_MITS_CDB_SQL_ERROR_STATEMENT', 'SQL #%s mislukt: %s');
defined('TEXT_MITS_CDB_SQL_SUCCESS_ROWS') or define('TEXT_MITS_CDB_SQL_SUCCESS_ROWS', '%s records gevonden, %s weergegeven.');
defined('TEXT_MITS_CDB_SQL_SUCCESS_AFFECTED') or define('TEXT_MITS_CDB_SQL_SUCCESS_AFFECTED', 'SQL-instructie uitgevoerd. Betrokken rijen: %s.');
defined('TEXT_MITS_CDB_SQL_SUCCESS_INSERT_ID') or define('TEXT_MITS_CDB_SQL_SUCCESS_INSERT_ID', 'Insert-ID: %s.');
defined('TEXT_MITS_CDB_SQL_SUCCESS_SUMMARY') or define('TEXT_MITS_CDB_SQL_SUCCESS_SUMMARY', '%s SQL-instructie(s) succesvol uitgevoerd.');
defined('TEXT_MITS_CDB_RESTORE_DELETE') or define('TEXT_MITS_CDB_RESTORE_DELETE', 'Verwijderen');
defined('TEXT_MITS_CDB_RESTORE_DELETE_CONFIRM') or define('TEXT_MITS_CDB_RESTORE_DELETE_CONFIRM', 'Deze back-up echt verwijderen?\\n\\n%s');
defined('TEXT_MITS_CDB_RESTORE_DELETE_TABLE_CONFIRM') or define('TEXT_MITS_CDB_RESTORE_DELETE_TABLE_CONFIRM', 'Dit tabelbestand echt verwijderen?\\n\\n%s');
defined('TEXT_MITS_CDB_RESTORE_DELETE_SUCCESS') or define('TEXT_MITS_CDB_RESTORE_DELETE_SUCCESS', 'Back-up verwijderd: %s');
defined('TEXT_MITS_CDB_RESTORE_DELETE_ERROR') or define('TEXT_MITS_CDB_RESTORE_DELETE_ERROR', 'Back-up kon niet worden verwijderd: %s');
defined('TEXT_MITS_CDB_RESTORE_DELETE_TABLE_SUCCESS') or define('TEXT_MITS_CDB_RESTORE_DELETE_TABLE_SUCCESS', 'Tabelbestand verwijderd: %s');
defined('TEXT_MITS_CDB_RESTORE_DELETE_TABLE_ERROR') or define('TEXT_MITS_CDB_RESTORE_DELETE_TABLE_ERROR', 'Tabelbestand kon niet worden verwijderd: %s');
defined('TEXT_MITS_CDB_RESTORE_DELETE_TABLE_FOLDER_EMPTY') or define('TEXT_MITS_CDB_RESTORE_DELETE_TABLE_FOLDER_EMPTY', 'De tabelback-upmap bevatte geen verdere tabelbestanden en is verwijderd.');
defined('TEXT_MITS_CDB_RESTORE_DELETE_RESULT_SUCCESS') or define('TEXT_MITS_CDB_RESTORE_DELETE_RESULT_SUCCESS', 'Back-up verwijderd');
defined('TEXT_MITS_CDB_RESTORE_DELETE_RESULT_ERROR') or define('TEXT_MITS_CDB_RESTORE_DELETE_RESULT_ERROR', 'Verwijderen mislukt');
defined('TEXT_MITS_CDB_RESTORE_DELETE_RESULT_SUBTITLE') or define('TEXT_MITS_CDB_RESTORE_DELETE_RESULT_SUBTITLE', 'Het resultaat van de verwijderactie is gelogd.');
defined('TEXT_MITS_CDB_SQL_ERROR_FILE_OPERATION') or define('TEXT_MITS_CDB_SQL_ERROR_FILE_OPERATION', 'SQL-instructies met LOAD_FILE, LOAD DATA, INTO OUTFILE of INTO DUMPFILE worden om veiligheidsredenen niet uitgevoerd.');
defined('TEXT_MITS_CDB_RESTORE_SELECT_ALL') or define('TEXT_MITS_CDB_RESTORE_SELECT_ALL', 'Alles selecteren / deselecteren');
defined('TEXT_MITS_CDB_RESTORE_SELECT_BACKUP') or define('TEXT_MITS_CDB_RESTORE_SELECT_BACKUP', 'Backup selecteren: %s');
defined('TEXT_MITS_CDB_RESTORE_DELETE_SELECTED') or define('TEXT_MITS_CDB_RESTORE_DELETE_SELECTED', 'Geselecteerde verwijderen');
defined('TEXT_MITS_CDB_RESTORE_DELETE_SELECTED_CONFIRM') or define('TEXT_MITS_CDB_RESTORE_DELETE_SELECTED_CONFIRM', 'Geselecteerde backups echt definitief verwijderen?');
defined('TEXT_MITS_CDB_RESTORE_DELETE_SELECTED_INFO') or define('TEXT_MITS_CDB_RESTORE_DELETE_SELECTED_INFO', 'Geselecteerde tabelbackups worden volledig inclusief alle tabelbestanden verwijderd.');
defined('TEXT_MITS_CDB_RESTORE_DELETE_SELECTED_NONE') or define('TEXT_MITS_CDB_RESTORE_DELETE_SELECTED_NONE', 'Er zijn geen backups geselecteerd om te verwijderen.');
defined('TEXT_MITS_CDB_RESTORE_DELETE_SELECTED_SUMMARY') or define('TEXT_MITS_CDB_RESTORE_DELETE_SELECTED_SUMMARY', '%s backup(s) verwijderd, %s mislukt.');

defined('TEXT_MITS_CDB_BACKUP_TABLES_SELECT_ALL') or define('TEXT_MITS_CDB_BACKUP_TABLES_SELECT_ALL', 'Alle tabellen selecteren / deselecteren');
