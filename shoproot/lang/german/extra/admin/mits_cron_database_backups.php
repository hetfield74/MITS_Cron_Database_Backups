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
  'TEXT_HEADING_TASKS_MITS_CRON_DATABASE_BACKUPS' => 'MITS CronDatabaseBackups',
  'TEXT_INFO_TASKS_MITS_CRON_DATABASE_BACKUPS' => 'Datenbanksicherung wird &uuml;ber die bestehende Callback-URL per cURL gestartet.',
  'BOX_MITS_CRON_DATABASE_RESTORE' => 'MITS Datenbank-Werkzeuge',
);

foreach ($lang_array as $key => $val) {
    defined($key) || define($key, $val);
}
