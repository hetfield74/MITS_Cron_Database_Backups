<?php
/*
 * --------------------------------------------------------------
 * File: MitsModuleUpdateClient.php
 *
 * Author: Hetfield / MerZ IT-SerVice
 * Copyright: (c) 2026 - MerZ IT-SerVice
 * Web: https://www.merz-it-service.de
 * Contact: info@merz-it-service.de
 *
 * Released under the GNU General Public License
 * --------------------------------------------------------------
 */

class MitsModuleUpdateClient
{
    public const VERSION = '0.2.8';
    public const DEFAULT_ENDPOINT = 'https://www.merz-it-service.de/api/mits_module_update.php';

    /**
     * @param string $moduleKey
     * @param string $installedVersion
     * @param array $options
     * @return array
     */
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
        $result['notes'] = function_exists('mb_substr') ? mb_substr($notes, 0, 20000, 'UTF-8') : substr($notes, 0, 20000);
        $moduleCompat = isset($payload['compatibility']) && is_array($payload['compatibility']) ? $payload['compatibility'] : array();
        $latestCompat = isset($latest['compatibility']) && is_array($latest['compatibility']) ? $latest['compatibility'] : array();
        $mode = (string)($latestCompat['mode'] ?? 'undocumented');
        $result['compatibility_mode'] = in_array($mode, array('undocumented','inherit','explicit'), true) ? $mode : 'undocumented';
        $useRelease = $result['compatibility_mode'] !== 'undocumented';
        $result['compatibility_source'] = $useRelease ? 'release' : 'module_general';
        $source = $useRelease ? $latestCompat : $moduleCompat;
        $result['supported_shop_versions'] = self::stringList($source['shop_versions'] ?? array());
        $result['supported_shop_labels'] = self::stringList($source['shop_labels'] ?? array());
        $result['supported_php_versions'] = self::stringList($source['php_versions'] ?? array());
        return $result;
    }

    /**
     * @param mixed $module
     * @param array $options
     * @return array
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
            $result = self::evaluateCompatibility($result, isset($options['shop_version']) ? (string)$options['shop_version'] : '');
            $result['refresh_url'] = self::manualRefreshUrl($moduleKey, (int)($result['last_checked_at'] ?? 0));

            $showTitle = !array_key_exists('show_title_badge', $options) || (bool)$options['show_title_badge'];
            $showNotice = !array_key_exists('show_notice', $options) || (bool)$options['show_notice'];

            if ($showTitle && !empty($result['ok']) && !empty($result['update_available']) && isset($module->title)) {
                $badge = isset($options['title_badge'])
                  ? (string)$options['title_badge']
                  : ' <span class="mits-update-badge" style="display:inline-block;margin-left:6px;padding:1px 6px;border-radius:9px;background:#fff0c2;color:#785b00;font-size:11px;font-weight:700;vertical-align:middle">Update ' . self::hUtf8((string)$result['latest_version']) . '</span>';
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

    /**
     * @param array $result
     * @return string
     */
    public static function noticeHtml(array $result): string
    {

        $installed = self::h((string)($result['installed_version'] ?? ''));
        $latest = self::hUtf8((string)($result['latest_version'] ?? ''));
        $checked = self::formatCheckedAt((int)($result['last_checked_at'] ?? 0));
        $refreshUrl = self::safeLocalUrl((string)($result['refresh_url'] ?? ''));
        $refresh = $refreshUrl !== ''
          ? '<a class="button" style="margin-left:10px;white-space:nowrap" href="' . self::h($refreshUrl) . '">' . self::label('refresh') . '</a>'
          : '';
        $meta = $checked !== ''
          ? '<div style="margin-top:4px;font-size:11px;opacity:.78">' . self::label('last_checked') . ' ' . self::h($checked) . '</div>'
          : '';
        $compatibility = self::compatibilityHtml($result);

        if (empty($result['ok'])) {
            return '<div class="mits-update-notice mits-update-notice--error" style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;padding:10px 12px;margin:8px 0;border:1px solid #d7dce0;border-left:4px solid #7a858e;background:#f7f8f9;color:#39444c">'
              . '<div><strong>' . self::label('error') . '</strong>' . $meta . '</div>' . $refresh . '</div>';
        }

        $productUrl = self::safeUrl((string)($result['product_url'] ?? ''));
        $productLink = $productUrl !== ''
          ? '<a class="button" style="white-space:nowrap" rel="noopener noreferrer" target="_blank" href="' . self::hUtf8($productUrl) . '">' . self::label('product') . '</a>'
          : '';

        if (!empty($result['update_available'])) {
            $incompatible = (($result['php_supported'] ?? null) === false) || (($result['shop_supported'] ?? null) === false);
            $updateLink = $productUrl !== ''
              ? '<a class="button but_green" style="white-space:nowrap" rel="noopener noreferrer" target="_blank" href="' . self::hUtf8($productUrl) . '">' . self::label('update_link') . '</a>'
              : '';
            return '<div class="mits-update-notice mits-update-notice--update" style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;padding:10px 12px;margin:8px 0;border:1px solid #ead9a6;border-left:4px solid #d5a100;background:#fff9e8;color:#5d4800">'
              . '<div><strong>' . self::label('new_version') . ' ' . $latest . ' ' . ($incompatible ? self::label('available_incompatible') : self::label('available')) . '</strong>'
              . ($installed !== '' ? '<div style="margin-top:3px">' . self::label('installed') . ' ' . $installed . '</div>' : '')
              . $meta . $compatibility . '</div><div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">' . $updateLink . $refresh . '</div></div>';
        }

        if ($latest !== '' && $installed !== '' && version_compare((string)$result['installed_version'], (string)$result['latest_version'], '>')) {
            return '<div class="mits-update-notice mits-update-notice--ahead" style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;padding:10px 12px;margin:8px 0;border:1px solid #b9d8e8;border-left:4px solid #3786ad;background:#eef8fd;color:#1e566f">'
              . '<div><strong>' . self::label('your_version') . ' ' . $installed . ' ' . self::label('newer_than_published') . ' ' . $latest . '.</strong>' . $meta . $compatibility . '</div><div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">' . $productLink . $refresh . '</div></div>';
        }

        return '<div class="mits-update-notice mits-update-notice--current" style="display:flex;justify-content:space-between;gap:12px;align-items:center;flex-wrap:wrap;padding:10px 12px;margin:8px 0;border:1px solid #b9ddcf;border-left:4px solid #329779;background:#effaf6;color:#165f4e">'
          . '<div><strong>' . self::label('your_version') . ' ' . $installed . ' ' . self::label('current') . '</strong>' . $meta . $compatibility . '</div><div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">' . $productLink . $refresh . '</div></div>';
    }

    /**
     * @param string $moduleKey
     * @param array $options
     * @return bool
     */
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

    /**
     * @param string $moduleKey
     * @param int $lastCheckedAt
     * @return string
     */
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

    /**
     * @param string $url
     * @return string
     */
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

    /**
     * @param int $timestamp
     * @return string
     */
    private static function formatCheckedAt(int $timestamp): string
    {
        if ($timestamp <= 0) {
            return '';
        }
        return date('d.m.Y, H:i', $timestamp);
    }

    /**
     * @param string $url
     * @param int $timeout
     * @return array
     */
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

    /**
     * @param string $endpoint
     * @param string $moduleKey
     * @param array $options
     * @return string
     */
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

    /**
     * @param string $file
     * @param string $body
     * @return void
     */
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

    /**
     * @return string
     */
    private static function configuredEndpoint(): string
    {
        return defined('MITS_MODULE_UPDATE_ENDPOINT')
          ? trim((string)MITS_MODULE_UPDATE_ENDPOINT)
          : self::DEFAULT_ENDPOINT;
    }

    /**
     * @param string $value
     * @return string
     */
    private static function normalizeModuleKey(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9_.-]+/', '_', $value);
        return trim((string)$value, '_.-');
    }

    /**
     * @param string $value
     * @return string
     */
    private static function normalizeVersion(string $value): string
    {
        $value = preg_replace('/^[vV](?=\d)/', '', trim($value));
        return preg_replace('/\s+/', '', (string)$value);
    }

    /**
     * @param string $url
     * @return bool
     */
    private static function isHttpsUrl(string $url): bool
    {
        $parts = parse_url($url);
        return is_array($parts)
          && strtolower((string)($parts['scheme'] ?? '')) === 'https'
          && !empty($parts['host']);
    }

    /**
     * @param string $url
     * @return string
     */
    private static function safeUrl(string $url): string
    {
        return self::isHttpsUrl($url) ? $url : '';
    }

    /**
     * @param string $moduleKey
     * @param string $installedVersion
     * @return array
     */
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
          'compatibility_mode' => 'undocumented',
          'compatibility_source' => '',
          'supported_shop_versions' => array(),
          'supported_shop_labels' => array(),
          'supported_php_versions' => array(),
          'local_php_version' => '',
          'local_shop_version' => '',
          'php_supported' => null,
          'shop_supported' => null,
          'from_cache' => false,
          'last_checked_at' => 0,
          'refresh_url' => '',
          'error' => '',
        );
    }

    /**
     * @param mixed $value
     * @return array
     */
    private static function stringList($value): array
    {
        if (!is_array($value)) return array();
        $out=array();foreach($value as $item){if(!is_scalar($item))continue;$item=trim((string)$item);if($item!==''&&!in_array($item,$out,true))$out[]=$item;}return $out;
    }

    /**
     * @param array $result
     * @param string $shopVersionOverride
     * @return array
     */
    private static function evaluateCompatibility(array $result, string $shopVersionOverride = ''): array
    {
        $php=self::majorMinor(PHP_VERSION);$shop=$shopVersionOverride!==''?self::extractNumericVersion($shopVersionOverride):self::detectShopVersion();
        $result['local_php_version']=$php;$result['local_shop_version']=$shop;
        $phpSupported=self::matchesSupported($php,$result['supported_php_versions']??array(),true);
        $shopSupported=$shop!==''?self::matchesSupported($shop,$result['supported_shop_versions']??array(),false):null;
        $result['php_supported']=$phpSupported;$result['shop_supported']=$shopSupported;return $result;
    }

    /**
     * @param string $value
     * @return string
     */
    private static function majorMinor(string $value): string
    {
        return preg_match('/(\d+)\.(\d+)/',$value,$m)?$m[1].'.'.$m[2]:'';
    }

    /**
     * @param string $value
     * @return string
     */
    private static function extractNumericVersion(string $value): string
    {
        return preg_match('/(\d+\.\d+(?:\.\d+)?)/',$value,$m)?$m[1]:'';
    }

    /**
     * @return string
     */
    private static function detectShopVersion(): string
    {
        foreach(array('PROJECT_VERSION_NO','MODIFIED_SHOP_VERSION','MODIFIED_VERSION','SHOP_VERSION','PROJECT_VERSION') as $constant){if(defined($constant)){ $v=self::extractNumericVersion((string)constant($constant)); if($v!=='')return $v; }}
        foreach(array('modified_shop_version','shop_version','project_version') as $global){if(isset($GLOBALS[$global])){$v=self::extractNumericVersion((string)$GLOBALS[$global]);if($v!=='')return $v;}}
        return '';
    }

    /**
     * @param string $local
     * @param array $supported
     * @param bool $majorMinor
     * @return ?bool
     */
    private static function matchesSupported(string $local, array $supported, bool $majorMinor): ?bool
    {
        if($local===''||!$supported)return null;
        foreach($supported as $candidate){$candidate=$majorMinor?self::majorMinor((string)$candidate):self::extractNumericVersion((string)$candidate);if($candidate!==''&&version_compare($local,$candidate,'=='))return true;}
        return false;
    }

    /**
     * @param array $result
     * @return string
     */
    private static function compatibilityHtml(array $result): string
    {
        $parts = array();
        $php = (string)($result['local_php_version'] ?? '');
        $phpList = $result['supported_php_versions'] ?? array();
        if ($php !== '' && is_array($phpList) && $phpList) {
            $ok = $result['php_supported'] ?? null;
            $parts[] = '<span><b>PHP ' . self::h($php) . '</b> ' . ($ok === true ? '&#10003;' : '&#10007;')
              . ' <small>(' . self::label('supported') . ': ' . self::hUtf8(implode(', ', $phpList)) . ')</small></span>';
        }

        $shop = (string)($result['local_shop_version'] ?? '');
        $shopLabels = $result['supported_shop_labels'] ?? array();
        $shopList = $result['supported_shop_versions'] ?? array();
        if (is_array($shopList) && $shopList) {
            $display = is_array($shopLabels) && $shopLabels ? $shopLabels : $shopList;
            $supported = ' <small>(' . self::label('supported') . ': ' . self::hUtf8(implode(', ', $display)) . ')</small>';
            if ($shop !== '') {
                $ok = $result['shop_supported'] ?? null;
                $parts[] = '<span><b>modified ' . self::h($shop) . '</b> ' . ($ok === true ? '&#10003;' : '&#10007;') . $supported . '</span>';
            } else {
                $parts[] = '<span><b>' . self::label('modified_version') . ':</b> ' . self::label('not_detected') . $supported . '</span>';
            }
        }
        if (!$parts) {
            return '';
        }
        $source = (string)($result['compatibility_source'] ?? '');
        $note = $source === 'module_general'
          ? '<div style="margin-top:3px;font-size:11px;opacity:.78">' . self::label('compatibility_note') . '</div>'
          : '';
        return '<div style="display:flex;gap:5px 12px;flex-wrap:wrap;margin-top:6px;font-size:12px">' . implode('', $parts) . '</div>' . $note;
    }

    /**
     * @param string $key
     * @return string
     */
    private static function label(string $key): string
    {
        static $labels = array(
          'de' => array(
            'refresh' => 'Jetzt auf Updates pr&uuml;fen',
            'last_checked' => 'Zuletzt gepr&uuml;ft:',
            'error' => 'Versionspr&uuml;fung derzeit nicht m&ouml;glich.',
            'product' => 'Modulseite &ouml;ffnen',
            'update_link' => 'Neue Version ansehen / herunterladen',
            'new_version' => 'Neue Version',
            'available' => 'verf&uuml;gbar.',
            'available_incompatible' => 'verf&uuml;gbar &ndash; Umgebung pr&uuml;fen.',
            'installed' => 'Installiert:',
            'your_version' => 'Ihre Version',
            'newer_than_published' => 'ist neuer als die ver&ouml;ffentlichte Version',
            'current' => 'ist aktuell.',
            'supported' => 'unterst&uuml;tzt',
            'modified_version' => 'modified-Version',
            'not_detected' => 'nicht automatisch erkannt',
            'compatibility_note' => 'Release-Kompatibilit&auml;t nicht separat dokumentiert; Anzeige nach Modulstandard.',
          ),
          'en' => array(
            'refresh' => 'Check for updates now',
            'last_checked' => 'Last checked:',
            'error' => 'Version check currently unavailable.',
            'product' => 'Open module page',
            'update_link' => 'View / download new version',
            'new_version' => 'New version',
            'available' => 'available.',
            'available_incompatible' => 'available &ndash; check environment.',
            'installed' => 'Installed:',
            'your_version' => 'Your version',
            'newer_than_published' => 'is newer than the published version',
            'current' => 'is up to date.',
            'supported' => 'supported',
            'modified_version' => 'modified version',
            'not_detected' => 'not detected automatically',
            'compatibility_note' => 'Release compatibility not documented separately; showing module defaults.',
          ),
        );

        $language = self::isGermanLanguage() ? 'de' : 'en';
        return $labels[$language][$key] ?? '';
    }

    /**
     * @return bool
     */
    private static function isGermanLanguage(): bool
    {
        if (isset($_SESSION) && is_array($_SESSION)) {
            if (!empty($_SESSION['language_code'])) {
                $code = strtolower(trim((string)$_SESSION['language_code']));
                return $code === 'de' || strpos($code, 'de-') === 0 || strpos($code, 'de_') === 0;
            }
            if (!empty($_SESSION['language'])) {
                $name = strtolower(trim((string)$_SESSION['language']));
                return $name === 'german' || $name === 'de' || $name === 'deutsch';
            }
        }
        if (isset($GLOBALS['language']) && is_string($GLOBALS['language'])) {
            return in_array(strtolower(trim($GLOBALS['language'])), array('german', 'de', 'deutsch'), true);
        }
        return false;
    }

    /**
     * @param string $value
     * @return string
     */
    private static function h(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, self::shopCharset(), false);
    }

    /**
     * @return string
     */
    private static function shopCharset(): string
    {
        $charset = '';
        if (isset($_SESSION) && is_array($_SESSION) && !empty($_SESSION['language_charset'])) {
            $charset = (string)$_SESSION['language_charset'];
        } elseif (isset($GLOBALS['language_charset']) && is_string($GLOBALS['language_charset'])) {
            $charset = $GLOBALS['language_charset'];
        } elseif (defined('CHARSET')) {
            $charset = (string)CHARSET;
        }
        $charset = strtolower(trim($charset));

        $encodings = array(
          'utf8' => 'UTF-8', 'utf-8' => 'UTF-8',
          'iso-8859-1' => 'ISO-8859-1', 'latin1' => 'ISO-8859-1',
          'iso-8859-15' => 'ISO-8859-15', 'iso-8859-2' => 'ISO-8859-2',
          'windows-1252' => 'Windows-1252', 'cp1252' => 'Windows-1252',
          'ascii' => 'ASCII', 'us-ascii' => 'ASCII',
        );
        return $encodings[$charset] ?? 'ISO-8859-1';
    }

    /**
     * @param string $value
     * @return string
     */
    private static function hUtf8(string $value): string
    {
        $escaped = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
        return (string)preg_replace_callback('/[^\x00-\x7F]/u', static function (array $match): string {
            $bytes = array_values(unpack('C*', $match[0]));
            $length = count($bytes);
            $codepoint = $bytes[0];
            if ($length === 2) {
                $codepoint = (($bytes[0] & 31) << 6) | ($bytes[1] & 63);
            } elseif ($length === 3) {
                $codepoint = (($bytes[0] & 15) << 12) | (($bytes[1] & 63) << 6) | ($bytes[2] & 63);
            } elseif ($length === 4) {
                $codepoint = (($bytes[0] & 7) << 18) | (($bytes[1] & 63) << 12) | (($bytes[2] & 63) << 6) | ($bytes[3] & 63);
            }
            return '&#' . $codepoint . ';';
        }, $escaped);
    }
}

