<?php
/**
 * Lightweight update checker for MITS modules.
 *
 * Copy this class into the target shop once and let individual modules call
 * MitsModuleUpdateClient::check($moduleKey, $installedVersion).
 *
 * The client only reads public release metadata. It never installs files and
 * never sends customer, order or shop data.
 */

class MitsModuleUpdateClient
{
    public const VERSION = '0.1.8';
    public const DEFAULT_ENDPOINT = 'https://www.merz-it-service.de/api/mits_module_update.php';

    public static function check(string $moduleKey, string $installedVersion, array $options = array()): array
    {
        $moduleKey = self::normalizeModuleKey($moduleKey);
        $installedVersion = self::normalizeVersion($installedVersion);
        $result = self::emptyResult($moduleKey, $installedVersion);
        if ($moduleKey === '' || $installedVersion === '') {
            $result['error'] = 'module_or_version_missing';
            return $result;
        }

        $endpoint = trim((string)($options['endpoint'] ?? self::configuredEndpoint()));
        if (!self::isHttpsUrl($endpoint)) {
            $result['error'] = 'invalid_endpoint';
            return $result;
        }
        $ttl = max(300, min(604800, (int)($options['cache_ttl'] ?? 86400)));
        $force = !empty($options['force']);
        $cacheFile = self::cacheFile($endpoint, $moduleKey, $options);

        $payload = null;
        $cacheMtime = is_file($cacheFile) ? (int)@filemtime($cacheFile) : 0;
        if ($cacheMtime > 0) {
            $result['last_checked_at'] = $cacheMtime;
        }
        if (!$force && $cacheMtime > 0 && $cacheMtime >= time() - $ttl) {
            $cached = @file_get_contents($cacheFile);
            $decoded = is_string($cached) ? json_decode($cached, true) : null;
            if (is_array($decoded)) {
                $payload = $decoded;
                $result['from_cache'] = true;
            }
        }

        if ($payload === null) {
            $url = $endpoint . (strpos($endpoint, '?') === false ? '?' : '&') . 'module=' . rawurlencode($moduleKey);
            $response = self::request($url, (int)($options['timeout'] ?? 6));
            if (!$response['ok']) {
                $result['error'] = (string)$response['error'];
                return $result;
            }
            $payload = json_decode((string)$response['body'], true);
            if (!is_array($payload)) {
                $result['error'] = 'invalid_json';
                return $result;
            }
            self::writeCache($cacheFile, (string)$response['body']);
            $result['last_checked_at'] = is_file($cacheFile) ? (int)@filemtime($cacheFile) : time();
        }

        if (!isset($payload['latest']) || !is_array($payload['latest'])) {
            $result['ok'] = true;
            $result['access_mode'] = (string)($payload['access_mode'] ?? '');
            return $result;
        }

        $latest = $payload['latest'];
        $latestVersion = self::normalizeVersion((string)($latest['version'] ?? ''));
        if ($latestVersion === '') {
            $result['error'] = 'latest_version_missing';
            return $result;
        }

        $result['ok'] = true;
        $result['latest_version'] = $latestVersion;
        $result['update_available'] = version_compare($latestVersion, $installedVersion, '>');
        $result['access_mode'] = (string)($payload['access_mode'] ?? '');
        $result['released_at'] = (string)($latest['released_at'] ?? '');
        $result['download_url'] = self::safeUrl((string)($latest['download_url'] ?? ''));
        $result['product_url'] = self::safeUrl((string)($latest['product_url'] ?? ''));
        $result['account_url'] = self::safeUrl((string)($latest['account_url'] ?? ''));
        $result['sha256'] = preg_match('/^[a-f0-9]{64}$/i', (string)($latest['sha256'] ?? '')) ? strtolower((string)$latest['sha256']) : '';
        $result['size'] = max(0, (int)($latest['size'] ?? 0));
        $notes = (string)($latest['notes'] ?? '');
        $result['notes'] = function_exists('mb_substr') ? mb_substr($notes, 0, 20000) : substr($notes, 0, 20000);
        return $result;
    }

