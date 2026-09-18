<?php
/**
 * --------------------------------------------------------------
 * File: mits_cron_database_backups.php
 * Created by PhpStorm
 * Date: 06.09.2026
 * Time: 14:05
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
        mits_cdb_scheduled_log('Scheduled Task beendet: Modul ist deaktiviert.');
        return true;
    }

    $callback_file = mits_cdb_scheduled_callback_file();
    $backup_dir = mits_cdb_scheduled_backup_dir();

    mits_cdb_scheduled_log(
        'Scheduled Task gestartet',
        array(
            'sapi' => PHP_SAPI,
            'cwd' => getcwd(),
            'euid' => function_exists('posix_geteuid') ? posix_geteuid() : 'n/a',
            'egid' => function_exists('posix_getegid') ? posix_getegid() : 'n/a',
            'callback' => mits_cdb_scheduled_path_info($callback_file),
            'backup_dir' => mits_cdb_scheduled_path_info($backup_dir),
            'open_basedir' => (string)ini_get('open_basedir'),
        )
    );

    if (!defined('MODULE_MITS_CRON_DATABASE_BACKUPS_HASH') || MODULE_MITS_CRON_DATABASE_BACKUPS_HASH == '') {
        mits_cdb_scheduled_log('Scheduled Task abgebrochen: Callback-HASH fehlt.');
        return false;
    }

    if (!function_exists('curl_init')) {
        mits_cdb_scheduled_log('Scheduled Task abgebrochen: PHP-cURL ist nicht verfuegbar.');
        return false;
    }

    $url = mits_cron_database_backups_scheduled_task_url();
    if ($url == '') {
        mits_cdb_scheduled_log('Scheduled Task abgebrochen: Callback-URL konnte nicht ermittelt werden.');
        return false;
    }

    $http_auth = mits_cdb_scheduled_http_auth_config();
    if (!$http_auth['valid']) {
        mits_cdb_scheduled_log(
            'Scheduled Task abgebrochen: HTTP-Basic-Auth-Konfiguration ist ungueltig.',
            array('http_basic_auth' => $http_auth['enabled'] ? 'enabled' : 'disabled', 'reason' => $http_auth['error'])
        );
        return false;
    }

    mits_cdb_scheduled_log(
        'Callback-Aufruf wird gestartet',
        array(
            'url' => mits_cdb_scheduled_mask_url($url),
            'http_basic_auth' => $http_auth['enabled'] ? 'enabled' : 'disabled',
        )
    );

    $request = mits_cdb_scheduled_curl_request($url, false, $http_auth);
    mits_cdb_scheduled_log_request('Callback-Aufruf beendet', $request);

    if (($request['result'] === false || (int)$request['http_code'] === 0)
        && defined('CURL_IPRESOLVE_V4')
        && defined('CURLOPT_IPRESOLVE')) {
        mits_cdb_scheduled_log('Erster Callback-Aufruf ohne verwertbare HTTP-Antwort. Wiederholung ueber IPv4.');
        $request = mits_cdb_scheduled_curl_request($url, true, $http_auth);
        mits_cdb_scheduled_log_request('IPv4-Wiederholung beendet', $request);
    }

    if ($request['result'] === false) {
        mits_cdb_scheduled_log('Scheduled Task fehlgeschlagen: cURL konnte den Callback nicht aufrufen.');
        return false;
    }

    $http_code = (int)$request['http_code'];
    if ($http_code < 200 || $http_code >= 400) {
        if ($http_code === 401) {
            mits_cdb_scheduled_log(
                'Scheduled Task fehlgeschlagen: HTTP 401 Unauthorized. HTTP-Basic-Auth fuer den geschuetzten Shop fehlt oder wurde nicht akzeptiert.',
                array(
                    'http_basic_auth' => $http_auth['enabled'] ? 'enabled' : 'disabled',
                    'response' => mits_cdb_scheduled_response_excerpt($request['result']),
                )
            );
        } else {
            mits_cdb_scheduled_log(
                'Scheduled Task fehlgeschlagen: Callback lieferte einen HTTP-Fehler.',
                array('response' => mits_cdb_scheduled_response_excerpt($request['result']))
            );
        }
        return false;
    }

    if (strpos($request['result'], 'MITS_CRON_DATABASE_BACKUPS_SUCCESS') === false) {
        mits_cdb_scheduled_log(
            'Scheduled Task fehlgeschlagen: Erfolgskennung fehlt in der Callback-Antwort.',
            array('response' => mits_cdb_scheduled_response_excerpt($request['result']))
        );
        return false;
    }

    mits_cdb_scheduled_log('Scheduled Task erfolgreich abgeschlossen.');
    return true;
}

function mits_cdb_scheduled_http_auth_config()
{
    $enabled = defined('MODULE_MITS_CRON_DATABASE_BACKUPS_HTTP_AUTH')
        && strtolower((string)MODULE_MITS_CRON_DATABASE_BACKUPS_HTTP_AUTH) === 'true';

    $config = array(
        'enabled' => $enabled,
        'valid' => true,
        'username' => '',
        'password' => '',
        'error' => '',
    );

    if (!$enabled) {
        return $config;
    }

    $config['username'] = defined('MODULE_MITS_CRON_DATABASE_BACKUPS_HTTP_AUTH_USER')
        ? (string)MODULE_MITS_CRON_DATABASE_BACKUPS_HTTP_AUTH_USER
        : '';
    if (trim($config['username']) === '') {
        $config['valid'] = false;
        $config['error'] = 'HTTP-Basic-Auth-Benutzername fehlt.';
        return $config;
    }

    $encrypted = defined('MODULE_MITS_CRON_DATABASE_BACKUPS_HTTP_AUTH_PASS')
        ? (string)MODULE_MITS_CRON_DATABASE_BACKUPS_HTTP_AUTH_PASS
        : '';

    $helper = defined('DIR_FS_CATALOG')
        ? rtrim(DIR_FS_CATALOG, '/\\') . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'mits_cron_database_sync.php'
        : '';
    if ($helper === '' || !is_file($helper)) {
        $config['valid'] = false;
        $config['error'] = 'Verschluesselungshelfer fuer HTTP-Basic-Auth fehlt.';
        return $config;
    }

    require_once $helper;
    if (!function_exists('mits_cdb_sync_decrypt_password')) {
        $config['valid'] = false;
        $config['error'] = 'HTTP-Basic-Auth-Passwort kann nicht entschluesselt werden.';
        return $config;
    }

    $password = mits_cdb_sync_decrypt_password($encrypted);
    if ($password === false) {
        $config['valid'] = false;
        $config['error'] = 'HTTP-Basic-Auth-Passwort kann nicht entschluesselt werden. Passwort im Systemmodul neu speichern.';
        return $config;
    }

    $config['password'] = $password;
    return $config;
}

function mits_cdb_scheduled_curl_request($url, $force_ipv4 = false, $http_auth = array())
{
    $response = array(
        'result' => false,
        'curl_errno' => 0,
        'curl_error' => '',
        'http_code' => 0,
        'effective_url' => '',
        'primary_ip' => '',
        'redirect_count' => 0,
        'total_time' => 0,
        'ipv4' => $force_ipv4 ? 'yes' : 'no',
    );

    $ch = curl_init();
    if ($ch === false) {
        $response['curl_error'] = 'curl_init() fehlgeschlagen';
        return $response;
    }

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_TIMEOUT, 7200);
    curl_setopt($ch, CURLOPT_USERAGENT, 'MITS CronDatabaseBackups Scheduled Task');
    curl_setopt($ch, CURLOPT_NOSIGNAL, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: text/html,*/*;q=0.8', 'Connection: close'));

    if (!empty($http_auth['enabled'])) {
        if (!defined('CURLAUTH_BASIC') || !defined('CURLOPT_HTTPAUTH') || !defined('CURLOPT_USERPWD')) {
            $response['curl_error'] = 'HTTP Basic Auth wird von dieser cURL-Installation nicht unterstuetzt.';
            curl_close($ch);
            return $response;
        }
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, (string)$http_auth['username'] . ':' . (string)$http_auth['password']);
    }

    @curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

    if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS')) {
        @curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
    }
    if (defined('CURLOPT_REDIR_PROTOCOLS') && defined('CURLPROTO_HTTP') && defined('CURLPROTO_HTTPS')) {
        @curl_setopt($ch, CURLOPT_REDIR_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
    }
    if ($force_ipv4 && defined('CURLOPT_IPRESOLVE') && defined('CURL_IPRESOLVE_V4')) {
        @curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    }

    $response['result'] = curl_exec($ch);
    $response['curl_errno'] = curl_errno($ch);
    $response['curl_error'] = curl_error($ch);
    $response['http_code'] = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $response['effective_url'] = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    if (defined('CURLINFO_PRIMARY_IP')) {
        $response['primary_ip'] = (string)curl_getinfo($ch, CURLINFO_PRIMARY_IP);
    }
    if (defined('CURLINFO_REDIRECT_COUNT')) {
        $response['redirect_count'] = (int)curl_getinfo($ch, CURLINFO_REDIRECT_COUNT);
    }
    if (defined('CURLINFO_TOTAL_TIME')) {
        $response['total_time'] = (float)curl_getinfo($ch, CURLINFO_TOTAL_TIME);
    }

    curl_close($ch);
    return $response;
}