class MitsModuleUpdateClientFileUpdater
{
    public const VERSION = '1.1.0';
    public const FALLBACK_VERSION = '0.2.8';
    public const API_URL = 'https://www.merz-it-service.de/api/mits_module_update_client.php';
    private const PUBLIC_KEY = <<<'PEM'
-----BEGIN PUBLIC KEY-----
MIIBojANBgkqhkiG9w0BAQEFAAOCAY8AMIIBigKCAYEAuJhWDpm4T7HpPQncG6xg
/eDlqdD1Y/3Bbcnhnzi7f3GZrNbkpkTH6bV49kOQ3h9+UIMzz/cW2fE9JgR9kqBu
lQYS0ccW07pSVk88/CEVEiivsqMlI2/CryofDSs0FFPqIMD331rXk8UYtyU1kLPd
BImRk1r7esTYYYrnlMBThniq5zGZIJzl+aOhmaJD1MqXedtHwtIJFTVT4rBagiQy
6sgDUvBjtlKhmZDYSQZFfiVAMcXghFEwwGmX1GLeitEdlcwuAzdr2Ice6VTEAY02
4OConqU9iiDynhb0fJJlT4kfW3IB3uwl1V/sdhRVQLwnIkrAaf349d2eYKaUsdOa
OFk+4caBidSNtSSVv4XPJ5FxO8Nzx0Ans7cUuY+8Z92qZbtk4RF69B2WHezHmPYo
POE7chLNN3DJI/ctzGT8wUV6qPZMnMp7BAMXl8UPotg5kynyWSCOSi35VVNVgF9x
drLow9WJ4HxvtrE0zjBGxKzdyxP4lgWodMD9H2f7kLBJAgMBAAE=
-----END PUBLIC KEY-----
PEM;