    /**
     * One-call integration for classic modified system modules.
     *
     * Expected public properties on $module:
     *   code, version, title and description.
     *
     * The method never throws an update-check exception to the calling module.
     * It returns the same result array as check() for optional diagnostics.
     */
    public static function integrate($module, array $options = array()): array
    {
        $moduleKey = '';
        $installedVersion = '';
        try {
            if (!is_object($module)) {
                $result = self::emptyResult('', '');
                $result['error'] = 'module_object_missing';
                return $result;
            }

            $moduleKey = isset($options['module_key']) ? (string)$options['module_key'] : (isset($module->code) ? (string)$module->code : '');
            $installedVersion = isset($module->version) ? (string)$module->version : '';
            $checkOptions = isset($options['check']) && is_array($options['check']) ? $options['check'] : array();
            foreach (array('endpoint', 'cache_ttl', 'cache_dir', 'timeout', 'force') as $key) {
                if (array_key_exists($key, $options)) {
                    $checkOptions[$key] = $options[$key];
                }
            }

            if (self::manualRefreshRequested($moduleKey, $checkOptions)) {
                $checkOptions['force'] = true;
            }

            $result = self::check($moduleKey, $installedVersion, $checkOptions);
            $result['refresh_url'] = self::manualRefreshUrl($moduleKey, (int)($result['last_checked_at'] ?? 0));

            $showTitle = !array_key_exists('show_title_badge', $options) || (bool)$options['show_title_badge'];
            $showNotice = !array_key_exists('show_notice', $options) || (bool)$options['show_notice'];

            if ($showTitle && !empty($result['ok']) && !empty($result['update_available']) && isset($module->title)) {
                $badge = isset($options['title_badge'])
                    ? (string)$options['title_badge']
                    : ' <span class="mits-update-badge" style="display:inline-block;margin-left:6px;padding:1px 6px;border-radius:9px;background:#fff0c2;color:#785b00;font-size:11px;font-weight:700;vertical-align:middle">Update ' . self::h((string)$result['latest_version']) . '</span>';
                if ($badge !== '' && strpos((string)$module->title, 'mits-update-badge') === false) {
                    $module->title .= $badge;
                }
            }

            if ($showNotice && isset($module->description)) {
                $notice = self::noticeHtml($result);
                if ($notice !== '' && strpos((string)$module->description, 'mits-update-notice') === false) {
                    $module->description = $notice . (string)$module->description;
                }
            }

            return $result;
        } catch (Throwable $e) {
            $result = self::emptyResult(self::normalizeModuleKey($moduleKey), self::normalizeVersion($installedVersion));
            $result['error'] = 'integration_failed';
            return $result;
        }
    }

    public static function noticeHtml(array $result): string
    {
        $installed = self::h((string)($result['installed_version'] ?? ''));
        $latest = self::h((string)($result['latest_version'] ?? ''));
        $checked = self::formatCheckedAt((int)($result['last_checked_at'] ?? 0));
        $refreshUrl = self::safeLocalUrl((string)($result['refresh_url'] ?? ''));
        $refresh = $refreshUrl !== ''
            ? '<a class="button" style="margin-left:10px;white-space:nowrap" href="' . self::h($refreshUrl) . '">Jetzt auf Updates prüfen</a>'
            : '';
        $meta = $checked !== '' ? '<div style="margin-top:4px;font-size:11px;opacity:.78">Zuletzt geprüft: ' . self::h($checked) . '</div>' : '';

        if (empty($result['ok'])) {
            return '<div class="mits-update-notice mits-update-notice--error" style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;padding:10px 12px;margin:8px 0;border:1px solid #d7dce0;border-left:4px solid #7a858e;background:#f7f8f9;color:#39444c">'
                . '<div><strong>Versionsprüfung derzeit nicht möglich.</strong>' . $meta . '</div>' . $refresh . '</div>';
        }

        $productUrl = self::safeUrl((string)($result['product_url'] ?? ''));
        $productLink = $productUrl !== ''
            ? '<a class="button" style="white-space:nowrap" rel="noopener noreferrer" target="_blank" href="' . self::h($productUrl) . '">Modulseite öffnen</a>'
            : '';

        if (!empty($result['update_available'])) {
            $updateLink = $productUrl !== ''
                ? '<a class="button but_green" style="white-space:nowrap" rel="noopener noreferrer" target="_blank" href="' . self::h($productUrl) . '">Neue Version ansehen / herunterladen</a>'
                : '';
            return '<div class="mits-update-notice mits-update-notice--update" style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;padding:10px 12px;margin:8px 0;border:1px solid #ead9a6;border-left:4px solid #d5a100;background:#fff9e8;color:#5d4800">'
                . '<div><strong>Neue Version ' . $latest . ' verfügbar.</strong>'
                . ($installed !== '' ? '<div style="margin-top:3px">Installiert: ' . $installed . '</div>' : '')
                . $meta . '</div><div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">' . $updateLink . $refresh . '</div></div>';
        }

        if ($latest !== '' && $installed !== '' && version_compare((string)$result['installed_version'], (string)$result['latest_version'], '>')) {
            return '<div class="mits-update-notice mits-update-notice--ahead" style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;padding:10px 12px;margin:8px 0;border:1px solid #b9d8e8;border-left:4px solid #3786ad;background:#eef8fd;color:#1e566f">'
                . '<div><strong>Ihre Version ' . $installed . ' ist neuer als die veröffentlichte Version ' . $latest . '.</strong>' . $meta . '</div><div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">' . $productLink . $refresh . '</div></div>';
        }

        return '<div class="mits-update-notice mits-update-notice--current" style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;padding:10px 12px;margin:8px 0;border:1px solid #b9ddcf;border-left:4px solid #329779;background:#effaf6;color:#165f4e">'
            . '<div><strong>Ihre Version ' . $installed . ' ist aktuell.</strong>' . $meta . '</div><div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">' . $productLink . $refresh . '</div></div>';
    }

