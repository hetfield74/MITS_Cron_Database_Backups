<?php
/**
 * --------------------------------------------------------------
 * File: mits_cron_database_restore.php
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

defined('_VALID_XTC') or die('Direct Access to this location is not allowed.');

if (defined('MODULE_MITS_CRON_DATABASE_BACKUPS_STATUS') && strtolower(MODULE_MITS_CRON_DATABASE_BACKUPS_STATUS) == 'true') {
    $add_contents[BOX_HEADING_TOOLS][] = array(
        'admin_access_name' => 'mits_cron_database_restore',
        'filename' => 'mits_cron_database_restore.php',
        'boxname' => BOX_MITS_CRON_DATABASE_RESTORE,
        'parameters' => '',
        'ssl' => ''
    );
}
?>