    /**
     * @return void
     */
    public static function bootstrap(): void
    {
        if (defined('MITS_MODULE_UPDATE_CLIENT_AUTOUPDATE')
          && in_array(strtolower((string)MITS_MODULE_UPDATE_CLIENT_AUTOUPDATE), array('', '0', 'false', 'no', 'off'), true)
        ) {
            return;
        }
        if (!function_exists('openssl_verify')) {
            return;
        }
        $directory = self::cacheDirectory();
        if ($directory === '') {
            return;
        }
        try {
            self::refresh($directory);
            self::installActiveInShop($directory);
        } catch (Throwable $exception) {
            return;
        }
    }

    /**
     * @param string $directory
     * @return bool
     */
    private static function installActiveInShop(string $directory): bool
    {
        if (!defined('DIR_FS_CATALOG')) {
            return false;
        }
        $manifest = self::readActiveManifest($directory);
        $source = self::verifiedActiveFile($directory);
        if (!is_array($manifest) || $source === '') {
            return false;
        }
        $target = rtrim((string)DIR_FS_CATALOG, '/\\')
          . '/includes/external/mits_module_update_client/MitsModuleUpdateClient.php';
        $targetDirectory = dirname($target);
        if (is_link($target) || !is_file($target) || !is_writable($targetDirectory)) {
            return false;
        }
        $lock = @fopen($directory . '/.install-lock', 'c');
        if (!is_resource($lock)) {
            return false;
        }
        @chmod($directory . '/.install-lock', 0640);
        if (!@flock($lock, LOCK_EX)) {
            fclose($lock);
            return false;
        }
        $success = false;
        try {
            $current = @file_get_contents($target);
            $matches = array();
            if (is_string($current)
              && preg_match('/class\s+MitsModuleUpdateClient\b.{0,2400}?public\s+const\s+VERSION\s*=\s*[\'"]([0-9]+\.[0-9]+\.[0-9]+)[\'"];/s', $current, $matches) === 1
              && version_compare((string)$manifest['version'], $matches[1], '>')
              && is_file($source)
              && hash_equals((string)$manifest['sha256'], (string)@hash_file('sha256', $source))
            ) {
                $tmp = @tempnam($targetDirectory, '.mits-client-');
                if (is_string($tmp)) {
                    $payload = @file_get_contents($source);
                    if (is_string($payload)
                      && strlen($payload) === (int)$manifest['bytes']
                      && @file_put_contents($tmp, $payload, LOCK_EX) === strlen($payload)
                    ) {
                        @chmod($tmp, 0644);
                        if (@rename($tmp, $target)) {
                            $success = true;
                            if (function_exists('opcache_invalidate')) {
                                @opcache_invalidate($target, true);
                            }
                            clearstatcache(true, $target);
                        }
                    }
                    if (is_file($tmp)) {
                        @unlink($tmp);
                    }
                }
            }
        } finally {
            @flock($lock, LOCK_UN);
            fclose($lock);
        }
        return $success;
    }

