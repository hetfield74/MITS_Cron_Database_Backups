<?php
/**
 * --------------------------------------------------------------
 * File: mits_cron_database_backups.php
 * Created by PhpStorm
 * Date: 12.06.2026
 * Time: 18:00
 *
 * Author: Hetfield
 * Copyright: (c) 2026 - MerZ IT-SerVice
 * Web: https://www.merz-it-service.de
 * Contact: info@merz-it-service.de
 *
 * Released under the GNU General Public License
 * --------------------------------------------------------------
 */

$lang_array = array(
  'TEXT_HEADING_TASKS_MITS_CRON_DATABASE_BACKUPS' => 'MITS Cron Database Backups',
  'TEXT_INFO_TASKS_MITS_CRON_DATABASE_BACKUPS' => 'La sauvegarde de la base de donn&eacute;es est d&eacute;marr&eacute;e via l&rsquo;URL de callback existante. Les erreurs de connexion, HTTP et d&rsquo;ex&eacute;cution sont consign&eacute;es dans le journal MITS lorsque la journalisation est activ&eacute;e.',
  'BOX_MITS_CRON_DATABASE_RESTORE' => 'Gestion de base de donn&eacute;es MITS',
  'BOX_MITS_CRON_DATABASE_TOOLS' => 'Maintenance de base de donn&eacute;es MITS',
);

if (defined('TABLE_CONFIGURATION') && function_exists('xtc_db_query') && function_exists('xtc_db_num_rows')) {
    $mits_sync_profile_table = (substr(TABLE_CONFIGURATION, -13) === 'configuration')
        ? substr(TABLE_CONFIGURATION, 0, -13) . 'mits_cdb_sync_profiles'
        : 'mits_cdb_sync_profiles';
    $mits_sync_profile_table_like = str_replace(array('\\', '_', '%'), array('\\\\', '\\_', '\\%'), $mits_sync_profile_table);
    $mits_sync_profile_check = @xtc_db_query("SHOW TABLES LIKE '" . $mits_sync_profile_table_like . "'");
    if ($mits_sync_profile_check && xtc_db_num_rows($mits_sync_profile_check) > 0) {
        $mits_sync_profile_query = xtc_db_query("SELECT profile_id, name FROM `" . $mits_sync_profile_table . "` ORDER BY profile_id ASC");
        while ($mits_sync_profile = xtc_db_fetch_array($mits_sync_profile_query)) {
            $mits_sync_suffix = 'MITS_CRON_DATABASE_SYNC_PROFILE_' . (int)$mits_sync_profile['profile_id'];
            $mits_sync_name = htmlspecialchars((string)$mits_sync_profile['name'], ENT_QUOTES, 'UTF-8');
            $lang_array['TEXT_HEADING_TASKS_' . $mits_sync_suffix] = 'MITS DB Sync: ' . $mits_sync_name;
            $lang_array['TEXT_INFO_TASKS_' . $mits_sync_suffix] = 'Runs exactly this MITS database synchronization profile. Interval, anchor time and status can be changed independently from other profiles.';
        }
    }
}

foreach ($lang_array as $key => $val) {
    defined($key) || define($key, $val);
}