function mits_cdb_scheduled_log_request($message, $request)
{
    mits_cdb_scheduled_log(
        $message,
        array(
            'ipv4_forced' => isset($request['ipv4']) ? $request['ipv4'] : 'no',
            'curl_errno' => isset($request['curl_errno']) ? $request['curl_errno'] : '',
            'curl_error' => isset($request['curl_error']) ? $request['curl_error'] : '',
            'http_code' => isset($request['http_code']) ? $request['http_code'] : '',
            'primary_ip' => isset($request['primary_ip']) ? $request['primary_ip'] : '',
            'redirects' => isset($request['redirect_count']) ? $request['redirect_count'] : '',
            'time' => isset($request['total_time']) ? $request['total_time'] : '',
            'effective_url' => isset($request['effective_url']) ? mits_cdb_scheduled_mask_url($request['effective_url']) : '',
        )
    );
}

function mits_cdb_scheduled_write_log_enabled()
{
    if (!defined('MODULE_MITS_CRON_DATABASE_BACKUPS_WRITE_LOG')) {
        return true;
    }
    return strtolower((string)MODULE_MITS_CRON_DATABASE_BACKUPS_WRITE_LOG) == 'true';
}

function mits_cdb_scheduled_log($message, $context = array())
{
    if (!mits_cdb_scheduled_write_log_enabled()) {
        return;
    }

    $parts = array((string)$message);
    foreach ($context as $key => $value) {
        if (is_bool($value)) {
            $value = $value ? 'yes' : 'no';
        } elseif (is_array($value) || is_object($value)) {
            $value = json_encode($value);
        }
        $value = str_replace(array("\r", "\n"), ' ', (string)$value);
        $parts[] = $key . '=' . $value;
    }

    $line = '[' . date('Y-m-d H:i:s') . '] [scheduled-task] ' . implode(' | ', $parts) . PHP_EOL;
    $written = false;

    if (defined('DIR_FS_LOG') && is_dir(DIR_FS_LOG) && is_writable(DIR_FS_LOG)) {
        $written = (@error_log($line, 3, DIR_FS_LOG . 'mits_cron_database_backups_' . date('Y-m') . '.log') !== false);
    }

    if (!$written) {
        @error_log(rtrim($line));
    }
}

