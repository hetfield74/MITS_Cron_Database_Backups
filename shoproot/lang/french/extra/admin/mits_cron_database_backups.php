<?php
/**
 * --------------------------------------------------------------
 * File: mits_cron_database_backups.php
 * Date: 12.06.2026
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
  'TEXT_INFO_TASKS_MITS_CRON_DATABASE_BACKUPS' => 'La sauvegarde de la base de donn&eacute;es est d&eacute;marr&eacute;e via l\'URL de callback existante par cURL.',
  'BOX_MITS_CRON_DATABASE_RESTORE' => 'MITS outils de base de donn&eacute;es',
);

foreach ($lang_array as $key => $val) {
    defined($key) || define($key, $val);
}
