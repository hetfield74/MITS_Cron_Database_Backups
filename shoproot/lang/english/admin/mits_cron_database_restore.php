<?php
/**
 * --------------------------------------------------------------
 * File: mits_cron_database_restore.php
 * Date: 12.06.2026
 *
 * Released under the GNU General Public License
 * --------------------------------------------------------------
 */

defined('HEADING_TITLE') or define('HEADING_TITLE', 'MITS Database Tools');
defined('TEXT_MITS_CDB_RESTORE_PAGE_TITLE') or define('TEXT_MITS_CDB_RESTORE_PAGE_TITLE', 'MITS Cron Database Backups - Database Tools');
defined('TEXT_MITS_CDB_RESTORE_WARNING_TITLE') or define('TEXT_MITS_CDB_RESTORE_WARNING_TITLE', 'Attention: risky action');
defined('TEXT_MITS_CDB_RESTORE_WARNING') or define('TEXT_MITS_CDB_RESTORE_WARNING', 'A restore replaces data in the current shop database. Run this action only if you are sure that the selected backup matches the current shop version and database. Before the import, an additional safety backup of the current database is created automatically.');
defined('TEXT_MITS_CDB_RESTORE_INTRO') or define('TEXT_MITS_CDB_RESTORE_INTRO', 'Select an existing SQL, SQL.GZ or table backup. You can also create manual backups, download or delete individual backup files and run SQL directly.');
defined('TEXT_MITS_CDB_RESTORE_NO_BACKUPS') or define('TEXT_MITS_CDB_RESTORE_NO_BACKUPS', 'No matching .sql, .sql.gz or .zip backups were found.');
defined('TEXT_MITS_CDB_RESTORE_DIR_MODULE') or define('TEXT_MITS_CDB_RESTORE_DIR_MODULE', 'MITS backup directory');
defined('TEXT_MITS_CDB_RESTORE_DIR_ADMIN') or define('TEXT_MITS_CDB_RESTORE_DIR_ADMIN', 'Shop backup directory');
defined('TEXT_MITS_CDB_RESTORE_FILE') or define('TEXT_MITS_CDB_RESTORE_FILE', 'File');
defined('TEXT_MITS_CDB_RESTORE_DIRECTORY') or define('TEXT_MITS_CDB_RESTORE_DIRECTORY', 'Directory');
defined('TEXT_MITS_CDB_RESTORE_SIZE') or define('TEXT_MITS_CDB_RESTORE_SIZE', 'Size');
defined('TEXT_MITS_CDB_RESTORE_DATE') or define('TEXT_MITS_CDB_RESTORE_DATE', 'Date');
defined('TEXT_MITS_CDB_RESTORE_TYPE') or define('TEXT_MITS_CDB_RESTORE_TYPE', 'Type');
defined('TEXT_MITS_CDB_RESTORE_DOWNLOAD') or define('TEXT_MITS_CDB_RESTORE_DOWNLOAD', 'Download');
defined('TEXT_MITS_CDB_RESTORE_CONFIRM_TITLE') or define('TEXT_MITS_CDB_RESTORE_CONFIRM_TITLE', 'Confirm restore');
defined('TEXT_MITS_CDB_RESTORE_CONFIRM_WARNING') or define('TEXT_MITS_CDB_RESTORE_CONFIRM_WARNING', 'Do not close the browser after starting the restore. The shop may not respond correctly during the import.');
defined('TEXT_MITS_CDB_RESTORE_CONFIRM_INPUT') or define('TEXT_MITS_CDB_RESTORE_CONFIRM_INPUT', 'Enter exactly <strong>RESTORE</strong> to confirm:');
defined('TEXT_MITS_CDB_RESTORE_RESULT_SUCCESS') or define('TEXT_MITS_CDB_RESTORE_RESULT_SUCCESS', 'Restore completed');
defined('TEXT_MITS_CDB_RESTORE_RESULT_ERROR') or define('TEXT_MITS_CDB_RESTORE_RESULT_ERROR', 'Restore failed');
defined('TEXT_MITS_CDB_RESTORE_SAFETY_BACKUP_CREATED') or define('TEXT_MITS_CDB_RESTORE_SAFETY_BACKUP_CREATED', 'Safety backup before restore was created: %s');
defined('TEXT_MITS_CDB_RESTORE_GZ_UNPACKED') or define('TEXT_MITS_CDB_RESTORE_GZ_UNPACKED', 'GZIP backup was unpacked temporarily.');
defined('TEXT_MITS_CDB_RESTORE_SUCCESS') or define('TEXT_MITS_CDB_RESTORE_SUCCESS', 'Backup %s was imported into the database successfully.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_EXEC_DISABLED') or define('TEXT_MITS_CDB_RESTORE_ERROR_EXEC_DISABLED', 'The PHP function exec() is disabled. Fast restore using mysql is not possible.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_LOCKED') or define('TEXT_MITS_CDB_RESTORE_ERROR_LOCKED', 'A restore is already running or an old lock file exists. Please try again later.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_TOKEN') or define('TEXT_MITS_CDB_RESTORE_ERROR_TOKEN', 'The security token is invalid. Please reload the page and try again.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_INVALID_FILE') or define('TEXT_MITS_CDB_RESTORE_ERROR_INVALID_FILE', 'The selected backup file is invalid or no longer available.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_CONFIRM') or define('TEXT_MITS_CDB_RESTORE_ERROR_CONFIRM', 'The confirmation is wrong. The restore was not started.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_SAFETY_DIR') or define('TEXT_MITS_CDB_RESTORE_ERROR_SAFETY_DIR', 'The backup directory is not writable. The safety backup before restore could not be created.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_SAFETY_BACKUP') or define('TEXT_MITS_CDB_RESTORE_ERROR_SAFETY_BACKUP', 'The safety backup before restore could not be created. The import was aborted.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_ZLIB') or define('TEXT_MITS_CDB_RESTORE_ERROR_ZLIB', 'The PHP zlib extension is not active. SQL.GZ files cannot be unpacked.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_GZ_OPEN') or define('TEXT_MITS_CDB_RESTORE_ERROR_GZ_OPEN', 'The SQL.GZ file could not be opened.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_GZ_READ') or define('TEXT_MITS_CDB_RESTORE_ERROR_GZ_READ', 'The SQL.GZ file could not be read completely.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_TEMPFILE') or define('TEXT_MITS_CDB_RESTORE_ERROR_TEMPFILE', 'The temporary SQL file could not be created.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_IMPORT') or define('TEXT_MITS_CDB_RESTORE_ERROR_IMPORT', 'Import of %s failed.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_DOWNLOAD') or define('TEXT_MITS_CDB_RESTORE_ERROR_DOWNLOAD', 'The backup file could not be downloaded.');

