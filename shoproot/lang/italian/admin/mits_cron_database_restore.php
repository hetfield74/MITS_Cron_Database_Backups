<?php
/**
 * --------------------------------------------------------------
 * File: mits_cron_database_restore.php
 * Date: 12.06.2026
 *
 * Released under the GNU General Public License
 * --------------------------------------------------------------
 */

defined('HEADING_TITLE') or define('HEADING_TITLE', 'MITS strumenti database');
defined('TEXT_MITS_CDB_RESTORE_PAGE_TITLE') or define('TEXT_MITS_CDB_RESTORE_PAGE_TITLE', 'MITS Cron Database Backups - strumenti database');
defined('TEXT_MITS_CDB_RESTORE_WARNING_TITLE') or define('TEXT_MITS_CDB_RESTORE_WARNING_TITLE', 'Attenzione: azione rischiosa');
defined('TEXT_MITS_CDB_RESTORE_WARNING') or define('TEXT_MITS_CDB_RESTORE_WARNING', 'Un ripristino sostituisce dati nel database attuale del negozio. Eseguire questa azione solo se si &egrave; certi che il backup scelto sia adatto alla versione del negozio e al database. Prima dell\'importazione viene creato automaticamente un ulteriore backup di sicurezza del database attuale.');
defined('TEXT_MITS_CDB_RESTORE_INTRO') or define('TEXT_MITS_CDB_RESTORE_INTRO', 'Selezionare un backup SQL, SQL.GZ o per tabelle esistente. &Egrave; inoltre possibile creare backup manuali, scaricare o eliminare singoli file di backup ed eseguire SQL direttamente.');
defined('TEXT_MITS_CDB_RESTORE_NO_BACKUPS') or define('TEXT_MITS_CDB_RESTORE_NO_BACKUPS', 'Non sono stati trovati backup .sql, .sql.gz o .zip adatti.');
defined('TEXT_MITS_CDB_RESTORE_DIR_MODULE') or define('TEXT_MITS_CDB_RESTORE_DIR_MODULE', 'Cartella backup MITS');
defined('TEXT_MITS_CDB_RESTORE_DIR_ADMIN') or define('TEXT_MITS_CDB_RESTORE_DIR_ADMIN', 'Cartella backup shop');
defined('TEXT_MITS_CDB_RESTORE_FILE') or define('TEXT_MITS_CDB_RESTORE_FILE', 'File');
defined('TEXT_MITS_CDB_RESTORE_DIRECTORY') or define('TEXT_MITS_CDB_RESTORE_DIRECTORY', 'Cartella');
defined('TEXT_MITS_CDB_RESTORE_SIZE') or define('TEXT_MITS_CDB_RESTORE_SIZE', 'Dimensione');
defined('TEXT_MITS_CDB_RESTORE_DATE') or define('TEXT_MITS_CDB_RESTORE_DATE', 'Data');
defined('TEXT_MITS_CDB_RESTORE_TYPE') or define('TEXT_MITS_CDB_RESTORE_TYPE', 'Tipo');
defined('TEXT_MITS_CDB_RESTORE_DOWNLOAD') or define('TEXT_MITS_CDB_RESTORE_DOWNLOAD', 'Scarica');
defined('TEXT_MITS_CDB_RESTORE_CONFIRM_TITLE') or define('TEXT_MITS_CDB_RESTORE_CONFIRM_TITLE', 'Conferma ripristino');
defined('TEXT_MITS_CDB_RESTORE_CONFIRM_WARNING') or define('TEXT_MITS_CDB_RESTORE_CONFIRM_WARNING', 'Dopo l\'avvio non chiudere il browser. Durante l\'importazione il negozio potrebbe rispondere temporaneamente in modo non corretto.');
defined('TEXT_MITS_CDB_RESTORE_CONFIRM_INPUT') or define('TEXT_MITS_CDB_RESTORE_CONFIRM_INPUT', 'Per confermare inserire esattamente <strong>RESTORE</strong>:');
defined('TEXT_MITS_CDB_RESTORE_RESULT_SUCCESS') or define('TEXT_MITS_CDB_RESTORE_RESULT_SUCCESS', 'Ripristino completato');
defined('TEXT_MITS_CDB_RESTORE_RESULT_ERROR') or define('TEXT_MITS_CDB_RESTORE_RESULT_ERROR', 'Ripristino non riuscito');
defined('TEXT_MITS_CDB_RESTORE_SAFETY_BACKUP_CREATED') or define('TEXT_MITS_CDB_RESTORE_SAFETY_BACKUP_CREATED', 'Backup di sicurezza creato prima del ripristino: %s');
defined('TEXT_MITS_CDB_RESTORE_GZ_UNPACKED') or define('TEXT_MITS_CDB_RESTORE_GZ_UNPACKED', 'Il backup GZIP &egrave; stato decompresso temporaneamente.');
defined('TEXT_MITS_CDB_RESTORE_SUCCESS') or define('TEXT_MITS_CDB_RESTORE_SUCCESS', 'Il backup %s &egrave; stato importato correttamente nel database.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_EXEC_DISABLED') or define('TEXT_MITS_CDB_RESTORE_ERROR_EXEC_DISABLED', 'La funzione PHP exec() &egrave; disattivata. Il ripristino rapido tramite mysql non &egrave; possibile.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_LOCKED') or define('TEXT_MITS_CDB_RESTORE_ERROR_LOCKED', 'Un ripristino &egrave; gi&agrave; in corso oppure esiste un vecchio file di blocco. Riprovare pi&ugrave; tardi.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_TOKEN') or define('TEXT_MITS_CDB_RESTORE_ERROR_TOKEN', 'Il token di sicurezza non &egrave; valido. Ricaricare la pagina e riprovare.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_INVALID_FILE') or define('TEXT_MITS_CDB_RESTORE_ERROR_INVALID_FILE', 'Il file di backup selezionato non &egrave; valido o non esiste pi&ugrave;.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_CONFIRM') or define('TEXT_MITS_CDB_RESTORE_ERROR_CONFIRM', 'La conferma non &egrave; corretta. Il ripristino non &egrave; stato avviato.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_SAFETY_DIR') or define('TEXT_MITS_CDB_RESTORE_ERROR_SAFETY_DIR', 'La cartella backup non &egrave; scrivibile. Non &egrave; stato possibile creare il backup di sicurezza prima del ripristino.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_SAFETY_BACKUP') or define('TEXT_MITS_CDB_RESTORE_ERROR_SAFETY_BACKUP', 'Non &egrave; stato possibile creare il backup di sicurezza prima del ripristino. Importazione annullata.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_ZLIB') or define('TEXT_MITS_CDB_RESTORE_ERROR_ZLIB', 'L\'estensione PHP Zlib non &egrave; attiva. I file SQL.GZ non possono essere decompressi.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_GZ_OPEN') or define('TEXT_MITS_CDB_RESTORE_ERROR_GZ_OPEN', 'Impossibile aprire il file SQL.GZ.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_GZ_READ') or define('TEXT_MITS_CDB_RESTORE_ERROR_GZ_READ', 'Impossibile leggere completamente il file SQL.GZ.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_TEMPFILE') or define('TEXT_MITS_CDB_RESTORE_ERROR_TEMPFILE', 'Impossibile creare il file SQL temporaneo.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_IMPORT') or define('TEXT_MITS_CDB_RESTORE_ERROR_IMPORT', 'Importazione di %s non riuscita.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_DOWNLOAD') or define('TEXT_MITS_CDB_RESTORE_ERROR_DOWNLOAD', 'Impossibile scaricare il file di backup.');

