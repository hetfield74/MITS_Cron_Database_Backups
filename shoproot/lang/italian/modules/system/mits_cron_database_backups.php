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
  $mits_db_backup_cronjoburl = '<hr /><h3>URL CronJob:</h3><textarea style="width: 100%;height:auto;">' . xtc_catalog_href_link('callback/mits_cron_database_backups/mits_cron_database_backups.php', 'pw=' . $mits_hash, 'SSL') . '</textarea><p>Utilizzare questo URL nei propri cron job.</p><p>Il parametro <strong style="color:#900">pw</strong> deve essere sostituito con il valore HASH configurato. Se si modifica il valore HASH, occorre aggiornare anche l&rsquo;URL nel cron job.</p>';
  $mits_db_backup_button = '<hr /><div style="text-align:center;padding:10px;"><a href="'.xtc_catalog_href_link('callback/mits_cron_database_backups/mits_cron_database_backups.php', '', 'SSL').'?pw='.$mits_hash.'" class="button" onclick="this.blur();"><strong>Avvia backup database</strong></a></div>';
  $mits_db_restore_button = '<div style="text-align:center;padding:10px;"><a href="'.xtc_href_link('mits_cron_database_restore.php', '', 'NONSSL').'" class="button" onclick="this.blur();"><strong>Apri strumenti database</strong></a></div><hr />';
} else {
  $mits_db_backup_cronjoburl = '';
  $mits_db_backup_button = '';
  $mits_db_restore_button = '';
}

$mits_disabled_functions = array_map('trim', explode(',', (string)ini_get('disable_functions')));
$mits_exec_enabled = function_exists('exec') && !in_array('exec', $mits_disabled_functions) && strtolower((string)ini_get('safe_mode')) != 1;
$mits_curl_enabled = function_exists('curl_init');
$mits_no_curl = (!$mits_curl_enabled) ? '<div style="padding:6px;background:#ff0;font-size:14px;border:1px solid #900;color:#900;"><strong>L&rsquo;estensione PHP cURL non &egrave; attiva. Il task pianificato modified per questo modulo richiede cURL, in modo che la chiamata di backup possa essere eseguita separatamente tramite l&rsquo;URL di callback.</strong></div>' : '';
$mits_no_exec = (!$mits_exec_enabled) ? '<div style="padding:6px;background:#ff0;font-size:14px;border:1px solid #900;color:#900;"><strong>Il server non dispone delle autorizzazioni necessarie. La funzione <i>exec()</i> &egrave; disattivata. Contattare il provider per abilitarla oppure scegliere un provider con exec attivo, ad es. <a href="https://all-inkl.com/?partner=293050">all-inkl.com</a>.</strong></div>' : '';

