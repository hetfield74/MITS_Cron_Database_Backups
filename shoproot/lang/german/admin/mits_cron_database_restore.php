<?php
/**
 * --------------------------------------------------------------
 * File: mits_cron_database_restore.php
 * Date: 12.06.2026
 *
 * Released under the GNU General Public License
 * --------------------------------------------------------------
 */

defined('HEADING_TITLE') or define('HEADING_TITLE', 'MITS Datenbank-Werkzeuge');
defined('TEXT_MITS_CDB_RESTORE_PAGE_TITLE') or define('TEXT_MITS_CDB_RESTORE_PAGE_TITLE', 'MITS Cron Database Backups - Datenbank-Werkzeuge');
defined('TEXT_MITS_CDB_RESTORE_WARNING_TITLE') or define('TEXT_MITS_CDB_RESTORE_WARNING_TITLE', 'Achtung: riskante Aktion');
defined('TEXT_MITS_CDB_RESTORE_WARNING') or define('TEXT_MITS_CDB_RESTORE_WARNING', 'Eine R&uuml;cksicherung ersetzt Daten in der aktuellen Shopdatenbank. F&uuml;hren Sie diese Aktion nur aus, wenn Sie sicher sind, dass das gew&auml;hlte Backup zur aktuellen Shopversion und Datenbank passt. Vor dem Import wird automatisch ein zus&auml;tzliches Sicherheitsbackup der aktuellen Datenbank erstellt.');
defined('TEXT_MITS_CDB_RESTORE_INTRO') or define('TEXT_MITS_CDB_RESTORE_INTRO', 'W&auml;hlen Sie ein vorhandenes SQL-, SQL.GZ- oder Tabellen-Backup aus. Zus&auml;tzlich k&ouml;nnen Sie manuelle Sicherungen erstellen, einzelne Backup-Dateien herunterladen oder l&ouml;schen und SQL direkt ausf&uuml;hren.');
defined('TEXT_MITS_CDB_RESTORE_NO_BACKUPS') or define('TEXT_MITS_CDB_RESTORE_NO_BACKUPS', 'Es wurden keine passenden .sql, .sql.gz oder Tabellen-Backups gefunden.');
defined('TEXT_MITS_CDB_RESTORE_DIR_MODULE') or define('TEXT_MITS_CDB_RESTORE_DIR_MODULE', 'MITS Backupordner');
defined('TEXT_MITS_CDB_RESTORE_DIR_ADMIN') or define('TEXT_MITS_CDB_RESTORE_DIR_ADMIN', 'Shop Backupordner');
defined('TEXT_MITS_CDB_RESTORE_FILE') or define('TEXT_MITS_CDB_RESTORE_FILE', 'Datei');
defined('TEXT_MITS_CDB_RESTORE_DIRECTORY') or define('TEXT_MITS_CDB_RESTORE_DIRECTORY', 'Ordner');
defined('TEXT_MITS_CDB_RESTORE_SIZE') or define('TEXT_MITS_CDB_RESTORE_SIZE', 'Gr&ouml;&szlig;e');
defined('TEXT_MITS_CDB_RESTORE_DATE') or define('TEXT_MITS_CDB_RESTORE_DATE', 'Datum');
defined('TEXT_MITS_CDB_RESTORE_TYPE') or define('TEXT_MITS_CDB_RESTORE_TYPE', 'Typ');
defined('TEXT_MITS_CDB_RESTORE_DOWNLOAD') or define('TEXT_MITS_CDB_RESTORE_DOWNLOAD', 'Herunterladen');
defined('TEXT_MITS_CDB_RESTORE_CONFIRM_TITLE') or define('TEXT_MITS_CDB_RESTORE_CONFIRM_TITLE', 'R&uuml;cksicherung best&auml;tigen');
defined('TEXT_MITS_CDB_RESTORE_CONFIRM_WARNING') or define('TEXT_MITS_CDB_RESTORE_CONFIRM_WARNING', 'Nach dem Start sollte der Browser nicht geschlossen werden. Der Shop kann w&auml;hrend des Imports kurzzeitig nicht korrekt reagieren.');
defined('TEXT_MITS_CDB_RESTORE_CONFIRM_INPUT') or define('TEXT_MITS_CDB_RESTORE_CONFIRM_INPUT', 'Geben Sie zur Best&auml;tigung exakt <strong>RESTORE</strong> ein:');
defined('TEXT_MITS_CDB_RESTORE_RESULT_SUCCESS') or define('TEXT_MITS_CDB_RESTORE_RESULT_SUCCESS', 'R&uuml;cksicherung abgeschlossen');
defined('TEXT_MITS_CDB_RESTORE_RESULT_ERROR') or define('TEXT_MITS_CDB_RESTORE_RESULT_ERROR', 'R&uuml;cksicherung fehlgeschlagen');
defined('TEXT_MITS_CDB_RESTORE_SAFETY_BACKUP_CREATED') or define('TEXT_MITS_CDB_RESTORE_SAFETY_BACKUP_CREATED', 'Sicherheitsbackup vor der R&uuml;cksicherung wurde erstellt: %s');
defined('TEXT_MITS_CDB_RESTORE_GZ_UNPACKED') or define('TEXT_MITS_CDB_RESTORE_GZ_UNPACKED', 'GZIP-Backup wurde tempor&auml;r entpackt.');
defined('TEXT_MITS_CDB_RESTORE_SUCCESS') or define('TEXT_MITS_CDB_RESTORE_SUCCESS', 'Backup %s wurde erfolgreich in die Datenbank importiert.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_EXEC_DISABLED') or define('TEXT_MITS_CDB_RESTORE_ERROR_EXEC_DISABLED', 'Die PHP-Funktion exec() ist deaktiviert. Die schnelle R&uuml;cksicherung per mysql ist dadurch nicht m&ouml;glich.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_LOCKED') or define('TEXT_MITS_CDB_RESTORE_ERROR_LOCKED', 'Es l&auml;uft bereits eine R&uuml;cksicherung oder eine alte Lockdatei ist vorhanden. Bitte sp&auml;ter erneut versuchen.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_TOKEN') or define('TEXT_MITS_CDB_RESTORE_ERROR_TOKEN', 'Der Sicherheits-Token ist ung&uuml;ltig. Bitte Seite neu laden und erneut versuchen.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_INVALID_FILE') or define('TEXT_MITS_CDB_RESTORE_ERROR_INVALID_FILE', 'Die gew&auml;hlte Backup-Datei ist ung&uuml;ltig oder nicht mehr vorhanden.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_CONFIRM') or define('TEXT_MITS_CDB_RESTORE_ERROR_CONFIRM', 'Die Best&auml;tigung ist falsch. Die R&uuml;cksicherung wurde nicht gestartet.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_SAFETY_DIR') or define('TEXT_MITS_CDB_RESTORE_ERROR_SAFETY_DIR', 'Der Backupordner ist nicht beschreibbar. Das Sicherheitsbackup vor der R&uuml;cksicherung konnte nicht erstellt werden.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_SAFETY_BACKUP') or define('TEXT_MITS_CDB_RESTORE_ERROR_SAFETY_BACKUP', 'Das Sicherheitsbackup vor der R&uuml;cksicherung konnte nicht erstellt werden. Der Import wurde abgebrochen.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_ZLIB') or define('TEXT_MITS_CDB_RESTORE_ERROR_ZLIB', 'Die PHP-Zlib-Erweiterung ist nicht aktiv. SQL.GZ-Dateien k&ouml;nnen nicht entpackt werden.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_GZ_OPEN') or define('TEXT_MITS_CDB_RESTORE_ERROR_GZ_OPEN', 'Die SQL.GZ-Datei konnte nicht ge&ouml;ffnet werden.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_GZ_READ') or define('TEXT_MITS_CDB_RESTORE_ERROR_GZ_READ', 'Die SQL.GZ-Datei konnte nicht vollst&auml;ndig gelesen werden.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_TEMPFILE') or define('TEXT_MITS_CDB_RESTORE_ERROR_TEMPFILE', 'Die tempor&auml;re SQL-Datei konnte nicht erstellt werden.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_IMPORT') or define('TEXT_MITS_CDB_RESTORE_ERROR_IMPORT', 'Der Import von %s ist fehlgeschlagen.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_DOWNLOAD') or define('TEXT_MITS_CDB_RESTORE_ERROR_DOWNLOAD', 'Die Backup-Datei konnte nicht heruntergeladen werden.');

