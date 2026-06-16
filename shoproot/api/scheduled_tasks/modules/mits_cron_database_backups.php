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

function cron_mits_cron_database_backups()
{
    if (defined('MODULE_MITS_CRON_DATABASE_BACKUPS_STATUS') && MODULE_MITS_CRON_DATABASE_BACKUPS_STATUS != 'true') {
        return true;
    }

    if (!defined('MODULE_MITS_CRON_DATABASE_BACKUPS_HASH') || MODULE_MITS_CRON_DATABASE_BACKUPS_HASH == '') {
        return false;
    }

    if (!function_exists('curl_init')) {
        return false;
    }

    $url = mits_cron_database_backups_scheduled_task_url();
    if ($url == '') {
        return false;
    }

    $ch = curl_init();
    if ($ch === false) {
        return false;
    }

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 7200);
    curl_setopt($ch, CURLOPT_USERAGENT, 'MITS CronDatabaseBackups Scheduled Task');

    if (!ini_get('open_basedir') && strtolower(ini_get('safe_mode')) != 1) {
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    }

    $result = curl_exec($ch);
    $http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($result === false || $http_code < 200 || $http_code >= 400) {
        return false;
    }

    return (strpos($result, 'MITS_CRON_DATABASE_BACKUPS_SUCCESS') !== false);
}

function mits_cron_database_backups_scheduled_task_url()
{
    $params = 'pw=' . rawurlencode(MODULE_MITS_CRON_DATABASE_BACKUPS_HASH);

    if (function_exists('xtc_catalog_href_link')) {
        return str_replace('&amp;', '&', xtc_catalog_href_link('callback/mits_cron_database_backups/mits_cron_database_backups.php', $params, 'SSL'));
    }

    $server = '';
    if (defined('HTTPS_SERVER') && HTTPS_SERVER != '') {
        $server = HTTPS_SERVER;
    } elseif (defined('HTTP_SERVER') && HTTP_SERVER != '') {
        $server = HTTP_SERVER;
    }

    if ($server == '') {
        return '';
    }

    $catalog = defined('DIR_WS_CATALOG') ? DIR_WS_CATALOG : '/';
    return rtrim($server, '/') . '/' . trim($catalog, '/') . '/callback/mits_cron_database_backups/mits_cron_database_backups.php?' . $params;
}
?>