    private static function manualRefreshRequested(string $moduleKey, array $options): bool
    {
        if (!isset($_GET['mits_update_check'], $_GET['mits_update_token'])) {
            return false;
        }
        if (!hash_equals($moduleKey, self::normalizeModuleKey((string)$_GET['mits_update_check']))) {
            return false;
        }
        $token = ctype_digit((string)$_GET['mits_update_token']) ? (int)$_GET['mits_update_token'] : -1;
        $endpoint = trim((string)($options['endpoint'] ?? self::configuredEndpoint()));
        if ($token < 0 || !self::isHttpsUrl($endpoint)) {
            return false;
        }
        $cacheFile = self::cacheFile($endpoint, $moduleKey, $options);
        $cacheMtime = is_file($cacheFile) ? (int)@filemtime($cacheFile) : 0;
        return $token === $cacheMtime;
    }

    private static function manualRefreshUrl(string $moduleKey, int $lastCheckedAt): string
    {
        if (!isset($_SERVER['REQUEST_URI']) || !is_string($_SERVER['REQUEST_URI'])) {
            return '';
        }
        $uri = $_SERVER['REQUEST_URI'];
        $parts = parse_url($uri);
        if (!is_array($parts)) {
            return '';
        }
        $path = (string)($parts['path'] ?? '');
        if ($path === '' || strpos($path, '//') === 0) {
            return '';
        }
        $query = array();
        if (!empty($parts['query'])) {
            parse_str((string)$parts['query'], $query);
        }
        $query['mits_update_check'] = $moduleKey;
        $query['mits_update_token'] = max(0, $lastCheckedAt);
        return $path . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    private static function safeLocalUrl(string $url): string
    {
        if ($url === '' || preg_match('/[\r\n]/', $url)) {
            return '';
        }
        $parts = parse_url($url);
        if (!is_array($parts) || isset($parts['scheme']) || isset($parts['host'])) {
            return '';
        }
        return strpos($url, '/') === 0 || strpos($url, '?') === 0 || preg_match('/^[A-Za-z0-9_.-]+\?/', $url) ? $url : '';
    }

    private static function formatCheckedAt(int $timestamp): string
    {
        if ($timestamp <= 0) {
            return '';
        }
        return date('d.m.Y, H:i', $timestamp);
    }

    private static function request(string $url, int $timeout): array
    {
        $timeout = max(2, min(20, $timeout));
        $maxBytes = 262144;
        if (function_exists('curl_init')) {
            $body = '';
            $ch = curl_init($url);
            $curlOptions = array(
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_CONNECTTIMEOUT => min(5, $timeout),
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HTTPHEADER => array(
                    'Accept: application/json',
                    'User-Agent: MITS-Module-Update-Client/' . self::VERSION,
                ),
                CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body, $maxBytes): int {
                    if (strlen($body) + strlen($chunk) > $maxBytes) {
                        return 0;
                    }
                    $body .= $chunk;
                    return strlen($chunk);
                },
            );
            if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTPS')) {
                $curlOptions[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
            }
            if (defined('CURLOPT_REDIR_PROTOCOLS') && defined('CURLPROTO_HTTPS')) {
                $curlOptions[CURLOPT_REDIR_PROTOCOLS] = CURLPROTO_HTTPS;
            }
            curl_setopt_array($ch, $curlOptions);
            $ok = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            if ($ok === false || $status !== 200) {
                return array('ok' => false, 'body' => '', 'error' => 'http_' . $status . ($error !== '' ? ':' . $error : ''));
            }
            return array('ok' => true, 'body' => $body, 'error' => '');
        }

        if (filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
            $context = stream_context_create(array(
                'http' => array(
                    'method' => 'GET',
                    'timeout' => $timeout,
                    'ignore_errors' => true,
                    'header' => "Accept: application/json\r\nUser-Agent: MITS-Module-Update-Client/" . self::VERSION . "\r\n",
                ),
                'ssl' => array(
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                ),
            ));
            $body = @file_get_contents($url, false, $context, 0, $maxBytes + 1);
            if (!is_string($body) || strlen($body) > $maxBytes) {
                return array('ok' => false, 'body' => '', 'error' => 'stream_request_failed');
            }
            $status = 0;
            if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $match)) {
                $status = (int)$match[1];
            }
            if ($status !== 200) {
                return array('ok' => false, 'body' => '', 'error' => 'http_' . $status);
            }
            return array('ok' => true, 'body' => $body, 'error' => '');
        }

        return array('ok' => false, 'body' => '', 'error' => 'no_http_client');
    }

    private static function cacheFile(string $endpoint, string $moduleKey, array $options): string
    {
        $directory = trim((string)($options['cache_dir'] ?? ''));
        if ($directory === '') {
            if (defined('DIR_FS_CATALOG')) {
                $directory = rtrim((string)DIR_FS_CATALOG, '/\\') . '/cache/mits_module_updates';
            } else {
                $directory = rtrim(sys_get_temp_dir(), '/\\') . '/mits_module_updates';
            }
        }
        if (!is_dir($directory)) {
            @mkdir($directory, 0750, true);
        }
        return rtrim($directory, '/\\') . '/' . hash('sha256', $endpoint . '|' . $moduleKey) . '.json';
    }

    private static function writeCache(string $file, string $body): void
    {
        $directory = dirname($file);
        if (!is_dir($directory) || !is_writable($directory)) {
            return;
        }
        $temporary = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($temporary, $body, LOCK_EX) !== false) {
            @chmod($temporary, 0640);
            @rename($temporary, $file);
        }
        if (is_file($temporary)) {
            @unlink($temporary);
        }
    }

    private static function configuredEndpoint(): string
    {
        return defined('MITS_MODULE_UPDATE_ENDPOINT')
            ? trim((string)MITS_MODULE_UPDATE_ENDPOINT)
            : self::DEFAULT_ENDPOINT;
    }

    private static function normalizeModuleKey(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_.-]+/', '_', $value);
        return trim((string)$value, '_.-');
    }

    private static function normalizeVersion(string $value): string
    {
        $value = preg_replace('/^[vV](?=\d)/', '', trim($value));
        return preg_replace('/\s+/', '', (string)$value);
    }

    private static function isHttpsUrl(string $url): bool
    {
        $parts = parse_url($url);
        return is_array($parts)
            && strtolower((string)($parts['scheme'] ?? '')) === 'https'
            && !empty($parts['host']);
    }

    private static function safeUrl(string $url): string
    {
        return self::isHttpsUrl($url) ? $url : '';
    }

    private static function emptyResult(string $moduleKey, string $installedVersion): array
    {
        return array(
            'ok' => false,
            'module' => $moduleKey,
            'installed_version' => $installedVersion,
            'latest_version' => '',
            'update_available' => false,
            'access_mode' => '',
            'released_at' => '',
            'download_url' => '',
            'product_url' => '',
            'account_url' => '',
            'sha256' => '',
            'size' => 0,
            'notes' => '',
            'from_cache' => false,
            'last_checked_at' => 0,
            'refresh_url' => '',
            'error' => '',
        );
    }

    private static function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