$lang_array = array(
  'MODULE_' . $modulname . '_TITLE'       => 'MITS Cron Database Backups <span style="white-space:nowrap;">&copy; by <span style="padding:2px;background:#ffe;color:#6a9;font-weight:bold;">Hetfield (<a href="https://www.merz-it-service.de/" target="_blank">MerZ IT-SerVice</a>)</span></span>',
  'MODULE_' . $modulname . '_DESCRIPTION' => '
   <div>
    <a href="https://www.merz-it-service.de/" target="_blank">
        <img src="' . DIR_WS_CATALOG . 'callback/mits_cron_database_backups/merz-it-service.png" border="0" alt="MerZ IT-SerVice" style="display:block;max-width:100%;height:auto;" />
    </a><br />
    <h3>Backup del database tramite CronJob</h3>
    ' . $mits_no_exec . '
    ' . $mits_no_curl . '
    <div>
      <p>Con questo modulo &egrave; possibile eseguire backup automatici regolari del database del negozio o avviare un backup manualmente quando necessario.</p>
      <ul>
        <li>Creazione automatica e regolare di backup del database del negozio</li>

        <li>Creare opzionalmente una cartella backup tabelle con un file SQL.GZ per tabella</li>
       <li>Ripristino rapido dei backup SQL/SQL.GZ esistenti nell&rsquo;area admin tramite client mysql</li>
        <li>Task pianificato modified opzionale: richiama l&rsquo;URL di callback esistente tramite cURL</li>
        <li>Invio opzionale del backup del database via e-mail</li>
        <li>Caricamento opzionale del backup via FTP su un altro server di backup</li>
        <li>Eliminazione automatica opzionale dei vecchi backup dopo x giorni</li>
        <li>Eliminazione automatica opzionale dei vecchi file LOG di tipo mod_notice, mod_deprecated e mod_strict dopo x giorni</li>
      </ul>
      <div style="text-align:center;">
        <small>La versione pi&ugrave; recente del modulo &egrave; sempre disponibile su GitHub.</small><br />
        <a style="background:#6a9;color:#444" target="_blank" href="https://github.com/hetfield74/MITS_Cron_Database_Backups" class="button" onclick="this.blur();">MITS_Cron_Database_Backups on GitHub</a>
      </div>
      <p>Per domande, problemi o richieste relative a questo modulo o alla modified eCommerce Shopsoftware, contattateci:</p>
      <div style="text-align:center;"><a style="background:#6a9;color:#444" target="_blank" href="https://www.merz-it-service.de/Kontakt.html" class="button" onclick="this.blur();">Pagina contatti su MerZ-IT-SerVice.de</a></div>
    </div>
    ' . $mits_db_backup_cronjoburl . '
    ' . $mits_db_backup_button . '
    ' . $mits_db_restore_button . '
  </div>
',

  'MODULE_' . $modulname . '_STATUS_TITLE' => 'Status',
  'MODULE_' . $modulname . '_STATUS_DESC'  => 'Attiva modulo',

  'MODULE_' . $modulname . '_HASH_TITLE' => 'Valore HASH',
  'MODULE_' . $modulname . '_HASH_DESC'  => 'Il parametro <strong>pw</strong> deve essere sostituito con il valore inserito come HASH, come in questo esempio di URL: pw=<strong style="color:#900">' . $mits_default_hash . '</strong>. Per motivi di sicurezza il valore dovrebbe essere modificato periodicamente. Il nuovo valore HASH deve essere usato anche nell&rsquo;URL di richiamo dello script.',

  'MODULE_' . $modulname . '_GZIP_TITLE' => 'Compressione GZIP',
  'MODULE_' . $modulname . '_GZIP_DESC'  => 'Utilizzare la compressione GZIP per il backup del database?',

  'MODULE_' . $modulname . '_COMPLETE_INSERT_TITLE' => 'Option --complete-insert',
  'MODULE_' . $modulname . '_COMPLETE_INSERT_DESC'  => 'Complete inserts aggiunge i nomi delle colonne al dump SQL. Questo parametro migliora la leggibilit&agrave; e l&rsquo;affidabilit&agrave; del dump. L&rsquo;aggiunta dei nomi delle colonne aumenta la dimensione del dump SQL, ma in combinazione con Extended Insert di solito &egrave; trascurabile.',

  'MODULE_' . $modulname . '_EXTENDED_INSERT_TITLE' => 'Option --extended-insert',
  'MODULE_' . $modulname . '_EXTENDED_INSERT_DESC'  => 'Extended Insert combina pi&ugrave; righe di dati in una sola query INSERT. Ci&ograve; riduce significativamente la dimensione dei grandi dump SQL, aumenta la velocit&agrave; di importazione ed &egrave; generalmente consigliato.',

  'MODULE_' . $modulname . '_SQL_COMMENTS_TITLE' => 'Commenti nel dump SQL',
  'MODULE_' . $modulname . '_SQL_COMMENTS_DESC'  => 'Aggiungere commenti al dump SQL? Oltre ai commenti di mysqldump viene aggiunto un breve commento di intestazione MITS. I commenti vengono ignorati durante il ripristino.',

  'MODULE_' . $modulname . '_BACKUP_MODE_TITLE' => 'Modalit&agrave; backup',
  'MODULE_' . $modulname . '_BACKUP_MODE_DESC'  => '<strong>single</strong> crea un file SQL/SQL.GZ per l\'intero database. <strong>tables</strong> crea una cartella di backup dedicata con un file SQL.GZ per tabella.',

  'MODULE_' . $modulname . '_SENDMAIL_TITLE' => 'Invia backup database via e-mail',
  'MODULE_' . $modulname . '_SENDMAIL_DESC'  => 'Il backup del database deve essere inviato via e-mail?',

  'MODULE_' . $modulname . '_MAILADDRESS_TITLE' => 'Indirizzo e-mail per il backup',
  'MODULE_' . $modulname . '_MAILADDRESS_DESC'  => 'Inserire l&rsquo;indirizzo e-mail a cui inviare il backup del database. Considerare eventuali limiti per allegati di grandi dimensioni.',

  'MODULE_' . $modulname . '_SENDFTP_TITLE' => 'Invia backup database via FTP',
  'MODULE_' . $modulname . '_SENDFTP_DESC'  => 'Il backup del database deve essere caricato via FTP su un altro server?',

  'MODULE_' . $modulname . '_FTP_HOST_TITLE' => 'Server FTP',
  'MODULE_' . $modulname . '_FTP_HOST_DESC'  => 'Nome del server FTP.',

  'MODULE_' . $modulname . '_FTP_USER_TITLE' => 'Nome utente FTP',
  'MODULE_' . $modulname . '_FTP_USER_DESC'  => 'Inserire qui il nome utente FTP.',

  'MODULE_' . $modulname . '_FTP_PASS_TITLE' => 'Password FTP',
  'MODULE_' . $modulname . '_FTP_PASS_DESC'  => 'Inserire qui la password FTP.',

  'MODULE_' . $modulname . '_FTP_PORT_TITLE' => 'Porta FTP',
  'MODULE_' . $modulname . '_FTP_PORT_DESC'  => 'Inserire qui la porta FTP, ad es. 21.',

  'MODULE_' . $modulname . '_FTP_PATH_TITLE' => 'Percorso server FTP',
  'MODULE_' . $modulname . '_FTP_PATH_DESC'  => 'Inserire qui il percorso completo sul server FTP in cui salvare il backup del database.',

  'MODULE_' . $modulname . '_DELETEOLDBACKUPS_TITLE' => 'Attiva eliminazione automatica',
  'MODULE_' . $modulname . '_DELETEOLDBACKUPS_DESC'  => 'Attivare l&rsquo;eliminazione automatica dei vecchi backup del database? Se impostato su <strong>s&igrave;</strong>, tutti i file con estensione <i>.sql</i> e <i>.sql.gz</i> pi&ugrave; vecchi del periodo configurato saranno rimossi automaticamente dalla cartella <i>' . (defined('DIR_ADMIN') ? DIR_ADMIN : 'admin/') . 'backups</i>.',

  'MODULE_' . $modulname . '_DELETEOLDBACKUPS_DAYS_TITLE' => 'Periodo di eliminazione automatica',
  'MODULE_' . $modulname . '_DELETEOLDBACKUPS_DAYS_DESC'  => 'Dopo quanti giorni devono essere eliminati i vecchi backup del database? Inserire solo il numero di giorni in cifre. Rilevante solo se <i>Attiva eliminazione automatica</i> &egrave; impostato su <strong>s&igrave;</strong>.',

  'MODULE_' . $modulname . '_DELETELOGS_TITLE' => 'Elimina vecchi file LOG',
  'MODULE_' . $modulname . '_DELETELOGS_DESC'  => 'Eliminare automaticamente anche i vecchi file LOG di tipo mod_notice, mod_strict e mod_deprecated? Rilevante solo se <i>Attiva eliminazione automatica</i> &egrave; impostato su <strong>s&igrave;</strong>. Il periodo &egrave; identico al valore indicato in <i>Periodo di eliminazione automatica</i>.',

  'MODULE_' . $modulname . '_UPDATE_AVAILABLE_TITLE' => ' <span style="font-weight:bold;color:#900;background:#ff6;padding:2px;border:1px solid #900;">Aggiornare il modulo!</span>',
  'MODULE_' . $modulname . '_UPDATE_AVAILABLE_DESC'  => '',
  'MODULE_' . $modulname . '_UPDATE_FINISHED'        => 'MITS Cron Database Backups &egrave; stato aggiornato.',
  'MODULE_' . $modulname . '_UPDATE_ERROR'           => 'Errore',
  'MODULE_' . $modulname . '_UPDATE_MODUL'           => 'Aggiorna modulo',
  'MODULE_' . $modulname . '_DELETE_MODUL'           => 'Rimuovi completamente MITS Cron Database Backups dal server',
  'MODULE_' . $modulname . '_CONFIRM_DELETE_MODUL'   => 'Rimuovere davvero MITS Cron Database Backups dal server inclusi i file?',
  'MODULE_' . $modulname . '_DELETE_FINISHED'        => 'MITS Cron Database Backups &egrave; stato rimosso dal server.',
);

foreach ($lang_array as $key => $val) {
    defined($key) || define($key, $val);
}