defined('TEXT_MITS_CDB_RESTORE_ERROR_ZIPARCHIVE') or define('TEXT_MITS_CDB_RESTORE_ERROR_ZIPARCHIVE', 'L\'estensione PHP ZipArchive non &egrave; attiva. I backup ZIP non possono essere decompressi.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_ZIP_OPEN') or define('TEXT_MITS_CDB_RESTORE_ERROR_ZIP_OPEN', 'Impossibile aprire il file ZIP.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_ZIP_EMPTY') or define('TEXT_MITS_CDB_RESTORE_ERROR_ZIP_EMPTY', 'Nel file ZIP non sono stati trovati file SQL.');
defined('TEXT_MITS_CDB_RESTORE_ZIP_UNPACKED') or define('TEXT_MITS_CDB_RESTORE_ZIP_UNPACKED', 'Il backup ZIP &egrave; stato decompresso temporaneamente e preparato per l\'importazione.');
defined('TEXT_MITS_CDB_RESTORE_RELOGIN_TITLE') or define('TEXT_MITS_CDB_RESTORE_RELOGIN_TITLE', 'Sessione admin terminata');
defined('TEXT_MITS_CDB_RESTORE_RELOGIN_TEXT') or define('TEXT_MITS_CDB_RESTORE_RELOGIN_TEXT', 'Il ripristino &egrave; stato completato. Per sicurezza la sessione admin attuale &egrave; stata terminata. Effettuare nuovamente il login nell\'area admin.');
defined('TEXT_MITS_CDB_RESTORE_RELOGIN_BUTTON') or define('TEXT_MITS_CDB_RESTORE_RELOGIN_BUTTON', 'Vai al login admin');
defined('TEXT_MITS_CDB_RESTORE_RELOGIN_NOTE') or define('TEXT_MITS_CDB_RESTORE_RELOGIN_NOTE', 'Questo &egrave; intenzionale, perch&eacute; sessioni, utenti admin e permessi potrebbero essere stati riportati allo stato del backup.');
defined('TEXT_MITS_CDB_RESTORE_HERO_TEXT') or define('TEXT_MITS_CDB_RESTORE_HERO_TEXT', 'Creare backup del database, scaricare backup, ripristinare tabelle selezionate, rimuovere vecchi backup ed eseguire query SQL direttamente. Nota: prima del ripristino viene creato automaticamente un backup di sicurezza.');
defined('TEXT_MITS_CDB_RESTORE_MODULE_SETTINGS') or define('TEXT_MITS_CDB_RESTORE_MODULE_SETTINGS', 'Impostazioni modulo');
defined('TEXT_MITS_CDB_RESTORE_REFRESH') or define('TEXT_MITS_CDB_RESTORE_REFRESH', 'Aggiorna elenco');
defined('TEXT_MITS_CDB_RESTORE_STAT_BACKUPS') or define('TEXT_MITS_CDB_RESTORE_STAT_BACKUPS', 'Backup');
defined('TEXT_MITS_CDB_RESTORE_STAT_COMPRESSED') or define('TEXT_MITS_CDB_RESTORE_STAT_COMPRESSED', '%s compressi');
defined('TEXT_MITS_CDB_RESTORE_STAT_TOTAL_SIZE') or define('TEXT_MITS_CDB_RESTORE_STAT_TOTAL_SIZE', 'Dimensione totale');
defined('TEXT_MITS_CDB_RESTORE_STAT_TOTAL_SIZE_META') or define('TEXT_MITS_CDB_RESTORE_STAT_TOTAL_SIZE_META', 'tutti i file trovati');
defined('TEXT_MITS_CDB_RESTORE_STAT_LATEST') or define('TEXT_MITS_CDB_RESTORE_STAT_LATEST', 'Ultimo backup');
defined('TEXT_MITS_CDB_RESTORE_STAT_LATEST_META') or define('TEXT_MITS_CDB_RESTORE_STAT_LATEST_META', 'file pi&ugrave; recente prima');
defined('TEXT_MITS_CDB_RESTORE_STAT_SYSTEM') or define('TEXT_MITS_CDB_RESTORE_STAT_SYSTEM', 'Stato sistema');
defined('TEXT_MITS_CDB_RESTORE_STAT_SYSTEM_META') or define('TEXT_MITS_CDB_RESTORE_STAT_SYSTEM_META', 'Importazione tramite client server');
defined('TEXT_MITS_CDB_RESTORE_EXEC_AVAILABLE') or define('TEXT_MITS_CDB_RESTORE_EXEC_AVAILABLE', 'mysql pronto');
defined('TEXT_MITS_CDB_RESTORE_EXEC_NOT_AVAILABLE') or define('TEXT_MITS_CDB_RESTORE_EXEC_NOT_AVAILABLE', 'exec disattivato');
defined('TEXT_MITS_CDB_RESTORE_RESULT_SUBTITLE') or define('TEXT_MITS_CDB_RESTORE_RESULT_SUBTITLE', 'Il risultato del ripristino &egrave; stato registrato.');
defined('TEXT_MITS_CDB_RESTORE_CONFIRM_SUBTITLE') or define('TEXT_MITS_CDB_RESTORE_CONFIRM_SUBTITLE', 'Controllare il file e confermare consapevolmente il ripristino.');
defined('TEXT_MITS_CDB_RESTORE_PROCESS_TITLE') or define('TEXT_MITS_CDB_RESTORE_PROCESS_TITLE', 'Procedura di ripristino');
defined('TEXT_MITS_CDB_RESTORE_PROCESS_SUBTITLE') or define('TEXT_MITS_CDB_RESTORE_PROCESS_SUBTITLE', 'Questi passaggi di sicurezza vengono eseguiti prima e durante l\'importazione.');
defined('TEXT_MITS_CDB_RESTORE_STEP_BACKUP') or define('TEXT_MITS_CDB_RESTORE_STEP_BACKUP', 'Il database attuale viene salvato come backup di sicurezza.');
defined('TEXT_MITS_CDB_RESTORE_STEP_UNPACK') or define('TEXT_MITS_CDB_RESTORE_STEP_UNPACK', 'I file SQL.GZ e i backup per tabelle vengono decompressi temporaneamente se necessario.');
defined('TEXT_MITS_CDB_RESTORE_STEP_IMPORT') or define('TEXT_MITS_CDB_RESTORE_STEP_IMPORT', 'L\'importazione avviene tramite il client mysql del server.');
defined('TEXT_MITS_CDB_RESTORE_STEP_RELOGIN') or define('TEXT_MITS_CDB_RESTORE_STEP_RELOGIN', 'Dopo un ripristino riuscito la sessione admin viene terminata.');
defined('TEXT_MITS_CDB_RESTORE_CARD_BACKUPS') or define('TEXT_MITS_CDB_RESTORE_CARD_BACKUPS', 'Backup disponibili');
defined('TEXT_MITS_CDB_RESTORE_SAFETY_SUBTITLE') or define('TEXT_MITS_CDB_RESTORE_SAFETY_SUBTITLE', 'Controllare attentamente prima dell\'avvio.');
defined('TEXT_MITS_CDB_RESTORE_ERROR_SAFETY_GZIP') or define('TEXT_MITS_CDB_RESTORE_ERROR_SAFETY_GZIP', 'La compressione GZIP del backup di sicurezza non &egrave; riuscita. Il ripristino non &egrave; stato avviato.');
defined('TEXT_MITS_CDB_RESTORE_TABLE_DOWNLOADS_TOGGLE') or define('TEXT_MITS_CDB_RESTORE_TABLE_DOWNLOADS_TOGGLE', 'Mostra singoli file tabella');
defined('TEXT_MITS_CDB_RESTORE_TABLE_DOWNLOADS_COUNT') or define('TEXT_MITS_CDB_RESTORE_TABLE_DOWNLOADS_COUNT', '%s file');
defined('TEXT_MITS_CDB_RESTORE_TABLE_DOWNLOADS_INFO') or define('TEXT_MITS_CDB_RESTORE_TABLE_DOWNLOADS_INFO', 'Ogni file tabella appartiene a questo backup per tabelle e pu&ograve; essere scaricato, ripristinato o eliminato separatamente.');
defined('TEXT_MITS_CDB_RESTORE_TABLE_FILE_RESTORE') or define('TEXT_MITS_CDB_RESTORE_TABLE_FILE_RESTORE', 'Ripristina questo file tabella.');
defined('TEXT_MITS_CDB_RESTORE_TABLE_PRESELECTED_INFO') or define('TEXT_MITS_CDB_RESTORE_TABLE_PRESELECTED_INFO', 'Solo questo file tabella &egrave; preselezionato: %s');