defined('TEXT_MITS_CDB_RESTORE_ERROR_ZIPARCHIVE') or define('TEXT_MITS_CDB_RESTORE_ERROR_ZIPARCHIVE', 'The PHP ZipArchive extension is not active. ZIP backups cannot be unpacked.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_ZIP_OPEN') or define('TEXT_MITS_CDB_RESTORE_ERROR_ZIP_OPEN', 'The ZIP file could not be opened.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_ZIP_EMPTY') or define('TEXT_MITS_CDB_RESTORE_ERROR_ZIP_EMPTY', 'No SQL files were found in the ZIP file.');
defined('TEXT_MITS_CDB_RESTORE_ZIP_UNPACKED') or define('TEXT_MITS_CDB_RESTORE_ZIP_UNPACKED', 'ZIP backup was unpacked temporarily and prepared for import.');
defined('TEXT_MITS_CDB_RESTORE_RELOGIN_TITLE') or define('TEXT_MITS_CDB_RESTORE_RELOGIN_TITLE', 'Admin session has been closed');
defined('TEXT_MITS_CDB_RESTORE_RELOGIN_TEXT') or define('TEXT_MITS_CDB_RESTORE_RELOGIN_TEXT', 'The restore has been completed. For security reasons, the current admin session has been closed. Please log in to the admin area again.');
defined('TEXT_MITS_CDB_RESTORE_RELOGIN_BUTTON') or define('TEXT_MITS_CDB_RESTORE_RELOGIN_BUTTON', 'Go to admin login');
defined('TEXT_MITS_CDB_RESTORE_RELOGIN_NOTE') or define('TEXT_MITS_CDB_RESTORE_RELOGIN_NOTE', 'This is intentional because session, admin user and permission data may have been restored to the state of the backup.');
defined('TEXT_MITS_CDB_RESTORE_HERO_TEXT') or define('TEXT_MITS_CDB_RESTORE_HERO_TEXT', 'Create database backups, download backups, restore selected tables, remove old backups and run SQL queries directly. Note: A safety backup is created automatically before a restore.');
defined('TEXT_MITS_CDB_RESTORE_MODULE_SETTINGS') or define('TEXT_MITS_CDB_RESTORE_MODULE_SETTINGS', 'Module settings');
defined('TEXT_MITS_CDB_RESTORE_REFRESH') or define('TEXT_MITS_CDB_RESTORE_REFRESH', 'Refresh list');
defined('TEXT_MITS_CDB_RESTORE_STAT_BACKUPS') or define('TEXT_MITS_CDB_RESTORE_STAT_BACKUPS', 'Backups');
defined('TEXT_MITS_CDB_RESTORE_STAT_COMPRESSED') or define('TEXT_MITS_CDB_RESTORE_STAT_COMPRESSED', '%s compressed');
defined('TEXT_MITS_CDB_RESTORE_STAT_TOTAL_SIZE') or define('TEXT_MITS_CDB_RESTORE_STAT_TOTAL_SIZE', 'Total size');
defined('TEXT_MITS_CDB_RESTORE_STAT_TOTAL_SIZE_META') or define('TEXT_MITS_CDB_RESTORE_STAT_TOTAL_SIZE_META', 'all discovered files');
defined('TEXT_MITS_CDB_RESTORE_STAT_LATEST') or define('TEXT_MITS_CDB_RESTORE_STAT_LATEST', 'Latest backup');
defined('TEXT_MITS_CDB_RESTORE_STAT_LATEST_META') or define('TEXT_MITS_CDB_RESTORE_STAT_LATEST_META', 'newest file first');
defined('TEXT_MITS_CDB_RESTORE_STAT_SYSTEM') or define('TEXT_MITS_CDB_RESTORE_STAT_SYSTEM', 'System status');
defined('TEXT_MITS_CDB_RESTORE_STAT_SYSTEM_META') or define('TEXT_MITS_CDB_RESTORE_STAT_SYSTEM_META', 'Import via server client');
defined('TEXT_MITS_CDB_RESTORE_EXEC_AVAILABLE') or define('TEXT_MITS_CDB_RESTORE_EXEC_AVAILABLE', 'mysql ready');
defined('TEXT_MITS_CDB_RESTORE_EXEC_NOT_AVAILABLE') or define('TEXT_MITS_CDB_RESTORE_EXEC_NOT_AVAILABLE', 'exec disabled');
defined('TEXT_MITS_CDB_RESTORE_RESULT_SUBTITLE') or define('TEXT_MITS_CDB_RESTORE_RESULT_SUBTITLE', 'The restore result has been logged.');
defined('TEXT_MITS_CDB_RESTORE_CONFIRM_SUBTITLE') or define('TEXT_MITS_CDB_RESTORE_CONFIRM_SUBTITLE', 'Please check the file and consciously confirm the restore.');
defined('TEXT_MITS_CDB_RESTORE_PROCESS_TITLE') or define('TEXT_MITS_CDB_RESTORE_PROCESS_TITLE', 'Restore process');
defined('TEXT_MITS_CDB_RESTORE_PROCESS_SUBTITLE') or define('TEXT_MITS_CDB_RESTORE_PROCESS_SUBTITLE', 'These safety steps are performed before and during the import.');
defined('TEXT_MITS_CDB_RESTORE_STEP_BACKUP') or define('TEXT_MITS_CDB_RESTORE_STEP_BACKUP', 'The current database is saved as a safety backup.');
defined('TEXT_MITS_CDB_RESTORE_STEP_UNPACK') or define('TEXT_MITS_CDB_RESTORE_STEP_UNPACK', 'SQL.GZ files and table backups are unpacked temporarily if required.');
defined('TEXT_MITS_CDB_RESTORE_STEP_IMPORT') or define('TEXT_MITS_CDB_RESTORE_STEP_IMPORT', 'The import is executed using the server mysql client.');
defined('TEXT_MITS_CDB_RESTORE_STEP_RELOGIN') or define('TEXT_MITS_CDB_RESTORE_STEP_RELOGIN', 'After a successful restore the admin session is closed.');
defined('TEXT_MITS_CDB_RESTORE_CARD_BACKUPS') or define('TEXT_MITS_CDB_RESTORE_CARD_BACKUPS', 'Available backups');
defined('TEXT_MITS_CDB_RESTORE_SAFETY_SUBTITLE') or define('TEXT_MITS_CDB_RESTORE_SAFETY_SUBTITLE', 'Please check carefully before starting.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_SAFETY_GZIP') or define('TEXT_MITS_CDB_RESTORE_ERROR_SAFETY_GZIP', 'GZIP compression of the safety backup failed. The restore was not started.');
defined('TEXT_MITS_CDB_RESTORE_TABLE_DOWNLOADS_TOGGLE') or define('TEXT_MITS_CDB_RESTORE_TABLE_DOWNLOADS_TOGGLE', 'Show individual table files');
defined('TEXT_MITS_CDB_RESTORE_TABLE_DOWNLOADS_COUNT') or define('TEXT_MITS_CDB_RESTORE_TABLE_DOWNLOADS_COUNT', '%s files');
defined('TEXT_MITS_CDB_RESTORE_TABLE_DOWNLOADS_INFO') or define('TEXT_MITS_CDB_RESTORE_TABLE_DOWNLOADS_INFO', 'Each table file belongs to this table backup and can be downloaded, restored or deleted separately.');
defined('TEXT_MITS_CDB_RESTORE_TABLE_FILE_RESTORE') or define('TEXT_MITS_CDB_RESTORE_TABLE_FILE_RESTORE', 'Restore this table file.');
defined('TEXT_MITS_CDB_RESTORE_TABLE_PRESELECTED_INFO') or define('TEXT_MITS_CDB_RESTORE_TABLE_PRESELECTED_INFO', 'Only this table file is preselected: %s');