function mits_cdb_scheduled_mask_url($url)
{
    return preg_replace('/([?&]pw=)[^&]*/i', '$1***', (string)$url);
}

function mits_cdb_scheduled_response_excerpt($response)
{
    $text = html_entity_decode(strip_tags((string)$response), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/', ' ', trim($text));
    if (strlen($text) > 600) {
        $text = substr($text, 0, 600) . '...';
    }
    return $text;
}

function mits_cdb_scheduled_file_mode($path)
{
    $perms = @fileperms($path);
    if ($perms === false) {
        return 'n/a';
    }
    return substr(sprintf('%04o', $perms & 0777), -4);
}

function mits_cdb_scheduled_path_info($path)
{
    if ($path == '') {
        return 'n/a';
    }

    $exists = file_exists($path);
    return $path
        . ';exists=' . ($exists ? 'yes' : 'no')
        . ';readable=' . (is_readable($path) ? 'yes' : 'no')
        . ';writable=' . (is_writable($path) ? 'yes' : 'no')
        . ';mode=' . mits_cdb_scheduled_file_mode($path)
        . ';uid=' . ($exists && @fileowner($path) !== false ? @fileowner($path) : 'n/a')
        . ';gid=' . ($exists && @filegroup($path) !== false ? @filegroup($path) : 'n/a');
}

function mits_cdb_scheduled_callback_file()
{
    if (defined('DIR_FS_CATALOG')) {
        return rtrim(DIR_FS_CATALOG, '/\\') . DIRECTORY_SEPARATOR . 'callback' . DIRECTORY_SEPARATOR . 'mits_cron_database_backups' . DIRECTORY_SEPARATOR . 'mits_cron_database_backups.php';
    }
    if (defined('DIR_FS_DOCUMENT_ROOT')) {
        return rtrim(DIR_FS_DOCUMENT_ROOT, '/\\') . DIRECTORY_SEPARATOR . 'callback' . DIRECTORY_SEPARATOR . 'mits_cron_database_backups' . DIRECTORY_SEPARATOR . 'mits_cron_database_backups.php';
    }
    return '';
}

function mits_cdb_scheduled_backup_dir()
{
    if (defined('DIR_FS_CATALOG')) {
        return rtrim(DIR_FS_CATALOG, '/\\') . DIRECTORY_SEPARATOR . 'export' . DIRECTORY_SEPARATOR . 'mits_cron_database_backups';
    }
    if (defined('DIR_FS_DOCUMENT_ROOT')) {
        return rtrim(DIR_FS_DOCUMENT_ROOT, '/\\') . DIRECTORY_SEPARATOR . 'export' . DIRECTORY_SEPARATOR . 'mits_cron_database_backups';
    }
    return '';
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

    $catalog = defined('DIR_WS_CATALOG') ? trim((string)DIR_WS_CATALOG, '/') : '';
    $path = ($catalog != '' ? $catalog . '/' : '') . 'callback/mits_cron_database_backups/mits_cron_database_backups.php';

    return rtrim($server, '/') . '/' . $path . '?' . $params;
}
?>
