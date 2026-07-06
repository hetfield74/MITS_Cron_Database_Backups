<?php
/**
 * --------------------------------------------------------------
 * File: mits_cron_database_restore.php
 * Date: 12.06.2026
 *
 * Released under the GNU General Public License
 * --------------------------------------------------------------
 */

defined('HEADING_TITLE') or define('HEADING_TITLE', 'MITS outils de base de donn&eacute;es');
defined('TEXT_MITS_CDB_RESTORE_PAGE_TITLE') or define('TEXT_MITS_CDB_RESTORE_PAGE_TITLE', 'MITS Cron Database Backups - outils de base de donn&eacute;es');
defined('TEXT_MITS_CDB_RESTORE_WARNING_TITLE') or define('TEXT_MITS_CDB_RESTORE_WARNING_TITLE', 'Attention : action risqu&eacute;e');
defined('TEXT_MITS_CDB_RESTORE_WARNING') or define('TEXT_MITS_CDB_RESTORE_WARNING', 'Une restauration remplace des donn&eacute;es dans la base de donn&eacute;es actuelle de la boutique. Ex&eacute;cutez cette action uniquement si vous &ecirc;tes certain que la sauvegarde choisie correspond &agrave; la version de la boutique et &agrave; la base de donn&eacute;es. Avant l\'import, une sauvegarde de s&eacute;curit&eacute; suppl&eacute;mentaire de la base actuelle est cr&eacute;&eacute;e automatiquement.');
defined('TEXT_MITS_CDB_RESTORE_INTRO') or define('TEXT_MITS_CDB_RESTORE_INTRO', 'S&eacute;lectionnez une sauvegarde SQL, SQL.GZ ou par tables existante. Vous pouvez aussi cr&eacute;er des sauvegardes manuelles, t&eacute;l&eacute;charger ou supprimer des fichiers de sauvegarde individuels et ex&eacute;cuter SQL directement.');
defined('TEXT_MITS_CDB_RESTORE_NO_BACKUPS') or define('TEXT_MITS_CDB_RESTORE_NO_BACKUPS', 'Aucune sauvegarde .sql ou .sql.gz correspondante n\'a &eacute;t&eacute; trouv&eacute;e.');
defined('TEXT_MITS_CDB_RESTORE_DIR_MODULE') or define('TEXT_MITS_CDB_RESTORE_DIR_MODULE', 'Dossier de sauvegarde MITS');
defined('TEXT_MITS_CDB_RESTORE_DIR_ADMIN') or define('TEXT_MITS_CDB_RESTORE_DIR_ADMIN', 'Dossier de sauvegarde de la boutique');
defined('TEXT_MITS_CDB_RESTORE_FILE') or define('TEXT_MITS_CDB_RESTORE_FILE', 'Fichier');
defined('TEXT_MITS_CDB_RESTORE_DIRECTORY') or define('TEXT_MITS_CDB_RESTORE_DIRECTORY', 'Dossier');
defined('TEXT_MITS_CDB_RESTORE_SIZE') or define('TEXT_MITS_CDB_RESTORE_SIZE', 'Taille');
defined('TEXT_MITS_CDB_RESTORE_DATE') or define('TEXT_MITS_CDB_RESTORE_DATE', 'Date');
defined('TEXT_MITS_CDB_RESTORE_TYPE') or define('TEXT_MITS_CDB_RESTORE_TYPE', 'Type');
defined('TEXT_MITS_CDB_RESTORE_DOWNLOAD') or define('TEXT_MITS_CDB_RESTORE_DOWNLOAD', 'T&eacute;l&eacute;charger');
defined('TEXT_MITS_CDB_RESTORE_CONFIRM_TITLE') or define('TEXT_MITS_CDB_RESTORE_CONFIRM_TITLE', 'Confirmer la restauration');
defined('TEXT_MITS_CDB_RESTORE_CONFIRM_WARNING') or define('TEXT_MITS_CDB_RESTORE_CONFIRM_WARNING', 'Apr&egrave;s le d&eacute;marrage, le navigateur ne doit pas &ecirc;tre ferm&eacute;. La boutique peut r&eacute;agir bri&egrave;vement de fa&ccedil;on incorrecte pendant l\'import.');
defined('TEXT_MITS_CDB_RESTORE_CONFIRM_INPUT') or define('TEXT_MITS_CDB_RESTORE_CONFIRM_INPUT', 'Pour confirmer, saisissez exactement <strong>RESTORE</strong> :');
defined('TEXT_MITS_CDB_RESTORE_RESULT_SUCCESS') or define('TEXT_MITS_CDB_RESTORE_RESULT_SUCCESS', 'Restauration termin&eacute;e');
defined('TEXT_MITS_CDB_RESTORE_RESULT_ERROR') or define('TEXT_MITS_CDB_RESTORE_RESULT_ERROR', 'Restauration &eacute;chou&eacute;e');
defined('TEXT_MITS_CDB_RESTORE_SAFETY_BACKUP_CREATED') or define('TEXT_MITS_CDB_RESTORE_SAFETY_BACKUP_CREATED', 'Une sauvegarde de s&eacute;curit&eacute; a &eacute;t&eacute; cr&eacute;&eacute;e avant la restauration : %s');
defined('TEXT_MITS_CDB_RESTORE_GZ_UNPACKED') or define('TEXT_MITS_CDB_RESTORE_GZ_UNPACKED', 'La sauvegarde GZIP a &eacute;t&eacute; temporairement d&eacute;compress&eacute;e.');
defined('TEXT_MITS_CDB_RESTORE_SUCCESS') or define('TEXT_MITS_CDB_RESTORE_SUCCESS', 'La sauvegarde %s a &eacute;t&eacute; import&eacute;e avec succ&egrave;s dans la base de donn&eacute;es.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_EXEC_DISABLED') or define('TEXT_MITS_CDB_RESTORE_ERROR_EXEC_DISABLED', 'La fonction PHP exec() est d&eacute;sactiv&eacute;e. La restauration rapide via mysql n\'est donc pas possible.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_LOCKED') or define('TEXT_MITS_CDB_RESTORE_ERROR_LOCKED', 'Une restauration est d&eacute;j&agrave; en cours ou un ancien fichier de verrouillage existe. Veuillez r&eacute;essayer plus tard.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_TOKEN') or define('TEXT_MITS_CDB_RESTORE_ERROR_TOKEN', 'Le jeton de s&eacute;curit&eacute; est invalide. Rechargez la page et r&eacute;essayez.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_INVALID_FILE') or define('TEXT_MITS_CDB_RESTORE_ERROR_INVALID_FILE', 'Le fichier de sauvegarde s&eacute;lectionn&eacute; est invalide ou n\'existe plus.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_CONFIRM') or define('TEXT_MITS_CDB_RESTORE_ERROR_CONFIRM', 'La confirmation est incorrecte. La restauration n\'a pas &eacute;t&eacute; lanc&eacute;e.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_SAFETY_DIR') or define('TEXT_MITS_CDB_RESTORE_ERROR_SAFETY_DIR', 'Le dossier de sauvegarde n\'est pas inscriptible. La sauvegarde de s&eacute;curit&eacute; avant restauration n\'a pas pu &ecirc;tre cr&eacute;&eacute;e.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_SAFETY_BACKUP') or define('TEXT_MITS_CDB_RESTORE_ERROR_SAFETY_BACKUP', 'La sauvegarde de s&eacute;curit&eacute; avant restauration n\'a pas pu &ecirc;tre cr&eacute;&eacute;e. L\'import a &eacute;t&eacute; annul&eacute;.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_ZLIB') or define('TEXT_MITS_CDB_RESTORE_ERROR_ZLIB', 'L\'extension PHP Zlib n\'est pas active. Les fichiers SQL.GZ ne peuvent pas &ecirc;tre d&eacute;compress&eacute;s.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_GZ_OPEN') or define('TEXT_MITS_CDB_RESTORE_ERROR_GZ_OPEN', 'Le fichier SQL.GZ n\'a pas pu &ecirc;tre ouvert.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_GZ_READ') or define('TEXT_MITS_CDB_RESTORE_ERROR_GZ_READ', 'Le fichier SQL.GZ n\'a pas pu &ecirc;tre lu compl&egrave;tement.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_TEMPFILE') or define('TEXT_MITS_CDB_RESTORE_ERROR_TEMPFILE', 'Le fichier SQL temporaire n\'a pas pu &ecirc;tre cr&eacute;&eacute;.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_IMPORT') or define('TEXT_MITS_CDB_RESTORE_ERROR_IMPORT', 'L\'import de %s a &eacute;chou&eacute;.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_DOWNLOAD') or define('TEXT_MITS_CDB_RESTORE_ERROR_DOWNLOAD', 'Le fichier de sauvegarde n\'a pas pu &ecirc;tre t&eacute;l&eacute;charg&eacute;.');