    /**
     * @param array $manifest
     * @return bool
     */
    public static function verifyManifest(array $manifest): bool
    {
        foreach (array('version', 'sha256', 'bytes', 'php_min', 'loader_min', 'signature') as $key) {
            if (!isset($manifest[$key]) || !is_scalar($manifest[$key])) {
                return false;
            }
        }
        $version = (string)$manifest['version'];
        $sha = (string)$manifest['sha256'];
        $size = (int)$manifest['bytes'];
        $php = (string)$manifest['php_min'];
        $loader = (string)$manifest['loader_min'];
        if (!preg_match('/^\d+\.\d+\.\d+$/D', $version)
          || !preg_match('/^[a-f0-9]{64}$/D', $sha)
          || $size < 100 || $size > 262144
          || !preg_match('/^\d+\.\d+\.\d+$/D', $php)
          || !preg_match('/^\d+\.\d+\.\d+$/D', $loader)
          || !function_exists('openssl_verify')
        ) {
            return false;
        }
        $signature = base64_decode((string)$manifest['signature'], true);
        if (!is_string($signature) || strlen($signature) < 256) {
            return false;
        }
        $message = self::signedText($version, $sha, $size, $php, $loader);
        return openssl_verify($message, $signature, self::PUBLIC_KEY, OPENSSL_ALGO_SHA256) === 1;
    }

