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
  $mits_db_backup_cronjoburl = '<hr /><h3>URL CronJob:</h3><textarea style="width: 100%;height:auto;">' . xtc_catalog_href_link('callback/mits_cron_database_backups/mits_cron_database_backups.php', 'pw=' . $mits_hash, 'SSL') . '</textarea><p>Utilisez cette URL dans vos t&acirc;ches cron.</p><p>Le param&egrave;tre <strong style="color:#900">pw</strong> doit &ecirc;tre remplac&eacute; par la valeur HASH configur&eacute;e. Si vous modifiez la valeur HASH, vous devez aussi adapter l&rsquo;URL de la t&acirc;che cron.</p>';
  $mits_db_backup_button = '<hr /><div style="text-align:center;padding:10px;"><a href="'.xtc_catalog_href_link('callback/mits_cron_database_backups/mits_cron_database_backups.php', '', 'SSL').'?pw='.$mits_hash.'" class="button" onclick="this.blur();"><strong>D&eacute;marrer la sauvegarde de la base de donn&eacute;es</strong></a></div>';
  $mits_db_restore_button = '<div style="text-align:center;padding:10px;"><a href="'.xtc_href_link('mits_cron_database_restore.php', '', 'NONSSL').'" class="button" onclick="this.blur();"><strong>Ouvrir les outils de base de donn&eacute;es</strong></a></div><hr />';
} else {
  $mits_db_backup_cronjoburl = '';
  $mits_db_backup_button = '';
  $mits_db_restore_button = '';
}

$mits_disabled_functions = array_map('trim', explode(',', (string)ini_get('disable_functions')));
$mits_exec_enabled = function_exists('exec') && !in_array('exec', $mits_disabled_functions) && strtolower((string)ini_get('safe_mode')) != 1;
$mits_curl_enabled = function_exists('curl_init');
$mits_no_curl = (!$mits_curl_enabled) ? '<div style="padding:6px;background:#ff0;font-size:14px;border:1px solid #900;color:#900;"><strong>L&rsquo;extension PHP cURL n&rsquo;est pas active. La t&acirc;che planifi&eacute;e modified de ce module n&eacute;cessite cURL afin que l&rsquo;appel de sauvegarde soit ex&eacute;cut&eacute; isol&eacute;ment via l&rsquo;URL de callback.</strong></div>' : '';
$mits_no_exec = (!$mits_exec_enabled) ? '<div style="padding:6px;background:#ff0;font-size:14px;border:1px solid #900;color:#900;"><strong>Votre serveur ne fournit pas les autorisations n&eacute;cessaires. La fonction <i>exec()</i> est d&eacute;sactiv&eacute;e. Veuillez contacter votre h&eacute;bergeur pour l&rsquo;activer ou choisir un h&eacute;bergeur avec exec activ&eacute;, par exemple <a href="https://all-inkl.com/?partner=293050">all-inkl.com</a>.</strong></div>' : '';