defined('TEXT_MITS_CDB_RESTORE_ERROR_ZIPARCHIVE') or define('TEXT_MITS_CDB_RESTORE_ERROR_ZIPARCHIVE', 'L\'extension PHP ZipArchive n\'est pas active. Les sauvegardes ZIP ne peuvent pas &ecirc;tre d&eacute;compress&eacute;es.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_ZIP_OPEN') or define('TEXT_MITS_CDB_RESTORE_ERROR_ZIP_OPEN', 'Le fichier ZIP n\'a pas pu &ecirc;tre ouvert.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_ZIP_EMPTY') or define('TEXT_MITS_CDB_RESTORE_ERROR_ZIP_EMPTY', 'Aucun fichier SQL n\'a &eacute;t&eacute; trouv&eacute; dans le fichier ZIP.');
defined('TEXT_MITS_CDB_RESTORE_ZIP_UNPACKED') or define('TEXT_MITS_CDB_RESTORE_ZIP_UNPACKED', 'La sauvegarde ZIP a &eacute;t&eacute; d&eacute;compress&eacute;e temporairement et pr&eacute;par&eacute;e pour l\'import.');
defined('TEXT_MITS_CDB_RESTORE_RELOGIN_TITLE') or define('TEXT_MITS_CDB_RESTORE_RELOGIN_TITLE', 'La session admin a &eacute;t&eacute; termin&eacute;e');
defined('TEXT_MITS_CDB_RESTORE_RELOGIN_TEXT') or define('TEXT_MITS_CDB_RESTORE_RELOGIN_TEXT', 'La restauration est termin&eacute;e. Pour des raisons de s&eacute;curit&eacute;, la session admin actuelle a &eacute;t&eacute; termin&eacute;e. Veuillez vous reconnecter &agrave; l\'administration.');
defined('TEXT_MITS_CDB_RESTORE_RELOGIN_BUTTON') or define('TEXT_MITS_CDB_RESTORE_RELOGIN_BUTTON', 'Vers la connexion admin');
defined('TEXT_MITS_CDB_RESTORE_RELOGIN_NOTE') or define('TEXT_MITS_CDB_RESTORE_RELOGIN_NOTE', 'Ceci est volontaire, car les donn&eacute;es de session, d\'administrateur et de droits peuvent avoir &eacute;t&eacute; remises &agrave; l\'&eacute;tat de la sauvegarde par la restauration.');
defined('TEXT_MITS_CDB_RESTORE_HERO_TEXT') or define('TEXT_MITS_CDB_RESTORE_HERO_TEXT', 'Sauvegarder la base de donn&eacute;es, t&eacute;l&eacute;charger des sauvegardes, restaurer des tables de fa&ccedil;on cibl&eacute;e, supprimer d\'anciennes sauvegardes et ex&eacute;cuter des requ&ecirc;tes SQL directement. Remarque : une sauvegarde de s&eacute;curit&eacute; est cr&eacute;&eacute;e automatiquement avant une restauration.');
defined('TEXT_MITS_CDB_RESTORE_MODULE_SETTINGS') or define('TEXT_MITS_CDB_RESTORE_MODULE_SETTINGS', 'Param&egrave;tres du module');
defined('TEXT_MITS_CDB_RESTORE_REFRESH') or define('TEXT_MITS_CDB_RESTORE_REFRESH', 'Actualiser la liste');
defined('TEXT_MITS_CDB_RESTORE_STAT_BACKUPS') or define('TEXT_MITS_CDB_RESTORE_STAT_BACKUPS', 'Sauvegardes');
defined('TEXT_MITS_CDB_RESTORE_STAT_COMPRESSED') or define('TEXT_MITS_CDB_RESTORE_STAT_COMPRESSED', '%s compress&eacute;es');
defined('TEXT_MITS_CDB_RESTORE_STAT_TOTAL_SIZE') or define('TEXT_MITS_CDB_RESTORE_STAT_TOTAL_SIZE', 'Taille totale');
defined('TEXT_MITS_CDB_RESTORE_STAT_TOTAL_SIZE_META') or define('TEXT_MITS_CDB_RESTORE_STAT_TOTAL_SIZE_META', 'tous les fichiers trouv&eacute;s');
defined('TEXT_MITS_CDB_RESTORE_STAT_LATEST') or define('TEXT_MITS_CDB_RESTORE_STAT_LATEST', 'Derni&egrave;re sauvegarde');
defined('TEXT_MITS_CDB_RESTORE_STAT_LATEST_META') or define('TEXT_MITS_CDB_RESTORE_STAT_LATEST_META', 'fichier le plus r&eacute;cent en premier');
defined('TEXT_MITS_CDB_RESTORE_STAT_SYSTEM') or define('TEXT_MITS_CDB_RESTORE_STAT_SYSTEM', '&Eacute;tat du syst&egrave;me');
defined('TEXT_MITS_CDB_RESTORE_STAT_SYSTEM_META') or define('TEXT_MITS_CDB_RESTORE_STAT_SYSTEM_META', 'Import via client serveur');
defined('TEXT_MITS_CDB_RESTORE_EXEC_AVAILABLE') or define('TEXT_MITS_CDB_RESTORE_EXEC_AVAILABLE', 'mysql pr&ecirc;t');
defined('TEXT_MITS_CDB_RESTORE_EXEC_NOT_AVAILABLE') or define('TEXT_MITS_CDB_RESTORE_EXEC_NOT_AVAILABLE', 'exec d&eacute;sactiv&eacute;');
defined('TEXT_MITS_CDB_RESTORE_RESULT_SUBTITLE') or define('TEXT_MITS_CDB_RESTORE_RESULT_SUBTITLE', 'Le r&eacute;sultat de la restauration a &eacute;t&eacute; journalis&eacute;.');
defined('TEXT_MITS_CDB_RESTORE_CONFIRM_SUBTITLE') or define('TEXT_MITS_CDB_RESTORE_CONFIRM_SUBTITLE', 'Veuillez v&eacute;rifier le fichier et confirmer volontairement la restauration.');
defined('TEXT_MITS_CDB_RESTORE_PROCESS_TITLE') or define('TEXT_MITS_CDB_RESTORE_PROCESS_TITLE', 'D&eacute;roulement de la restauration');
defined('TEXT_MITS_CDB_RESTORE_PROCESS_SUBTITLE') or define('TEXT_MITS_CDB_RESTORE_PROCESS_SUBTITLE', 'Ces niveaux de s&eacute;curit&eacute; sont ex&eacute;cut&eacute;s avant et pendant l\'import.');
defined('TEXT_MITS_CDB_RESTORE_STEP_BACKUP') or define('TEXT_MITS_CDB_RESTORE_STEP_BACKUP', 'La base de donn&eacute;es actuelle est sauvegard&eacute;e comme sauvegarde de s&eacute;curit&eacute;.');
defined('TEXT_MITS_CDB_RESTORE_STEP_UNPACK') or define('TEXT_MITS_CDB_RESTORE_STEP_UNPACK', 'Les fichiers SQL.GZ et les sauvegardes par tables sont temporairement d&eacute;compress&eacute;s si n&eacute;cessaire.');
defined('TEXT_MITS_CDB_RESTORE_STEP_IMPORT') or define('TEXT_MITS_CDB_RESTORE_STEP_IMPORT', 'L\'import est effectu&eacute; via le client mysql du serveur.');
defined('TEXT_MITS_CDB_RESTORE_STEP_RELOGIN') or define('TEXT_MITS_CDB_RESTORE_STEP_RELOGIN', 'Apr&egrave;s une restauration r&eacute;ussie, la session admin est termin&eacute;e.');
defined('TEXT_MITS_CDB_RESTORE_CARD_BACKUPS') or define('TEXT_MITS_CDB_RESTORE_CARD_BACKUPS', 'Sauvegardes disponibles');
defined('TEXT_MITS_CDB_RESTORE_SAFETY_SUBTITLE') or define('TEXT_MITS_CDB_RESTORE_SAFETY_SUBTITLE', 'Veuillez v&eacute;rifier soigneusement avant le d&eacute;marrage.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_SAFETY_GZIP') or define('TEXT_MITS_CDB_RESTORE_ERROR_SAFETY_GZIP', 'La compression GZIP de la sauvegarde de s&eacute;curit&eacute; a &eacute;chou&eacute;. La restauration n\'a pas &eacute;t&eacute; lanc&eacute;e.');
defined('TEXT_MITS_CDB_RESTORE_TABLE_DOWNLOADS_TOGGLE') or define('TEXT_MITS_CDB_RESTORE_TABLE_DOWNLOADS_TOGGLE', 'Afficher les fichiers de table individuels');
defined('TEXT_MITS_CDB_RESTORE_TABLE_DOWNLOADS_COUNT') or define('TEXT_MITS_CDB_RESTORE_TABLE_DOWNLOADS_COUNT', '%s fichiers');
defined('TEXT_MITS_CDB_RESTORE_TABLE_DOWNLOADS_INFO') or define('TEXT_MITS_CDB_RESTORE_TABLE_DOWNLOADS_INFO', 'Chaque fichier de table appartient &agrave; cette sauvegarde par table et peut &ecirc;tre t&eacute;l&eacute;charg&eacute;, restaur&eacute; ou supprim&eacute; s&eacute;par&eacute;ment.');
defined('TEXT_MITS_CDB_RESTORE_TABLE_FILE_RESTORE') or define('TEXT_MITS_CDB_RESTORE_TABLE_FILE_RESTORE', 'Restaurer ce fichier de table.');
defined('TEXT_MITS_CDB_RESTORE_TABLE_PRESELECTED_INFO') or define('TEXT_MITS_CDB_RESTORE_TABLE_PRESELECTED_INFO', 'Seul ce fichier de table est pr&eacute;s&eacute;lectionn&eacute; : %s');