    /**
     * @return string
     */
    private static function cacheDirectory(): string
    {
        if (defined('MITS_MODULE_UPDATE_CLIENT_CACHE_DIR')) {
            $custom = trim((string)MITS_MODULE_UPDATE_CLIENT_CACHE_DIR);
            return $custom !== '' ? rtrim($custom, '/\\') : '';
        }
        if (defined('DIR_FS_CATALOG')) {
            return rtrim((string)DIR_FS_CATALOG, '/\\') . '/cache/mits_module_updates/client';
        }
        return '';
    }

    /**
     * @param string $directory
     * @return void
     */
    private static function refresh(string $directory): void
    {
        if (defined('MITS_MODULE_UPDATE_CLIENT_AUTOUPDATE')
          && in_array(strtolower((string)MITS_MODULE_UPDATE_CLIENT_AUTOUPDATE), array('', '0', 'false', 'no', 'off'), true)
        ) {
            return;
        }
        if (PHP_SAPI === 'cli' && !defined('MITS_MODULE_UPDATE_CLIENT_ALLOW_CLI_NETWORK')) {
            return;
        }
        if (!is_dir($directory) && !@mkdir($directory, 0750, true) && !is_dir($directory)) {
            return;
        }
        $stamp = $directory . '/.last-check';
        if (is_file($stamp) && (int)@filemtime($stamp) > time() - 86400) {
            return;
        }
        if (!is_writable($directory)) {
            return;
        }
        @chmod($directory, 0750);
        self::protectDirectory($directory);
        $lock = @fopen($directory . '/.lock', 'c');
        if (!is_resource($lock)) {
            return;
        }
        @chmod($directory . '/.lock', 0640);
        if (!@flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            return;
        }
        clearstatcache(true, $stamp);
        if (!is_file($stamp) || (int)@filemtime($stamp) <= time() - 86400) {
            @touch($stamp);
            @chmod($stamp, 0640);
            self::tryInstall($directory);
        }
        @flock($lock, LOCK_UN);
        fclose($lock);
    }

