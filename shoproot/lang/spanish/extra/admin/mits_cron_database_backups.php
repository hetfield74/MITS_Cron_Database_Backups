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
  'TEXT_INFO_TASKS_MITS_CRON_DATABASE_BACKUPS' => 'La copia de seguridad de la base de datos se inicia mediante la URL de callback existente con cURL.',
  'BOX_MITS_CRON_DATABASE_RESTORE' => 'MITS herramientas de base de datos',
);

foreach ($lang_array as $key => $val) {
    defined($key) || define($key, $val);
}