defined('TEXT_MITS_CDB_SQL_TITLE') or define('TEXT_MITS_CDB_SQL_TITLE', 'Run SQL directly');
defined('TEXT_MITS_CDB_SQL_SUBTITLE') or define('TEXT_MITS_CDB_SQL_SUBTITLE', 'Query box for individual SQL statements.');
defined('TEXT_MITS_CDB_SQL_WARNING_TITLE') or define('TEXT_MITS_CDB_SQL_WARNING_TITLE', 'Direct database access');
defined('TEXT_MITS_CDB_SQL_WARNING') or define('TEXT_MITS_CDB_SQL_WARNING', 'SQL code is executed directly on the current shop database. Create a backup first. Writing statements must be explicitly enabled below.');
defined('TEXT_MITS_CDB_SQL_CODE') or define('TEXT_MITS_CDB_SQL_CODE', 'SQL code');
defined('TEXT_MITS_CDB_SQL_ROW_LIMIT') or define('TEXT_MITS_CDB_SQL_ROW_LIMIT', 'Maximum result rows shown');
defined('TEXT_MITS_CDB_SQL_CONFIRM_WRITE') or define('TEXT_MITS_CDB_SQL_CONFIRM_WRITE', 'Explicitly allow writing SQL statements such as INSERT, UPDATE, DELETE, ALTER or DROP.');
defined('TEXT_MITS_CDB_SQL_RUN') or define('TEXT_MITS_CDB_SQL_RUN', 'Run SQL');
defined('TEXT_MITS_CDB_SQL_RESULT_SUCCESS') or define('TEXT_MITS_CDB_SQL_RESULT_SUCCESS', 'SQL executed');
defined('TEXT_MITS_CDB_SQL_RESULT_ERROR') or define('TEXT_MITS_CDB_SQL_RESULT_ERROR', 'SQL failed');
defined('TEXT_MITS_CDB_SQL_RESULT_SUBTITLE') or define('TEXT_MITS_CDB_SQL_RESULT_SUBTITLE', 'The SQL execution result was logged.');
defined('TEXT_MITS_CDB_SQL_RESULT_STATEMENT_TITLE') or define('TEXT_MITS_CDB_SQL_RESULT_STATEMENT_TITLE', 'SQL #%s');
defined('TEXT_MITS_CDB_SQL_STATUS_OK') or define('TEXT_MITS_CDB_SQL_STATUS_OK', 'OK');
defined('TEXT_MITS_CDB_SQL_STATUS_ERROR') or define('TEXT_MITS_CDB_SQL_STATUS_ERROR', 'Error');
defined('TEXT_MITS_CDB_SQL_RESULT_EMPTY') or define('TEXT_MITS_CDB_SQL_RESULT_EMPTY', 'The query returned no records.');
defined('TEXT_MITS_CDB_SQL_ERROR_EMPTY') or define('TEXT_MITS_CDB_SQL_ERROR_EMPTY', 'No SQL code was entered.');
defined('TEXT_MITS_CDB_SQL_ERROR_CONNECTION') or define('TEXT_MITS_CDB_SQL_ERROR_CONNECTION', 'The database connection is not available.');
defined('TEXT_MITS_CDB_SQL_ERROR_WRITE_CONFIRM') or define('TEXT_MITS_CDB_SQL_ERROR_WRITE_CONFIRM', 'Writing SQL statements were not executed because permission was not enabled.');
defined('TEXT_MITS_CDB_SQL_ERROR_STATEMENT') or define('TEXT_MITS_CDB_SQL_ERROR_STATEMENT', 'SQL #%s failed: %s');
defined('TEXT_MITS_CDB_SQL_SUCCESS_ROWS') or define('TEXT_MITS_CDB_SQL_SUCCESS_ROWS', '%s records found, %s displayed.');
defined('TEXT_MITS_CDB_SQL_SUCCESS_AFFECTED') or define('TEXT_MITS_CDB_SQL_SUCCESS_AFFECTED', 'SQL statement executed. Affected rows: %s.');
defined('TEXT_MITS_CDB_SQL_SUCCESS_INSERT_ID') or define('TEXT_MITS_CDB_SQL_SUCCESS_INSERT_ID', 'Insert ID: %s.');
defined('TEXT_MITS_CDB_SQL_SUCCESS_SUMMARY') or define('TEXT_MITS_CDB_SQL_SUCCESS_SUMMARY', '%s SQL statement(s) executed successfully.');
defined('TEXT_MITS_CDB_RESTORE_DELETE') or define('TEXT_MITS_CDB_RESTORE_DELETE', 'Delete');
defined('TEXT_MITS_CDB_RESTORE_DELETE_CONFIRM') or define('TEXT_MITS_CDB_RESTORE_DELETE_CONFIRM', 'Really delete this backup?\\n\\n%s');
defined('TEXT_MITS_CDB_RESTORE_DELETE_TABLE_CONFIRM') or define('TEXT_MITS_CDB_RESTORE_DELETE_TABLE_CONFIRM', 'Really delete this table file?\\n\\n%s');
defined('TEXT_MITS_CDB_RESTORE_DELETE_SUCCESS') or define('TEXT_MITS_CDB_RESTORE_DELETE_SUCCESS', 'Backup deleted: %s');
defined('TEXT_MITS_CDB_RESTORE_DELETE_ERROR') or define('TEXT_MITS_CDB_RESTORE_DELETE_ERROR', 'Backup could not be deleted: %s');
defined('TEXT_MITS_CDB_RESTORE_DELETE_TABLE_SUCCESS') or define('TEXT_MITS_CDB_RESTORE_DELETE_TABLE_SUCCESS', 'Table file deleted: %s');
defined('TEXT_MITS_CDB_RESTORE_DELETE_TABLE_ERROR') or define('TEXT_MITS_CDB_RESTORE_DELETE_TABLE_ERROR', 'Table file could not be deleted: %s');
defined('TEXT_MITS_CDB_RESTORE_DELETE_TABLE_FOLDER_EMPTY') or define('TEXT_MITS_CDB_RESTORE_DELETE_TABLE_FOLDER_EMPTY', 'The table backup folder contained no further table files and was removed.');
defined('TEXT_MITS_CDB_RESTORE_DELETE_RESULT_SUCCESS') or define('TEXT_MITS_CDB_RESTORE_DELETE_RESULT_SUCCESS', 'Backup deleted');
defined('TEXT_MITS_CDB_RESTORE_DELETE_RESULT_ERROR') or define('TEXT_MITS_CDB_RESTORE_DELETE_RESULT_ERROR', 'Delete failed');
defined('TEXT_MITS_CDB_RESTORE_DELETE_RESULT_SUBTITLE') or define('TEXT_MITS_CDB_RESTORE_DELETE_RESULT_SUBTITLE', 'The delete result has been logged.');
defined('TEXT_MITS_CDB_SQL_ERROR_FILE_OPERATION') or define('TEXT_MITS_CDB_SQL_ERROR_FILE_OPERATION', 'SQL statements using LOAD_FILE, LOAD DATA, INTO OUTFILE or INTO DUMPFILE are not executed for security reasons.');
defined('TEXT_MITS_CDB_RESTORE_SELECT_ALL') or define('TEXT_MITS_CDB_RESTORE_SELECT_ALL', 'Select / deselect all');
defined('TEXT_MITS_CDB_RESTORE_SELECT_BACKUP') or define('TEXT_MITS_CDB_RESTORE_SELECT_BACKUP', 'Select backup: %s');
defined('TEXT_MITS_CDB_RESTORE_DELETE_SELECTED') or define('TEXT_MITS_CDB_RESTORE_DELETE_SELECTED', 'Delete selected');
defined('TEXT_MITS_CDB_RESTORE_DELETE_SELECTED_CONFIRM') or define('TEXT_MITS_CDB_RESTORE_DELETE_SELECTED_CONFIRM', 'Really permanently delete the selected backups?');
defined('TEXT_MITS_CDB_RESTORE_DELETE_SELECTED_INFO') or define('TEXT_MITS_CDB_RESTORE_DELETE_SELECTED_INFO', 'Selected table backups are deleted completely including all contained table files.');
defined('TEXT_MITS_CDB_RESTORE_DELETE_SELECTED_NONE') or define('TEXT_MITS_CDB_RESTORE_DELETE_SELECTED_NONE', 'No backups were selected for deletion.');
defined('TEXT_MITS_CDB_RESTORE_DELETE_SELECTED_SUMMARY') or define('TEXT_MITS_CDB_RESTORE_DELETE_SELECTED_SUMMARY', '%s backup(s) deleted, %s failed.');

defined('TEXT_MITS_CDB_BACKUP_TABLES_SELECT_ALL') or define('TEXT_MITS_CDB_BACKUP_TABLES_SELECT_ALL', 'Select / deselect all tables');