    /**
     * @param string $directory
     * @return void
     */
    private static function tryInstall(string $directory): void
    {
        $body = self::request(self::API_URL . '?asset=manifest', 8192);
        if ($body === '') {
            return;
        }
        $manifest = json_decode($body, true);
        if (!is_array($manifest) || !self::verifyManifest($manifest) || !self::isCompatible($manifest)) {
            return;
        }
        $version = (string)$manifest['version'];
        if (version_compare($version, MitsModuleUpdateClient::VERSION, '<=')) {
            return;
        }
        $active = self::readActiveManifest($directory);
        if (is_array($active)
          && self::verifiedActiveFile($directory) !== ''
          && version_compare($version, (string)$active['version'], '<=')
        ) {
            return;
        }
        $source = self::request(self::API_URL . '?asset=payload', 262144);
        if ($source === '' || !self::matchesPayload($source, $manifest)) {
            return;
        }
        $target = self::releaseFile($directory, $version);
        $temp = @tempnam($directory, '.mits-client-');
        if (!is_string($temp)) {
            return;
        }
        if (@file_put_contents($temp, $source, LOCK_EX) !== strlen($source)) {
            @unlink($temp);
            return;
        }
        @chmod($temp, 0640);
        if (!@rename($temp, $target)) {
            @unlink($temp);
            return;
        }
        $pointer = $directory . '/active.json';
        $tmpPointer = @tempnam($directory, '.mits-active-');
        if (!is_string($tmpPointer)) {
            return;
        }
        $json = json_encode($manifest, JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || @file_put_contents($tmpPointer, $json, LOCK_EX) !== strlen($json)) {
            @unlink($tmpPointer);
            return;
        }
        @chmod($tmpPointer, 0640);
        if (!@rename($tmpPointer, $pointer)) {
            @unlink($tmpPointer);
        }
    }