defined('TEXT_MITS_CDB_RESTORE_ERROR_ZIPARCHIVE') or define('TEXT_MITS_CDB_RESTORE_ERROR_ZIPARCHIVE', 'Die PHP-Erweiterung ZipArchive ist nicht aktiv. ZIP-Backups k&ouml;nnen deshalb nicht entpackt werden.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_ZIP_OPEN') or define('TEXT_MITS_CDB_RESTORE_ERROR_ZIP_OPEN', 'Die ZIP-Datei konnte nicht ge&ouml;ffnet werden.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_ZIP_EMPTY') or define('TEXT_MITS_CDB_RESTORE_ERROR_ZIP_EMPTY', 'In der ZIP-Datei wurden keine SQL-Dateien gefunden.');
defined('TEXT_MITS_CDB_RESTORE_ZIP_UNPACKED') or define('TEXT_MITS_CDB_RESTORE_ZIP_UNPACKED', 'ZIP-Backup wurde tempor&auml;r entpackt und f&uuml;r den Import vorbereitet.');
defined('TEXT_MITS_CDB_RESTORE_RELOGIN_TITLE') or define('TEXT_MITS_CDB_RESTORE_RELOGIN_TITLE', 'Admin-Session wurde beendet');
defined('TEXT_MITS_CDB_RESTORE_RELOGIN_TEXT') or define('TEXT_MITS_CDB_RESTORE_RELOGIN_TEXT', 'Die R&uuml;cksicherung wurde abgeschlossen. Aus Sicherheitsgr&uuml;nden wurde die aktuelle Admin-Session beendet. Bitte melden Sie sich erneut im Adminbereich an.');
defined('TEXT_MITS_CDB_RESTORE_RELOGIN_BUTTON') or define('TEXT_MITS_CDB_RESTORE_RELOGIN_BUTTON', 'Zum Admin-Login');
defined('TEXT_MITS_CDB_RESTORE_RELOGIN_NOTE') or define('TEXT_MITS_CDB_RESTORE_RELOGIN_NOTE', 'Das ist beabsichtigt, da Session-, Adminbenutzer- und Berechtigungsdaten durch die R&uuml;cksicherung wieder auf den Stand des Backups gesetzt worden sein k&ouml;nnen.');
defined('TEXT_MITS_CDB_RESTORE_HERO_TEXT') or define('TEXT_MITS_CDB_RESTORE_HERO_TEXT', 'Datenbank sichern, Backups herunterladen, Tabellen gezielt wiederherstellen, alte Sicherungen entfernen und SQL-Abfragen direkt ausf&uuml;hren. Hinweis: Vor einer R&uuml;cksicherung wird automatisch ein Sicherheitsbackup erstellt.');
defined('TEXT_MITS_CDB_RESTORE_MODULE_SETTINGS') or define('TEXT_MITS_CDB_RESTORE_MODULE_SETTINGS', 'Moduleinstellungen');
defined('TEXT_MITS_CDB_RESTORE_REFRESH') or define('TEXT_MITS_CDB_RESTORE_REFRESH', 'Liste aktualisieren');
defined('TEXT_MITS_CDB_RESTORE_STAT_BACKUPS') or define('TEXT_MITS_CDB_RESTORE_STAT_BACKUPS', 'Backups');
defined('TEXT_MITS_CDB_RESTORE_STAT_COMPRESSED') or define('TEXT_MITS_CDB_RESTORE_STAT_COMPRESSED', '%s komprimiert');
defined('TEXT_MITS_CDB_RESTORE_STAT_TOTAL_SIZE') or define('TEXT_MITS_CDB_RESTORE_STAT_TOTAL_SIZE', 'Gesamtgr&ouml;&szlig;e');
defined('TEXT_MITS_CDB_RESTORE_STAT_TOTAL_SIZE_META') or define('TEXT_MITS_CDB_RESTORE_STAT_TOTAL_SIZE_META', 'alle gefundenen Dateien');
defined('TEXT_MITS_CDB_RESTORE_STAT_LATEST') or define('TEXT_MITS_CDB_RESTORE_STAT_LATEST', 'Letztes Backup');
defined('TEXT_MITS_CDB_RESTORE_STAT_LATEST_META') or define('TEXT_MITS_CDB_RESTORE_STAT_LATEST_META', 'neueste Datei zuerst');
defined('TEXT_MITS_CDB_RESTORE_STAT_SYSTEM') or define('TEXT_MITS_CDB_RESTORE_STAT_SYSTEM', 'Systemstatus');
defined('TEXT_MITS_CDB_RESTORE_STAT_SYSTEM_META') or define('TEXT_MITS_CDB_RESTORE_STAT_SYSTEM_META', 'Import per Server-Client');
defined('TEXT_MITS_CDB_RESTORE_EXEC_AVAILABLE') or define('TEXT_MITS_CDB_RESTORE_EXEC_AVAILABLE', 'mysql bereit');
defined('TEXT_MITS_CDB_RESTORE_EXEC_NOT_AVAILABLE') or define('TEXT_MITS_CDB_RESTORE_EXEC_NOT_AVAILABLE', 'exec deaktiviert');
defined('TEXT_MITS_CDB_RESTORE_RESULT_SUBTITLE') or define('TEXT_MITS_CDB_RESTORE_RESULT_SUBTITLE', 'Das Ergebnis der R&uuml;cksicherung wurde protokolliert.');
defined('TEXT_MITS_CDB_RESTORE_CONFIRM_SUBTITLE') or define('TEXT_MITS_CDB_RESTORE_CONFIRM_SUBTITLE', 'Bitte pr&uuml;fen Sie die Datei und best&auml;tigen Sie die R&uuml;cksicherung bewusst.');
defined('TEXT_MITS_CDB_RESTORE_PROCESS_TITLE') or define('TEXT_MITS_CDB_RESTORE_PROCESS_TITLE', 'Ablauf der R&uuml;cksicherung');
defined('TEXT_MITS_CDB_RESTORE_PROCESS_SUBTITLE') or define('TEXT_MITS_CDB_RESTORE_PROCESS_SUBTITLE', 'Diese Sicherheitsstufen werden vor und w&auml;hrend des Imports ausgef&uuml;hrt.');
defined('TEXT_MITS_CDB_RESTORE_STEP_BACKUP') or define('TEXT_MITS_CDB_RESTORE_STEP_BACKUP', 'Aktuelle Datenbank wird als Sicherheitsbackup gesichert.');
defined('TEXT_MITS_CDB_RESTORE_STEP_UNPACK') or define('TEXT_MITS_CDB_RESTORE_STEP_UNPACK', 'SQL.GZ-Dateien und Tabellen-Backups werden bei Bedarf tempor&auml;r entpackt.');
defined('TEXT_MITS_CDB_RESTORE_STEP_IMPORT') or define('TEXT_MITS_CDB_RESTORE_STEP_IMPORT', 'Der Import erfolgt per mysql-Client des Servers.');
defined('TEXT_MITS_CDB_RESTORE_STEP_RELOGIN') or define('TEXT_MITS_CDB_RESTORE_STEP_RELOGIN', 'Nach erfolgreicher R&uuml;cksicherung wird die Admin-Session beendet.');
defined('TEXT_MITS_CDB_RESTORE_CARD_BACKUPS') or define('TEXT_MITS_CDB_RESTORE_CARD_BACKUPS', 'Verf&uuml;gbare Backups');
defined('TEXT_MITS_CDB_RESTORE_SAFETY_SUBTITLE') or define('TEXT_MITS_CDB_RESTORE_SAFETY_SUBTITLE', 'Bitte vor dem Start genau pr&uuml;fen.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_SAFETY_GZIP') or define('TEXT_MITS_CDB_RESTORE_ERROR_SAFETY_GZIP', 'GZIP-Komprimierung des Sicherheitsbackups ist fehlgeschlagen. Die R&uuml;cksicherung wurde nicht gestartet.');
defined('TEXT_MITS_CDB_RESTORE_TABLE_DOWNLOADS_TOGGLE') or define('TEXT_MITS_CDB_RESTORE_TABLE_DOWNLOADS_TOGGLE', 'Einzelne Tabellendateien anzeigen');
defined('TEXT_MITS_CDB_RESTORE_TABLE_DOWNLOADS_COUNT') or define('TEXT_MITS_CDB_RESTORE_TABLE_DOWNLOADS_COUNT', '%s Dateien');
defined('TEXT_MITS_CDB_RESTORE_TABLE_DOWNLOADS_INFO') or define('TEXT_MITS_CDB_RESTORE_TABLE_DOWNLOADS_INFO', 'Jede Tabellendatei geh&ouml;rt zu diesem Tabellen-Backup und kann separat heruntergeladen, wiederhergestellt oder gel&ouml;scht werden.');
defined('TEXT_MITS_CDB_RESTORE_TABLE_FILE_RESTORE') or define('TEXT_MITS_CDB_RESTORE_TABLE_FILE_RESTORE', 'Diese Tabellendatei wiederherstellen.');
defined('TEXT_MITS_CDB_RESTORE_TABLE_PRESELECTED_INFO') or define('TEXT_MITS_CDB_RESTORE_TABLE_PRESELECTED_INFO', 'Vorausgew&auml;hlt ist nur die Tabellendatei: %s');