$lang_array = array(
  'MODULE_' . $modulname . '_TITLE'       => 'MITS Cron Database Backups <span style="white-space:nowrap;">&copy; by <span style="padding:2px;background:#ffe;color:#6a9;font-weight:bold;">Hetfield (<a href="https://www.merz-it-service.de/" target="_blank">MerZ IT-SerVice</a>)</span></span>',
  'MODULE_' . $modulname . '_DESCRIPTION' => '
   <div>
    <a href="https://www.merz-it-service.de/" target="_blank">
        <img src="' . DIR_WS_CATALOG . 'callback/mits_cron_database_backups/merz-it-service.png" border="0" alt="MerZ IT-SerVice" style="display:block;max-width:100%;height:auto;" />
    </a><br />
    <h3>Sauvegarde de la base de donn&eacute;es par CronJob</h3>
    ' . $mits_no_exec . '
    ' . $mits_no_curl . '
    <div>
      <p>Ce module vous permet de sauvegarder automatiquement et r&eacute;guli&egrave;rement la base de donn&eacute;es de votre boutique, ou de lancer une sauvegarde manuellement si n&eacute;cessaire.</p>
      <ul>
        <li>Cr&eacute;er automatiquement des sauvegardes r&eacute;guli&egrave;res de la base de donn&eacute;es de la boutique</li>

        <li>Cr&eacute;er en option un dossier de sauvegarde par tables avec un fichier SQL.GZ par table</li>
       <li>Restauration rapide des sauvegardes SQL/SQL.GZ existantes dans l&rsquo;administration via le client mysql</li>
        <li>T&acirc;che planifi&eacute;e modified optionnelle : appelle l&rsquo;URL de callback existante via cURL</li>
        <li>Recevoir la sauvegarde de la base de donn&eacute;es par e-mail en option</li>
        <li>Transf&eacute;rer la sauvegarde par FTP vers un autre serveur de sauvegarde en option</li>
        <li>Supprimer automatiquement les anciennes sauvegardes apr&egrave;s x jours en option</li>
        <li>Supprimer automatiquement les anciens fichiers LOG de type mod_notice, mod_deprecated et mod_strict apr&egrave;s x jours en option</li>
      </ul>
      <div style="text-align:center;">
        <small>La derni&egrave;re version du module est toujours disponible sur GitHub.</small><br />
        <a style="background:#6a9;color:#444" target="_blank" href="https://github.com/hetfield74/MITS_Cron_Database_Backups" class="button" onclick="this.blur();">MITS_Cron_Database_Backups on GitHub</a>
      </div>
      <p>Pour toute question, probl&egrave;me ou demande concernant ce module ou la boutique modified eCommerce, contactez-nous simplement :</p>
      <div style="text-align:center;"><a style="background:#6a9;color:#444" target="_blank" href="https://www.merz-it-service.de/Kontakt.html" class="button" onclick="this.blur();">Page de contact sur MerZ-IT-SerVice.de</a></div>
    </div>
    ' . $mits_db_backup_cronjoburl . '
    ' . $mits_db_backup_button . '
    ' . $mits_db_restore_button . '
  </div>
',

  'MODULE_' . $modulname . '_STATUS_TITLE' => 'Status',
  'MODULE_' . $modulname . '_STATUS_DESC'  => 'Activer le module',

  'MODULE_' . $modulname . '_HASH_TITLE' => 'Valeur HASH',
  'MODULE_' . $modulname . '_HASH_DESC'  => 'Le param&egrave;tre <strong>pw</strong> doit &ecirc;tre remplac&eacute; par la valeur saisie dans HASH, comme dans cet exemple d&rsquo;URL : pw=<strong style="color:#900">' . $mits_default_hash . '</strong>. Pour des raisons de s&eacute;curit&eacute;, cette valeur doit &ecirc;tre modifi&eacute;e r&eacute;guli&egrave;rement. La nouvelle valeur HASH doit ensuite &ecirc;tre utilis&eacute;e dans l&rsquo;URL d&rsquo;appel du script.',

  'MODULE_' . $modulname . '_GZIP_TITLE' => 'Compression GZIP',
  'MODULE_' . $modulname . '_GZIP_DESC'  => 'La compression GZIP doit-elle &ecirc;tre utilis&eacute;e pour la sauvegarde de la base de donn&eacute;es ?',

  'MODULE_' . $modulname . '_COMPLETE_INSERT_TITLE' => 'Option --complete-insert',
  'MODULE_' . $modulname . '_COMPLETE_INSERT_DESC'  => 'Complete inserts ajoute les noms de colonnes au dump SQL. Ce param&egrave;tre am&eacute;liore la lisibilit&eacute; et la fiabilit&eacute; du dump. L&rsquo;ajout des noms de colonnes augmente la taille du dump SQL, mais cela reste g&eacute;n&eacute;ralement n&eacute;gligeable avec Extended Insert.',

  'MODULE_' . $modulname . '_EXTENDED_INSERT_TITLE' => 'Option --extended-insert',
  'MODULE_' . $modulname . '_EXTENDED_INSERT_DESC'  => 'Extended Insert combine plusieurs lignes de donn&eacute;es dans une seule requ&ecirc;te INSERT. Cela r&eacute;duit fortement la taille des gros dumps SQL, acc&eacute;l&egrave;re l&rsquo;import et est g&eacute;n&eacute;ralement recommand&eacute;.',

  'MODULE_' . $modulname . '_SQL_COMMENTS_TITLE' => 'Commentaires dans le dump SQL',
  'MODULE_' . $modulname . '_SQL_COMMENTS_DESC'  => 'Ajouter des commentaires au dump SQL ? En plus des commentaires mysqldump, un court commentaire d\'en-t&ecirc;te MITS est ajout&eacute;. Les commentaires sont ignor&eacute;s lors de la restauration.',

  'MODULE_' . $modulname . '_BACKUP_MODE_TITLE' => 'Mode de sauvegarde',
  'MODULE_' . $modulname . '_BACKUP_MODE_DESC'  => '<strong>single</strong> cr&eacute;e un fichier SQL/SQL.GZ pour toute la base de donn&eacute;es. <strong>tables</strong> cr&eacute;e un dossier de sauvegarde s&eacute;par&eacute; avec un fichier SQL.GZ par table.',

  'MODULE_' . $modulname . '_SENDMAIL_TITLE' => 'Envoyer la sauvegarde par e-mail',
  'MODULE_' . $modulname . '_SENDMAIL_DESC'  => 'La sauvegarde de la base de donn&eacute;es doit-elle &ecirc;tre envoy&eacute;e par e-mail ?',

  'MODULE_' . $modulname . '_MAILADDRESS_TITLE' => 'Adresse e-mail pour la sauvegarde',
  'MODULE_' . $modulname . '_MAILADDRESS_DESC'  => 'Saisissez l&rsquo;adresse e-mail &agrave; laquelle la sauvegarde doit &ecirc;tre envoy&eacute;e. Tenez compte des limites possibles pour les pi&egrave;ces jointes volumineuses.',

  'MODULE_' . $modulname . '_SENDFTP_TITLE' => 'Envoyer la sauvegarde par FTP',
  'MODULE_' . $modulname . '_SENDFTP_DESC'  => 'La sauvegarde doit-elle &ecirc;tre envoy&eacute;e par FTP vers un autre serveur ?',

  'MODULE_' . $modulname . '_FTP_HOST_TITLE' => 'Serveur FTP',
  'MODULE_' . $modulname . '_FTP_HOST_DESC'  => 'Nom du serveur FTP.',

  'MODULE_' . $modulname . '_FTP_USER_TITLE' => 'Nom d&rsquo;utilisateur FTP',
  'MODULE_' . $modulname . '_FTP_USER_DESC'  => 'Saisissez ici le nom d&rsquo;utilisateur FTP.',

  'MODULE_' . $modulname . '_FTP_PASS_TITLE' => 'Mot de passe FTP',
  'MODULE_' . $modulname . '_FTP_PASS_DESC'  => 'Saisissez ici le mot de passe FTP.',

  'MODULE_' . $modulname . '_FTP_PORT_TITLE' => 'Port FTP',
  'MODULE_' . $modulname . '_FTP_PORT_DESC'  => 'Saisissez ici le port FTP, par ex. 21.',

  'MODULE_' . $modulname . '_FTP_PATH_TITLE' => 'Chemin serveur FTP',
  'MODULE_' . $modulname . '_FTP_PATH_DESC'  => 'Saisissez ici le chemin complet du serveur FTP o&ugrave; la sauvegarde doit &ecirc;tre stock&eacute;e.',

  'MODULE_' . $modulname . '_DELETEOLDBACKUPS_TITLE' => 'Activer la suppression automatique',
  'MODULE_' . $modulname . '_DELETEOLDBACKUPS_DESC'  => 'Activer la suppression automatique des anciennes sauvegardes ? Si <strong>oui</strong>, tous les fichiers avec l&rsquo;extension <i>.sql</i> et <i>.sql.gz</i> plus anciens que la p&eacute;riode configur&eacute;e seront automatiquement supprim&eacute;s du dossier <i>' . (defined('DIR_ADMIN') ? DIR_ADMIN : 'admin/') . 'backups</i>.',

  'MODULE_' . $modulname . '_DELETEOLDBACKUPS_DAYS_TITLE' => 'P&eacute;riode de suppression automatique',
  'MODULE_' . $modulname . '_DELETEOLDBACKUPS_DAYS_DESC'  => 'Apr&egrave;s combien de jours les anciennes sauvegardes doivent-elles &ecirc;tre supprim&eacute;es ? Saisissez uniquement le nombre de jours en chiffres. Pertinent uniquement si <i>Activer la suppression automatique</i> est sur <strong>oui</strong>.',

  'MODULE_' . $modulname . '_DELETELOGS_TITLE' => 'Supprimer les anciens fichiers LOG',
  'MODULE_' . $modulname . '_DELETELOGS_DESC'  => 'Les anciens fichiers LOG de type mod_notice, mod_strict et mod_deprecated doivent-ils aussi &ecirc;tre supprim&eacute;s automatiquement ? Pertinent uniquement si <i>Activer la suppression automatique</i> est sur <strong>oui</strong>. La p&eacute;riode est identique &agrave; celle indiqu&eacute;e pour <i>P&eacute;riode de suppression automatique</i>.',

  'MODULE_' . $modulname . '_UPDATE_AVAILABLE_TITLE' => ' <span style="font-weight:bold;color:#900;background:#ff6;padding:2px;border:1px solid #900;">Veuillez mettre &agrave; jour le module !</span>',
  'MODULE_' . $modulname . '_UPDATE_AVAILABLE_DESC'  => '',
  'MODULE_' . $modulname . '_UPDATE_FINISHED'        => 'MITS Cron Database Backups a &eacute;t&eacute; mis &agrave; jour.',
  'MODULE_' . $modulname . '_UPDATE_ERROR'           => 'Erreur',
  'MODULE_' . $modulname . '_UPDATE_MODUL'           => 'Mettre &agrave; jour le module',
  'MODULE_' . $modulname . '_DELETE_MODUL'           => 'Supprimer compl&egrave;tement MITS Cron Database Backups du serveur',
  'MODULE_' . $modulname . '_CONFIRM_DELETE_MODUL'   => 'Voulez-vous vraiment supprimer MITS Cron Database Backups du serveur, fichiers inclus ?',
  'MODULE_' . $modulname . '_DELETE_FINISHED'        => 'MITS Cron Database Backups a &eacute;t&eacute; supprim&eacute; du serveur.',
);

foreach ($lang_array as $key => $val) {
    defined($key) || define($key, $val);
}