    /**
     * @param string $directory
     * @return string
     */
    private static function verifiedActiveFile(string $directory): string
    {
        $manifest = self::readActiveManifest($directory);
        if (!is_array($manifest)
          || !self::isCompatible($manifest)
          || version_compare((string)$manifest['version'], self::FALLBACK_VERSION, '<')
        ) {
            return '';
        }
        $file = self::releaseFile($directory, (string)$manifest['version']);
        if (is_link($file) || !is_file($file)
          || (int)@filesize($file) !== (int)$manifest['bytes']
          || !hash_equals((string)$manifest['sha256'], (string)@hash_file('sha256', $file))
        ) {
            return '';
        }
        return $file;
    }

    /**
     * @param string $directory
     * @return ?array
     */
    private static function readActiveManifest(string $directory): ?array
    {
        $file = $directory . '/active.json';
        if (!is_file($file) || is_link($file)) {
            return null;
        }
        $raw = @file_get_contents($file, false, null, 0, 8193);
        if (!is_string($raw) || strlen($raw) > 8192) {
            return null;
        }
        $manifest = json_decode($raw, true);
        return is_array($manifest) && self::verifyManifest($manifest) ? $manifest : null;
    }

    /**
     * @param array $manifest
     * @return bool
     */
    private static function isCompatible(array $manifest): bool
    {
        return version_compare(PHP_VERSION, (string)$manifest['php_min'], '>=')
          && version_compare(self::VERSION, (string)$manifest['loader_min'], '>=');
    }