defined('TEXT_MITS_CDB_SQL_TITLE') or define('TEXT_MITS_CDB_SQL_TITLE', 'Esegui SQL direttamente');
defined('TEXT_MITS_CDB_SQL_SUBTITLE') or define('TEXT_MITS_CDB_SQL_SUBTITLE', 'Casella query per singole istruzioni SQL.');
defined('TEXT_MITS_CDB_SQL_WARNING_TITLE') or define('TEXT_MITS_CDB_SQL_WARNING_TITLE', 'Accesso diretto al database');
defined('TEXT_MITS_CDB_SQL_WARNING') or define('TEXT_MITS_CDB_SQL_WARNING', 'Il codice SQL viene eseguito direttamente sul database attuale dello shop. Creare prima un backup. Le istruzioni di scrittura devono essere abilitate esplicitamente qui sotto.');
defined('TEXT_MITS_CDB_SQL_CODE') or define('TEXT_MITS_CDB_SQL_CODE', 'Codice SQL');
defined('TEXT_MITS_CDB_SQL_ROW_LIMIT') or define('TEXT_MITS_CDB_SQL_ROW_LIMIT', 'Numero massimo di righe risultato mostrate');
defined('TEXT_MITS_CDB_SQL_CONFIRM_WRITE') or define('TEXT_MITS_CDB_SQL_CONFIRM_WRITE', 'Consentire esplicitamente istruzioni SQL di scrittura come INSERT, UPDATE, DELETE, ALTER o DROP.');
defined('TEXT_MITS_CDB_SQL_RUN') or define('TEXT_MITS_CDB_SQL_RUN', 'Esegui SQL');
defined('TEXT_MITS_CDB_SQL_RESULT_SUCCESS') or define('TEXT_MITS_CDB_SQL_RESULT_SUCCESS', 'SQL eseguito');
defined('TEXT_MITS_CDB_SQL_RESULT_ERROR') or define('TEXT_MITS_CDB_SQL_RESULT_ERROR', 'SQL non riuscito');
defined('TEXT_MITS_CDB_SQL_RESULT_SUBTITLE') or define('TEXT_MITS_CDB_SQL_RESULT_SUBTITLE', 'Il risultato dell\'esecuzione SQL &egrave; stato registrato.');
defined('TEXT_MITS_CDB_SQL_RESULT_STATEMENT_TITLE') or define('TEXT_MITS_CDB_SQL_RESULT_STATEMENT_TITLE', 'SQL #%s');
defined('TEXT_MITS_CDB_SQL_STATUS_OK') or define('TEXT_MITS_CDB_SQL_STATUS_OK', 'OK');
defined('TEXT_MITS_CDB_SQL_STATUS_ERROR') or define('TEXT_MITS_CDB_SQL_STATUS_ERROR', 'Errore');
defined('TEXT_MITS_CDB_SQL_RESULT_EMPTY') or define('TEXT_MITS_CDB_SQL_RESULT_EMPTY', 'La query non ha restituito record.');
defined('TEXT_MITS_CDB_SQL_ERROR_EMPTY') or define('TEXT_MITS_CDB_SQL_ERROR_EMPTY', 'Non &egrave; stato inserito alcun codice SQL.');
defined('TEXT_MITS_CDB_SQL_ERROR_CONNECTION') or define('TEXT_MITS_CDB_SQL_ERROR_CONNECTION', 'La connessione al database non &egrave; disponibile.');
defined('TEXT_MITS_CDB_SQL_ERROR_WRITE_CONFIRM') or define('TEXT_MITS_CDB_SQL_ERROR_WRITE_CONFIRM', 'Le istruzioni SQL di scrittura non sono state eseguite perch&eacute; l\'autorizzazione non &egrave; stata abilitata.');
defined('TEXT_MITS_CDB_SQL_ERROR_STATEMENT') or define('TEXT_MITS_CDB_SQL_ERROR_STATEMENT', 'SQL #%s non riuscito: %s');
defined('TEXT_MITS_CDB_SQL_SUCCESS_ROWS') or define('TEXT_MITS_CDB_SQL_SUCCESS_ROWS', '%s record trovati, %s visualizzati.');
defined('TEXT_MITS_CDB_SQL_SUCCESS_AFFECTED') or define('TEXT_MITS_CDB_SQL_SUCCESS_AFFECTED', 'Istruzione SQL eseguita. Righe interessate: %s.');
defined('TEXT_MITS_CDB_SQL_SUCCESS_INSERT_ID') or define('TEXT_MITS_CDB_SQL_SUCCESS_INSERT_ID', 'ID inserimento: %s.');
defined('TEXT_MITS_CDB_SQL_SUCCESS_SUMMARY') or define('TEXT_MITS_CDB_SQL_SUCCESS_SUMMARY', '%s istruzione/i SQL eseguita/e correttamente.');
defined('TEXT_MITS_CDB_RESTORE_DELETE') or define('TEXT_MITS_CDB_RESTORE_DELETE', 'Elimina');
defined('TEXT_MITS_CDB_RESTORE_DELETE_CONFIRM') or define('TEXT_MITS_CDB_RESTORE_DELETE_CONFIRM', 'Eliminare davvero questo backup?\\n\\n%s');
defined('TEXT_MITS_CDB_RESTORE_DELETE_TABLE_CONFIRM') or define('TEXT_MITS_CDB_RESTORE_DELETE_TABLE_CONFIRM', 'Eliminare davvero questo file tabella?\\n\\n%s');
defined('TEXT_MITS_CDB_RESTORE_DELETE_SUCCESS') or define('TEXT_MITS_CDB_RESTORE_DELETE_SUCCESS', 'Backup eliminato: %s');
defined('TEXT_MITS_CDB_RESTORE_DELETE_ERROR') or define('TEXT_MITS_CDB_RESTORE_DELETE_ERROR', 'Impossibile eliminare il backup: %s');
defined('TEXT_MITS_CDB_RESTORE_DELETE_TABLE_SUCCESS') or define('TEXT_MITS_CDB_RESTORE_DELETE_TABLE_SUCCESS', 'File tabella eliminato: %s');
defined('TEXT_MITS_CDB_RESTORE_DELETE_TABLE_ERROR') or define('TEXT_MITS_CDB_RESTORE_DELETE_TABLE_ERROR', 'Impossibile eliminare il file tabella: %s');
defined('TEXT_MITS_CDB_RESTORE_DELETE_TABLE_FOLDER_EMPTY') or define('TEXT_MITS_CDB_RESTORE_DELETE_TABLE_FOLDER_EMPTY', 'La cartella del backup per tabelle non conteneva altri file tabella ed &egrave; stata rimossa.');
defined('TEXT_MITS_CDB_RESTORE_DELETE_RESULT_SUCCESS') or define('TEXT_MITS_CDB_RESTORE_DELETE_RESULT_SUCCESS', 'Backup eliminato');
defined('TEXT_MITS_CDB_RESTORE_DELETE_RESULT_ERROR') or define('TEXT_MITS_CDB_RESTORE_DELETE_RESULT_ERROR', 'Eliminazione non riuscita');
defined('TEXT_MITS_CDB_RESTORE_DELETE_RESULT_SUBTITLE') or define('TEXT_MITS_CDB_RESTORE_DELETE_RESULT_SUBTITLE', 'Il risultato dell\'eliminazione &egrave; stato registrato.');
defined('TEXT_MITS_CDB_SQL_ERROR_FILE_OPERATION') or define('TEXT_MITS_CDB_SQL_ERROR_FILE_OPERATION', 'Le istruzioni SQL con LOAD_FILE, LOAD DATA, INTO OUTFILE o INTO DUMPFILE non vengono eseguite per motivi di sicurezza.');
defined('TEXT_MITS_CDB_RESTORE_SELECT_ALL') or define('TEXT_MITS_CDB_RESTORE_SELECT_ALL', 'Seleziona / deseleziona tutto');
defined('TEXT_MITS_CDB_RESTORE_SELECT_BACKUP') or define('TEXT_MITS_CDB_RESTORE_SELECT_BACKUP', 'Seleziona backup: %s');
defined('TEXT_MITS_CDB_RESTORE_DELETE_SELECTED') or define('TEXT_MITS_CDB_RESTORE_DELETE_SELECTED', 'Elimina selezionati');
defined('TEXT_MITS_CDB_RESTORE_DELETE_SELECTED_CONFIRM') or define('TEXT_MITS_CDB_RESTORE_DELETE_SELECTED_CONFIRM', 'Eliminare definitivamente i backup selezionati?');
defined('TEXT_MITS_CDB_RESTORE_DELETE_SELECTED_INFO') or define('TEXT_MITS_CDB_RESTORE_DELETE_SELECTED_INFO', 'I backup tabella selezionati vengono eliminati completamente, inclusi tutti i file tabella contenuti.');
defined('TEXT_MITS_CDB_RESTORE_DELETE_SELECTED_NONE') or define('TEXT_MITS_CDB_RESTORE_DELETE_SELECTED_NONE', 'Nessun backup selezionato per l\'eliminazione.');
defined('TEXT_MITS_CDB_RESTORE_DELETE_SELECTED_SUMMARY') or define('TEXT_MITS_CDB_RESTORE_DELETE_SELECTED_SUMMARY', '%s backup eliminati, %s non riusciti.');

defined('TEXT_MITS_CDB_BACKUP_TABLES_SELECT_ALL') or define('TEXT_MITS_CDB_BACKUP_TABLES_SELECT_ALL', 'Seleziona / deseleziona tutte le tabelle');