defined('TEXT_MITS_CDB_SQL_TITLE') or define('TEXT_MITS_CDB_SQL_TITLE', 'Ex&eacute;cuter SQL directement');
defined('TEXT_MITS_CDB_SQL_SUBTITLE') or define('TEXT_MITS_CDB_SQL_SUBTITLE', 'Zone de requ&ecirc;te pour des instructions SQL individuelles.');
defined('TEXT_MITS_CDB_SQL_WARNING_TITLE') or define('TEXT_MITS_CDB_SQL_WARNING_TITLE', 'Acc&egrave;s direct &agrave; la base de donn&eacute;es');
defined('TEXT_MITS_CDB_SQL_WARNING') or define('TEXT_MITS_CDB_SQL_WARNING', 'Le code SQL est ex&eacute;cut&eacute; directement sur la base de donn&eacute;es actuelle de la boutique. Cr&eacute;ez d\'abord une sauvegarde. Les instructions d\'&eacute;criture doivent &ecirc;tre explicitement activ&eacute;es ci-dessous.');
defined('TEXT_MITS_CDB_SQL_CODE') or define('TEXT_MITS_CDB_SQL_CODE', 'Code SQL');
defined('TEXT_MITS_CDB_SQL_ROW_LIMIT') or define('TEXT_MITS_CDB_SQL_ROW_LIMIT', 'Nombre maximal de lignes affich&eacute;es');
defined('TEXT_MITS_CDB_SQL_CONFIRM_WRITE') or define('TEXT_MITS_CDB_SQL_CONFIRM_WRITE', 'Autoriser explicitement les instructions SQL d\'&eacute;criture comme INSERT, UPDATE, DELETE, ALTER ou DROP.');
defined('TEXT_MITS_CDB_SQL_RUN') or define('TEXT_MITS_CDB_SQL_RUN', 'Ex&eacute;cuter SQL');
defined('TEXT_MITS_CDB_SQL_RESULT_SUCCESS') or define('TEXT_MITS_CDB_SQL_RESULT_SUCCESS', 'SQL ex&eacute;cut&eacute;');
defined('TEXT_MITS_CDB_SQL_RESULT_ERROR') or define('TEXT_MITS_CDB_SQL_RESULT_ERROR', 'SQL &eacute;chou&eacute;');
defined('TEXT_MITS_CDB_SQL_RESULT_SUBTITLE') or define('TEXT_MITS_CDB_SQL_RESULT_SUBTITLE', 'Le r&eacute;sultat de l\'ex&eacute;cution SQL a &eacute;t&eacute; journalis&eacute;.');
defined('TEXT_MITS_CDB_SQL_RESULT_STATEMENT_TITLE') or define('TEXT_MITS_CDB_SQL_RESULT_STATEMENT_TITLE', 'SQL #%s');
defined('TEXT_MITS_CDB_SQL_STATUS_OK') or define('TEXT_MITS_CDB_SQL_STATUS_OK', 'OK');
defined('TEXT_MITS_CDB_SQL_STATUS_ERROR') or define('TEXT_MITS_CDB_SQL_STATUS_ERROR', 'Erreur');
defined('TEXT_MITS_CDB_SQL_RESULT_EMPTY') or define('TEXT_MITS_CDB_SQL_RESULT_EMPTY', 'La requ&ecirc;te n\'a retourn&eacute; aucun enregistrement.');
defined('TEXT_MITS_CDB_SQL_ERROR_EMPTY') or define('TEXT_MITS_CDB_SQL_ERROR_EMPTY', 'Aucun code SQL n\'a &eacute;t&eacute; saisi.');
defined('TEXT_MITS_CDB_SQL_ERROR_CONNECTION') or define('TEXT_MITS_CDB_SQL_ERROR_CONNECTION', 'La connexion &agrave; la base de donn&eacute;es n\'est pas disponible.');
defined('TEXT_MITS_CDB_SQL_ERROR_WRITE_CONFIRM') or define('TEXT_MITS_CDB_SQL_ERROR_WRITE_CONFIRM', 'Les instructions SQL d\'&eacute;criture n\'ont pas &eacute;t&eacute; ex&eacute;cut&eacute;es car l\'autorisation n\'a pas &eacute;t&eacute; activ&eacute;e.');
defined('TEXT_MITS_CDB_SQL_ERROR_STATEMENT') or define('TEXT_MITS_CDB_SQL_ERROR_STATEMENT', 'SQL #%s a &eacute;chou&eacute; : %s');
defined('TEXT_MITS_CDB_SQL_SUCCESS_ROWS') or define('TEXT_MITS_CDB_SQL_SUCCESS_ROWS', '%s enregistrements trouv&eacute;s, %s affich&eacute;s.');
defined('TEXT_MITS_CDB_SQL_SUCCESS_AFFECTED') or define('TEXT_MITS_CDB_SQL_SUCCESS_AFFECTED', 'Instruction SQL ex&eacute;cut&eacute;e. Lignes affect&eacute;es : %s.');
defined('TEXT_MITS_CDB_SQL_SUCCESS_INSERT_ID') or define('TEXT_MITS_CDB_SQL_SUCCESS_INSERT_ID', 'ID insert : %s.');
defined('TEXT_MITS_CDB_SQL_SUCCESS_SUMMARY') or define('TEXT_MITS_CDB_SQL_SUCCESS_SUMMARY', '%s instruction(s) SQL ex&eacute;cut&eacute;e(s) avec succ&egrave;s.');
defined('TEXT_MITS_CDB_RESTORE_DELETE') or define('TEXT_MITS_CDB_RESTORE_DELETE', 'Supprimer');
defined('TEXT_MITS_CDB_RESTORE_DELETE_CONFIRM') or define('TEXT_MITS_CDB_RESTORE_DELETE_CONFIRM', 'Supprimer vraiment cette sauvegarde ?\\n\\n%s');
defined('TEXT_MITS_CDB_RESTORE_DELETE_TABLE_CONFIRM') or define('TEXT_MITS_CDB_RESTORE_DELETE_TABLE_CONFIRM', 'Supprimer vraiment ce fichier de table ?\\n\\n%s');
defined('TEXT_MITS_CDB_RESTORE_DELETE_SUCCESS') or define('TEXT_MITS_CDB_RESTORE_DELETE_SUCCESS', 'Sauvegarde supprim&eacute;e : %s');
defined('TEXT_MITS_CDB_RESTORE_DELETE_ERROR') or define('TEXT_MITS_CDB_RESTORE_DELETE_ERROR', 'La sauvegarde n\'a pas pu &ecirc;tre supprim&eacute;e : %s');
defined('TEXT_MITS_CDB_RESTORE_DELETE_TABLE_SUCCESS') or define('TEXT_MITS_CDB_RESTORE_DELETE_TABLE_SUCCESS', 'Fichier de table supprim&eacute; : %s');
defined('TEXT_MITS_CDB_RESTORE_DELETE_TABLE_ERROR') or define('TEXT_MITS_CDB_RESTORE_DELETE_TABLE_ERROR', 'Le fichier de table n\'a pas pu &ecirc;tre supprim&eacute; : %s');
defined('TEXT_MITS_CDB_RESTORE_DELETE_TABLE_FOLDER_EMPTY') or define('TEXT_MITS_CDB_RESTORE_DELETE_TABLE_FOLDER_EMPTY', 'Le dossier de sauvegarde par tables ne contenait plus de fichiers de table et a &eacute;t&eacute; supprim&eacute;.');
defined('TEXT_MITS_CDB_RESTORE_DELETE_RESULT_SUCCESS') or define('TEXT_MITS_CDB_RESTORE_DELETE_RESULT_SUCCESS', 'Sauvegarde supprim&eacute;e');
defined('TEXT_MITS_CDB_RESTORE_DELETE_RESULT_ERROR') or define('TEXT_MITS_CDB_RESTORE_DELETE_RESULT_ERROR', '&Eacute;chec de la suppression');
defined('TEXT_MITS_CDB_RESTORE_DELETE_RESULT_SUBTITLE') or define('TEXT_MITS_CDB_RESTORE_DELETE_RESULT_SUBTITLE', 'Le r&eacute;sultat de la suppression a &eacute;t&eacute; journalis&eacute;.');
defined('TEXT_MITS_CDB_SQL_ERROR_FILE_OPERATION') or define('TEXT_MITS_CDB_SQL_ERROR_FILE_OPERATION', 'Les instructions SQL utilisant LOAD_FILE, LOAD DATA, INTO OUTFILE ou INTO DUMPFILE ne sont pas ex&eacute;cut&eacute;es pour des raisons de s&eacute;curit&eacute;.');
defined('TEXT_MITS_CDB_RESTORE_SELECT_ALL') or define('TEXT_MITS_CDB_RESTORE_SELECT_ALL', 'Tout s&eacute;lectionner / d&eacute;s&eacute;lectionner');
defined('TEXT_MITS_CDB_RESTORE_SELECT_BACKUP') or define('TEXT_MITS_CDB_RESTORE_SELECT_BACKUP', 'S&eacute;lectionner la sauvegarde : %s');
defined('TEXT_MITS_CDB_RESTORE_DELETE_SELECTED') or define('TEXT_MITS_CDB_RESTORE_DELETE_SELECTED', 'Supprimer la s&eacute;lection');
defined('TEXT_MITS_CDB_RESTORE_DELETE_SELECTED_CONFIRM') or define('TEXT_MITS_CDB_RESTORE_DELETE_SELECTED_CONFIRM', 'Supprimer d&eacute;finitivement les sauvegardes s&eacute;lectionn&eacute;es ?');
defined('TEXT_MITS_CDB_RESTORE_DELETE_SELECTED_INFO') or define('TEXT_MITS_CDB_RESTORE_DELETE_SELECTED_INFO', 'Les sauvegardes de tables s&eacute;lectionn&eacute;es sont supprim&eacute;es compl&egrave;tement, y compris tous les fichiers de tables.');
defined('TEXT_MITS_CDB_RESTORE_DELETE_SELECTED_NONE') or define('TEXT_MITS_CDB_RESTORE_DELETE_SELECTED_NONE', 'Aucune sauvegarde n\'a &eacute;t&eacute; s&eacute;lectionn&eacute;e pour la suppression.');
defined('TEXT_MITS_CDB_RESTORE_DELETE_SELECTED_SUMMARY') or define('TEXT_MITS_CDB_RESTORE_DELETE_SELECTED_SUMMARY', '%s sauvegarde(s) supprim&eacute;e(s), %s &eacute;chec(s).');

defined('TEXT_MITS_CDB_BACKUP_TABLES_SELECT_ALL') or define('TEXT_MITS_CDB_BACKUP_TABLES_SELECT_ALL', 'S&eacute;lectionner / d&eacute;s&eacute;lectionner toutes les tables');