defined('TEXT_MITS_CDB_SQL_TITLE') or define('TEXT_MITS_CDB_SQL_TITLE', 'SQL direkt ausf&uuml;hren');
defined('TEXT_MITS_CDB_SQL_SUBTITLE') or define('TEXT_MITS_CDB_SQL_SUBTITLE', 'Query-Box f&uuml;r einzelne SQL-Anweisungen.');
defined('TEXT_MITS_CDB_SQL_WARNING_TITLE') or define('TEXT_MITS_CDB_SQL_WARNING_TITLE', 'Direkter Datenbankzugriff');
defined('TEXT_MITS_CDB_SQL_WARNING') or define('TEXT_MITS_CDB_SQL_WARNING', 'SQL-Code wird direkt auf der aktuellen Shopdatenbank ausgef&uuml;hrt. Erstellen Sie vorher ein Backup. Schreibende Anweisungen m&uuml;ssen unten zus&auml;tzlich erlaubt werden.');
defined('TEXT_MITS_CDB_SQL_CODE') or define('TEXT_MITS_CDB_SQL_CODE', 'SQL-Code');
defined('TEXT_MITS_CDB_SQL_ROW_LIMIT') or define('TEXT_MITS_CDB_SQL_ROW_LIMIT', 'Maximal angezeigte Ergebniszeilen');
defined('TEXT_MITS_CDB_SQL_CONFIRM_WRITE') or define('TEXT_MITS_CDB_SQL_CONFIRM_WRITE', 'Schreibende SQL-Anweisungen wie INSERT, UPDATE, DELETE, ALTER oder DROP erlauben.');
defined('TEXT_MITS_CDB_SQL_RUN') or define('TEXT_MITS_CDB_SQL_RUN', 'SQL ausf&uuml;hren');
defined('TEXT_MITS_CDB_SQL_RESULT_SUCCESS') or define('TEXT_MITS_CDB_SQL_RESULT_SUCCESS', 'SQL ausgef&uuml;hrt');
defined('TEXT_MITS_CDB_SQL_RESULT_ERROR') or define('TEXT_MITS_CDB_SQL_RESULT_ERROR', 'SQL fehlgeschlagen');
defined('TEXT_MITS_CDB_SQL_RESULT_SUBTITLE') or define('TEXT_MITS_CDB_SQL_RESULT_SUBTITLE', 'Das Ergebnis der SQL-Ausf&uuml;hrung wurde protokolliert.');
defined('TEXT_MITS_CDB_SQL_RESULT_STATEMENT_TITLE') or define('TEXT_MITS_CDB_SQL_RESULT_STATEMENT_TITLE', 'SQL #%s');
defined('TEXT_MITS_CDB_SQL_STATUS_OK') or define('TEXT_MITS_CDB_SQL_STATUS_OK', 'OK');
defined('TEXT_MITS_CDB_SQL_STATUS_ERROR') or define('TEXT_MITS_CDB_SQL_STATUS_ERROR', 'Fehler');
defined('TEXT_MITS_CDB_SQL_RESULT_EMPTY') or define('TEXT_MITS_CDB_SQL_RESULT_EMPTY', 'Die Abfrage lieferte keine Datens&auml;tze.');
defined('TEXT_MITS_CDB_SQL_ERROR_EMPTY') or define('TEXT_MITS_CDB_SQL_ERROR_EMPTY', 'Es wurde kein SQL-Code eingegeben.');
defined('TEXT_MITS_CDB_SQL_ERROR_CONNECTION') or define('TEXT_MITS_CDB_SQL_ERROR_CONNECTION', 'Die Datenbankverbindung ist nicht verf&uuml;gbar.');
defined('TEXT_MITS_CDB_SQL_ERROR_WRITE_CONFIRM') or define('TEXT_MITS_CDB_SQL_ERROR_WRITE_CONFIRM', 'Schreibende SQL-Anweisungen wurden nicht ausgef&uuml;hrt, weil die Freigabe nicht aktiviert wurde.');
defined('TEXT_MITS_CDB_SQL_ERROR_STATEMENT') or define('TEXT_MITS_CDB_SQL_ERROR_STATEMENT', 'SQL #%s fehlgeschlagen: %s');
defined('TEXT_MITS_CDB_SQL_SUCCESS_ROWS') or define('TEXT_MITS_CDB_SQL_SUCCESS_ROWS', '%s Datens&auml;tze gefunden, %s angezeigt.');
defined('TEXT_MITS_CDB_SQL_SUCCESS_AFFECTED') or define('TEXT_MITS_CDB_SQL_SUCCESS_AFFECTED', 'SQL-Anweisung ausgef&uuml;hrt. Betroffene Zeilen: %s.');
defined('TEXT_MITS_CDB_SQL_SUCCESS_INSERT_ID') or define('TEXT_MITS_CDB_SQL_SUCCESS_INSERT_ID', 'Insert-ID: %s.');
defined('TEXT_MITS_CDB_SQL_SUCCESS_SUMMARY') or define('TEXT_MITS_CDB_SQL_SUCCESS_SUMMARY', '%s SQL-Anweisung(en) erfolgreich ausgef&uuml;hrt.');
defined('TEXT_MITS_CDB_RESTORE_DELETE') or define('TEXT_MITS_CDB_RESTORE_DELETE', 'L&ouml;schen');
defined('TEXT_MITS_CDB_RESTORE_DELETE_CONFIRM') or define('TEXT_MITS_CDB_RESTORE_DELETE_CONFIRM', 'Dieses Backup wirklich l&ouml;schen?\\n\\n%s');
defined('TEXT_MITS_CDB_RESTORE_DELETE_TABLE_CONFIRM') or define('TEXT_MITS_CDB_RESTORE_DELETE_TABLE_CONFIRM', 'Diese Tabellendatei wirklich l&ouml;schen?\\n\\n%s');
defined('TEXT_MITS_CDB_RESTORE_DELETE_SUCCESS') or define('TEXT_MITS_CDB_RESTORE_DELETE_SUCCESS', 'Backup wurde gel&ouml;scht: %s');
defined('TEXT_MITS_CDB_RESTORE_DELETE_ERROR') or define('TEXT_MITS_CDB_RESTORE_DELETE_ERROR', 'Backup konnte nicht gel&ouml;scht werden: %s');
defined('TEXT_MITS_CDB_RESTORE_DELETE_TABLE_SUCCESS') or define('TEXT_MITS_CDB_RESTORE_DELETE_TABLE_SUCCESS', 'Tabellendatei wurde gel&ouml;scht: %s');
defined('TEXT_MITS_CDB_RESTORE_DELETE_TABLE_ERROR') or define('TEXT_MITS_CDB_RESTORE_DELETE_TABLE_ERROR', 'Tabellendatei konnte nicht gel&ouml;scht werden: %s');
defined('TEXT_MITS_CDB_RESTORE_DELETE_TABLE_FOLDER_EMPTY') or define('TEXT_MITS_CDB_RESTORE_DELETE_TABLE_FOLDER_EMPTY', 'Der Tabellen-Backupordner enthielt keine weiteren Tabellendateien und wurde entfernt.');
defined('TEXT_MITS_CDB_RESTORE_DELETE_RESULT_SUCCESS') or define('TEXT_MITS_CDB_RESTORE_DELETE_RESULT_SUCCESS', 'Backup gel&ouml;scht');
defined('TEXT_MITS_CDB_RESTORE_DELETE_RESULT_ERROR') or define('TEXT_MITS_CDB_RESTORE_DELETE_RESULT_ERROR', 'L&ouml;schen fehlgeschlagen');
defined('TEXT_MITS_CDB_RESTORE_DELETE_RESULT_SUBTITLE') or define('TEXT_MITS_CDB_RESTORE_DELETE_RESULT_SUBTITLE', 'Das Ergebnis der L&ouml;schaktion wurde protokolliert.');
defined('TEXT_MITS_CDB_SQL_ERROR_FILE_OPERATION') or define('TEXT_MITS_CDB_SQL_ERROR_FILE_OPERATION', 'SQL-Anweisungen mit LOAD_FILE, LOAD DATA, INTO OUTFILE oder INTO DUMPFILE werden aus Sicherheitsgr&uuml;nden nicht ausgef&uuml;hrt.');
defined('TEXT_MITS_CDB_RESTORE_SELECT_ALL') or define('TEXT_MITS_CDB_RESTORE_SELECT_ALL', 'Alle ausw&auml;hlen / abw&auml;hlen');
defined('TEXT_MITS_CDB_RESTORE_SELECT_BACKUP') or define('TEXT_MITS_CDB_RESTORE_SELECT_BACKUP', 'Sicherung ausw&auml;hlen: %s');
defined('TEXT_MITS_CDB_RESTORE_DELETE_SELECTED') or define('TEXT_MITS_CDB_RESTORE_DELETE_SELECTED', 'Ausgew&auml;hlte l&ouml;schen');
defined('TEXT_MITS_CDB_RESTORE_DELETE_SELECTED_CONFIRM') or define('TEXT_MITS_CDB_RESTORE_DELETE_SELECTED_CONFIRM', 'Ausgew&auml;hlte Sicherungen wirklich endg&uuml;ltig l&ouml;schen?');
defined('TEXT_MITS_CDB_RESTORE_DELETE_SELECTED_INFO') or define('TEXT_MITS_CDB_RESTORE_DELETE_SELECTED_INFO', 'Ausgew&auml;hlte Tabellen-Backups werden komplett inklusive aller enthaltenen Tabellendateien gel&ouml;scht.');
defined('TEXT_MITS_CDB_RESTORE_DELETE_SELECTED_NONE') or define('TEXT_MITS_CDB_RESTORE_DELETE_SELECTED_NONE', 'Es wurden keine Sicherungen zum L&ouml;schen ausgew&auml;hlt.');
defined('TEXT_MITS_CDB_RESTORE_DELETE_SELECTED_SUMMARY') or define('TEXT_MITS_CDB_RESTORE_DELETE_SELECTED_SUMMARY', '%s Sicherung(en) gel&ouml;scht, %s fehlgeschlagen.');

defined('TEXT_MITS_CDB_BACKUP_TABLES_SELECT_ALL') or define('TEXT_MITS_CDB_BACKUP_TABLES_SELECT_ALL', 'Alle Tabellen ausw&auml;hlen / abw&auml;hlen');