    /**
     * @param string $source
     * @param array $manifest
     * @return bool
     */
    private static function matchesPayload(string $source, array $manifest): bool
    {
        $version = preg_quote((string)$manifest['version'], '/');
        return strlen($source) === (int)$manifest['bytes']
          && hash_equals((string)$manifest['sha256'], hash('sha256', $source))
          && strpos($source, '<?php') === 0
          && preg_match('/class\s+MitsModuleUpdateClient\b/', $source) === 1
          && preg_match('/public\s+const\s+VERSION\s*=\s*[\'"]' . $version . '[\'"]/', $source) === 1;
    }

    /**
     * @param string $directory
     * @param string $version
     * @return string
     */
    private static function releaseFile(string $directory, string $version): string
    {
        return $directory . '/client-' . $version . '.dat';
    }

    /**
     * @param string $version
     * @param string $sha
     * @param int $size
     * @param string $php
     * @param string $loader
     * @return string
     */
    private static function signedText(string $version, string $sha, int $size, string $php, string $loader): string
    {
        return 'MITS-MODULE-UPDATE-CLIENT' . "\n" . $version . "\n" . $sha . "\n" . $size . "\n" . $php . "\n" . $loader . "\n";
    }

    /**
     * @param string $directory
     * @return void
     */
    private static function protectDirectory(string $directory): void
    {
        $path = $directory . '/.htaccess';
        if (!is_file($path)) {
            $rules = "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n";
            if (@file_put_contents($path, $rules, LOCK_EX) !== false) {
                @chmod($path, 0640);
            }
        }
    }

    /**
     * @param string $url
     * @param int $limit
     * @return string
     */
    private static function request(string $url, int $limit): string
    {
        if (strpos($url, self::API_URL . '?asset=') !== 0) {
            return '';
        }
        if (function_exists('curl_init')) {
            $buffer = '';
            $handle = curl_init($url);
            $options = array(
              CURLOPT_RETURNTRANSFER => false,
              CURLOPT_FOLLOWLOCATION => false,
              CURLOPT_CONNECTTIMEOUT => 2,
              CURLOPT_TIMEOUT => 4,
              CURLOPT_SSL_VERIFYPEER => true,
              CURLOPT_SSL_VERIFYHOST => 2,
              CURLOPT_HTTPHEADER => array('Accept: application/octet-stream'),
              CURLOPT_USERAGENT => 'MITS-Module-Update-Loader/' . self::VERSION,
              CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$buffer, $limit): int {
                  if (strlen($buffer) + strlen($chunk) > $limit) {
                      return 0;
                  }
                  $buffer .= $chunk;
                  return strlen($chunk);
              },
            );
            if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTPS')) {
                $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
            }
            curl_setopt_array($handle, $options);
            $ok = curl_exec($handle);
            $status = (int)curl_getinfo($handle, CURLINFO_HTTP_CODE);
            curl_close($handle);
            return $ok !== false && $status === 200 ? $buffer : '';
        }
        if (filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN)) {
            $context = stream_context_create(array(
              'http' => array(
                'method' => 'GET', 'timeout' => 4, 'follow_location' => 0,
                'ignore_errors' => true, 'header' => "Accept: application/octet-stream\r\nUser-Agent: MITS-Module-Update-Loader/" . self::VERSION . "\r\n",
              ),
              'ssl' => array('verify_peer' => true, 'verify_peer_name' => true),
            ));
            $body = @file_get_contents($url, false, $context, 0, $limit + 1);
            $status = isset($http_response_header[0]) && preg_match('/\s200\s/', $http_response_header[0]);
            return is_string($body) && strlen($body) <= $limit && $status ? $body : '';
        }
        return '';
    }
}

MitsModuleUpdateClientFileUpdater::bootstrap();
