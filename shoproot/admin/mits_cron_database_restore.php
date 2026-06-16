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

require('includes/application_top.php');

@set_time_limit(0);

function mits_cdb_restore_get_charset()
{
    if (isset($_SESSION['language_charset']) && $_SESSION['language_charset'] != '') {
        return $_SESSION['language_charset'];
    }
    if (defined('CHARSET') && CHARSET != '') {
        return CHARSET;
    }
    return 'UTF-8';
}

function mits_cdb_restore_html($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES, mits_cdb_restore_get_charset());
}

function mits_cdb_restore_plain_html($value, $strip_tags = false)
{
    $charset = mits_cdb_restore_get_charset();
    $value = html_entity_decode((string)$value, ENT_QUOTES | ENT_HTML5, $charset);
    if ($strip_tags === true) {
        $value = strip_tags($value);
    }
    return htmlspecialchars($value, ENT_QUOTES, $charset);
}

function mits_cdb_restore_text($constant, $fallback)
{
    return defined($constant) ? constant($constant) : $fallback;
}

function mits_cdb_restore_trailing_slash($path)
{
    return rtrim($path, '/\\') . DIRECTORY_SEPARATOR;
}

function mits_cdb_restore_exec_enabled()
{
    return function_exists('exec')
        && !in_array('exec', array_map('trim', explode(',', (string)ini_get('disable_functions'))))
        && strtolower((string)ini_get('safe_mode')) != '1';
}

function mits_cdb_restore_make_token()
{
    if (function_exists('random_bytes')) {
        return bin2hex(random_bytes(32));
    }
    if (function_exists('xtc_RandomString')) {
        return xtc_RandomString(32);
    }
    return md5(uniqid(mt_rand(), true));
}

function mits_cdb_restore_ensure_token()
{
    if (empty($_SESSION['MITS_CDB_RESTORE_TOKEN'])) {
        $_SESSION['MITS_CDB_RESTORE_TOKEN'] = mits_cdb_restore_make_token();
    }
    return $_SESSION['MITS_CDB_RESTORE_TOKEN'];
}

function mits_cdb_restore_check_token($token)
{
    if (empty($_SESSION['MITS_CDB_RESTORE_TOKEN']) || $token == '') {
        return false;
    }
    if (function_exists('hash_equals')) {
        return hash_equals((string)$_SESSION['MITS_CDB_RESTORE_TOKEN'], (string)$token);
    }
    return ((string)$_SESSION['MITS_CDB_RESTORE_TOKEN'] === (string)$token);
}

function mits_cdb_restore_check_admin_csrf()
{
    if (!isset($_SESSION['CSRFName']) || !isset($_SESSION['CSRFToken'])) {
        return true;
    }

    $csrf_name = (string)$_SESSION['CSRFName'];
    if ($csrf_name == '' || !isset($_POST[$csrf_name])) {
        return false;
    }

    if (function_exists('hash_equals')) {
        return hash_equals((string)$_SESSION['CSRFToken'], (string)$_POST[$csrf_name]);
    }

    return ((string)$_SESSION['CSRFToken'] === (string)$_POST[$csrf_name]);
}

function mits_cdb_restore_rotate_token()
{
    $_SESSION['MITS_CDB_RESTORE_TOKEN'] = mits_cdb_restore_make_token();
}

function mits_cdb_restore_csrf_hidden_fields()
{
    $fields = '';
    if (isset($_SESSION['CSRFName']) && isset($_SESSION['CSRFToken'])) {
        $fields .= xtc_draw_hidden_field($_SESSION['CSRFName'], $_SESSION['CSRFToken']);
    }
    $fields .= xtc_draw_hidden_field('mits_restore_token', mits_cdb_restore_ensure_token());
    return $fields;
}

function mits_cdb_restore_backup_dirs()
{
    $dirs = array();

    $module_dir = mits_cdb_restore_trailing_slash(DIR_FS_DOCUMENT_ROOT . 'export/mits_cron_database_backups');
    if (is_dir($module_dir)) {
        $dirs['module'] = array(
            'key' => 'module',
            'path' => $module_dir,
            'label' => TEXT_MITS_CDB_RESTORE_DIR_MODULE,
        );
    }

    if (defined('DIR_FS_BACKUP')) {
        $admin_dir = mits_cdb_restore_trailing_slash(DIR_FS_BACKUP);
    } else {
        $admin_dir = mits_cdb_restore_trailing_slash(DIR_FS_ADMIN . 'backups');
    }
    if (is_dir($admin_dir) && realpath($admin_dir) !== realpath($module_dir)) {
        $dirs['admin'] = array(
            'key' => 'admin',
            'path' => $admin_dir,
            'label' => TEXT_MITS_CDB_RESTORE_DIR_ADMIN,
        );
    }

    return $dirs;
}

function mits_cdb_restore_file_allowed($filename)
{
    return (bool)preg_match('/^[A-Za-z0-9_.-]+\.(sql|sql\.gz)$/i', $filename);
}

function mits_cdb_restore_collect_backups($dirs)
{
    $backups = array();
    foreach ($dirs as $dir_key => $dir) {
        foreach (array('*.sql', '*.sql.gz') as $pattern) {
            foreach (glob($dir['path'] . $pattern) as $file) {
                if (!is_file($file)) {
                    continue;
                }
                $filename = basename($file);
                if (!mits_cdb_restore_file_allowed($filename) || strpos($filename, '.restore_tmp_') !== false) {
                    continue;
                }
                $backups[] = array(
                    'id' => $dir_key . '|' . $filename,
                    'dir_key' => $dir_key,
                    'filename' => $filename,
                    'path' => $file,
                    'directory' => $dir['label'],
                    'size' => filesize($file),
                    'mtime' => filemtime($file),
                    'compressed' => (substr(strtolower($filename), -3) === '.gz'),
                );
            }
        }
    }

    usort($backups, function ($a, $b) {
        if ($a['mtime'] == $b['mtime']) {
            return strcmp($a['filename'], $b['filename']);
        }
        return ($a['mtime'] < $b['mtime']) ? 1 : -1;
    });

    return $backups;
}

function mits_cdb_restore_resolve_backup($id, $dirs)
{
    $parts = explode('|', (string)$id, 2);
    if (count($parts) != 2) {
        return false;
    }

    $dir_key = $parts[0];
    $filename = basename($parts[1]);
    if (!isset($dirs[$dir_key]) || !mits_cdb_restore_file_allowed($filename)) {
        return false;
    }

    $dir_real = realpath($dirs[$dir_key]['path']);
    $file_real = realpath($dirs[$dir_key]['path'] . $filename);
    if ($dir_real === false || $file_real === false || !is_file($file_real)) {
        return false;
    }

    $dir_real = mits_cdb_restore_trailing_slash($dir_real);
    if (strpos($file_real, $dir_real) !== 0) {
        return false;
    }

    return array(
        'id' => $dir_key . '|' . $filename,
        'dir_key' => $dir_key,
        'filename' => $filename,
        'path' => $file_real,
        'directory' => $dirs[$dir_key]['label'],
        'size' => filesize($file_real),
        'mtime' => filemtime($file_real),
        'compressed' => (substr(strtolower($filename), -3) === '.gz'),
    );
}

function mits_cdb_restore_format_filesize($bytes)
{
    $bytes = (float)$bytes;
    $units = array('B', 'KB', 'MB', 'GB');
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return number_format($bytes, ($i == 0 ? 0 : 2), ',', '.') . ' ' . $units[$i];
}

function mits_cdb_restore_mysql_args()
{
    $args = '';
    if (defined('DB_SERVER') && DB_SERVER != '') {
        $args .= ' -h' . escapeshellarg(DB_SERVER);
    }
    if (defined('DB_SERVER_USERNAME') && DB_SERVER_USERNAME != '') {
        $args .= ' -u' . escapeshellarg(DB_SERVER_USERNAME);
    }
    if (defined('DB_SERVER_PASSWORD') && DB_SERVER_PASSWORD != '') {
        $args .= ' -p' . escapeshellarg(DB_SERVER_PASSWORD);
    }
    $args .= ' ' . escapeshellarg(DB_DATABASE);
    return $args;
}

function mits_cdb_restore_create_safety_backup($target_dir, &$messages)
{
    $target_dir = mits_cdb_restore_trailing_slash($target_dir);
    if (!is_dir($target_dir) || !is_writable($target_dir)) {
        $messages[] = TEXT_MITS_CDB_RESTORE_ERROR_SAFETY_DIR;
        return false;
    }

    $safe_database = preg_replace('/[^A-Za-z0-9_.-]/', '_', DB_DATABASE);
    $target_file = $target_dir . $safe_database . '_before_restore_' . date('Y-m-d_H-i-s') . '.sql';

    $complete_insert = '';
    if (defined('MODULE_MITS_CRON_DATABASE_BACKUPS_COMPLETE_INSERT') && MODULE_MITS_CRON_DATABASE_BACKUPS_COMPLETE_INSERT == 'false') {
        $complete_insert = ' --complete-insert=FALSE';
    }

    $extended_insert = '';
    if (defined('MODULE_MITS_CRON_DATABASE_BACKUPS_EXTENDED_INSERT') && MODULE_MITS_CRON_DATABASE_BACKUPS_EXTENDED_INSERT == 'false') {
        $extended_insert = ' --extended-insert=FALSE';
    }

    $password_arg = '';
    if (defined('DB_SERVER_PASSWORD') && DB_SERVER_PASSWORD != '') {
        $password_arg = ' -p' . escapeshellarg(DB_SERVER_PASSWORD);
    }

    $command = 'mysqldump --opt' . $complete_insert . $extended_insert
        . ' -h' . escapeshellarg(DB_SERVER)
        . ' -u' . escapeshellarg(DB_SERVER_USERNAME)
        . $password_arg
        . ' ' . escapeshellarg(DB_DATABASE)
        . ' > ' . escapeshellarg($target_file)
        . ' 2>&1';

    $output = array();
    $return = 1;
    exec($command, $output, $return);

    if ($return !== 0 || !is_file($target_file) || filesize($target_file) < 1) {
        @unlink($target_file);
        $messages[] = TEXT_MITS_CDB_RESTORE_ERROR_SAFETY_BACKUP;
        if (!empty($output)) {
            $messages[] = implode("\n", $output);
        }
        return false;
    }

    $final_file = $target_file;
    if (defined('MODULE_MITS_CRON_DATABASE_BACKUPS_GZIP') && MODULE_MITS_CRON_DATABASE_BACKUPS_GZIP == 'true') {
        $gzip_output = array();
        $gzip_return = 1;
        exec('gzip -f ' . escapeshellarg($target_file) . ' 2>&1', $gzip_output, $gzip_return);

        if ($gzip_return !== 0 || !is_file($target_file . '.gz') || filesize($target_file . '.gz') < 1) {
            @unlink($target_file . '.gz');
            $messages[] = TEXT_MITS_CDB_RESTORE_ERROR_SAFETY_GZIP;
            if (!empty($gzip_output)) {
                $messages[] = implode("\n", $gzip_output);
            }
            return false;
        }

        $final_file = $target_file . '.gz';
    }

    $messages[] = sprintf(TEXT_MITS_CDB_RESTORE_SAFETY_BACKUP_CREATED, basename($final_file));
    return $final_file;
}

function mits_cdb_restore_decompress_gz($source, $target, &$messages)
{
    if (!function_exists('gzopen')) {
        $messages[] = TEXT_MITS_CDB_RESTORE_ERROR_ZLIB;
        return false;
    }

    $in = @gzopen($source, 'rb');
    if (!$in) {
        $messages[] = TEXT_MITS_CDB_RESTORE_ERROR_GZ_OPEN;
        return false;
    }

    $out = @fopen($target, 'wb');
    if (!$out) {
        gzclose($in);
        $messages[] = TEXT_MITS_CDB_RESTORE_ERROR_TEMPFILE;
        return false;
    }

    while (!gzeof($in)) {
        $data = gzread($in, 1024 * 1024);
        if ($data === false) {
            fclose($out);
            gzclose($in);
            @unlink($target);
            $messages[] = TEXT_MITS_CDB_RESTORE_ERROR_GZ_READ;
            return false;
        }
        if ($data !== '') {
            fwrite($out, $data);
        }
    }

    fclose($out);
    gzclose($in);

    if (!is_file($target) || filesize($target) < 1) {
        @unlink($target);
        $messages[] = TEXT_MITS_CDB_RESTORE_ERROR_TEMPFILE;
        return false;
    }

    return true;
}

function mits_cdb_restore_lock_file($dirs)
{
    if (defined('DIR_FS_CATALOG') && is_dir(DIR_FS_CATALOG . 'cache') && is_writable(DIR_FS_CATALOG . 'cache')) {
        return DIR_FS_CATALOG . 'cache/mits_cron_database_restore.lock';
    }
    foreach ($dirs as $dir) {
        if (is_writable($dir['path'])) {
            return $dir['path'] . 'mits_cron_database_restore.lock';
        }
    }
    return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mits_cron_database_restore.lock';
}

function mits_cdb_restore_acquire_lock($lock_file)
{
    if (is_file($lock_file) && filemtime($lock_file) < (time() - 12 * 60 * 60)) {
        @unlink($lock_file);
    }
    $handle = @fopen($lock_file, 'x');
    if (!$handle) {
        return false;
    }
    fwrite($handle, date('c') . PHP_EOL);
    fclose($handle);
    return true;
}

function mits_cdb_restore_write_log($messages)
{
    if (!defined('DIR_FS_LOG') || !is_dir(DIR_FS_LOG) || !is_writable(DIR_FS_LOG)) {
        return;
    }
    $line = '[' . date('Y-m-d H:i:s') . '] ' . implode(' | ', array_map('strip_tags', $messages)) . PHP_EOL;
    @error_log($line, 3, DIR_FS_LOG . 'mits_cron_database_restore_' . date('Y-m') . '.log');
}

function mits_cdb_restore_destroy_admin_session()
{
    if (function_exists('session_name') && session_id() != '') {
        $_SESSION = array();

        if ((bool)ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            $cookie_params = array(
                'expires' => time() - 42000,
                'path' => isset($params['path']) ? $params['path'] : '/',
                'domain' => isset($params['domain']) ? $params['domain'] : '',
                'secure' => isset($params['secure']) ? (bool)$params['secure'] : false,
                'httponly' => isset($params['httponly']) ? (bool)$params['httponly'] : true,
            );

            if (defined('PHP_VERSION_ID') && PHP_VERSION_ID >= 70300) {
                if (isset($params['samesite'])) {
                    $cookie_params['samesite'] = $params['samesite'];
                }
                @setcookie(session_name(), '', $cookie_params);
            } else {
                @setcookie(session_name(), '', $cookie_params['expires'], $cookie_params['path'], $cookie_params['domain'], $cookie_params['secure'], $cookie_params['httponly']);
            }
        }

        @session_destroy();
    }
}

function mits_cdb_restore_render_relogin_page($messages)
{
    $charset = mits_cdb_restore_get_charset();
    $title = mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_RELOGIN_TITLE', 'Restore completed - login required');
    $text = mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_RELOGIN_TEXT', 'The restore was completed. For security reasons, the current admin session has been closed. Please log in again.');
    $button = mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_RELOGIN_BUTTON', 'Go to admin login');
    $note = mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_RELOGIN_NOTE', 'This is intentional because session, admin user and permission data may have been restored to the state of the backup.');
    $login_url = xtc_catalog_href_link('login_admin.php');
    $message_text = implode("\n", $messages);

    mits_cdb_restore_destroy_admin_session();

    if (!headers_sent()) {
        header('Content-Type: text/html; charset=' . $charset);
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }

    echo '<!doctype html>' . "\n";
    echo '<html><head><meta charset="' . mits_cdb_restore_plain_html($charset, true) . '">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . mits_cdb_restore_plain_html($title, true) . '</title>';
    echo '<style>'
        . 'body{font-family:Arial,Helvetica,sans-serif;background:#f4f8f6;margin:0;padding:32px;color:#444}'
        . '.mits-relogin{--mits-ci-primary:#6a9;--mits-ci-primary-dark:#4f8e7e;--mits-ci-primary-soft:#edf7f4;--mits-ci-line:#d4e7e0;--mits-ci-heading:#30534b;--mits-ci-muted:#6d7b77;--mits-ci-shadow:rgba(76,110,101,.12);max-width:960px;margin:42px auto}'
        . '.mits-relogin__card{background:#fff;border:1px solid var(--mits-ci-line);border-radius:22px;box-shadow:0 16px 36px var(--mits-ci-shadow);overflow:hidden}'
        . '.mits-relogin__hero{padding:28px 32px;background:linear-gradient(135deg,#fff 0%,var(--mits-ci-primary-soft) 100%);border-bottom:1px solid var(--mits-ci-line)}'
        . '.mits-relogin__badge{display:inline-flex;align-items:center;padding:7px 12px;border-radius:999px;background:#e9f7f1;color:#2c715d;font-weight:700;font-size:12px;margin-bottom:14px}'
        . '.mits-relogin h1{margin:0 0 8px;font-size:28px;line-height:1.2;color:var(--mits-ci-heading)}'
        . '.mits-relogin p{margin:0;color:var(--mits-ci-muted);line-height:1.55}'
        . '.mits-relogin__body{padding:24px 32px}'
        . '.mits-relogin__note{margin:16px 0;padding:14px 16px;border:1px solid #d4e7e0;border-radius:14px;background:#f7fbfa;color:#536661}'
        . '.mits-relogin__log{white-space:pre-wrap;margin:16px 0;padding:14px 16px;border:1px solid var(--mits-ci-line);border-radius:14px;background:#fcfefd;max-height:300px;overflow:auto;color:#444}'
        . '.mits-relogin__button{display:inline-flex;align-items:center;justify-content:center;min-height:42px;padding:0 18px;border-radius:12px;border:1px solid var(--mits-ci-primary-dark);background:var(--mits-ci-primary-dark);color:#fff;text-decoration:none;font-weight:700}'
        . '.mits-relogin__button:hover{background:#467d70;border-color:#467d70}'
        . '</style>';
    echo '</head><body><div class="mits-relogin"><div class="mits-relogin__card">';
    echo '<div class="mits-relogin__hero"><span class="mits-relogin__badge">' . mits_cdb_restore_plain_html(mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_RESULT_SUCCESS', 'Restore completed'), true) . '</span><h1>' . mits_cdb_restore_plain_html($title, true) . '</h1><p>' . mits_cdb_restore_plain_html($text) . '</p></div>';
    echo '<div class="mits-relogin__body"><div class="mits-relogin__note">' . mits_cdb_restore_plain_html($note) . '</div>';
    if ($message_text != '') {
        echo '<div class="mits-relogin__log">' . mits_cdb_restore_plain_html($message_text) . '</div>';
    }
    echo '<a class="mits-relogin__button" href="' . mits_cdb_restore_plain_html($login_url, true) . '">' . mits_cdb_restore_plain_html($button, true) . '</a>';
    echo '</div></div></div></body></html>';
    exit;
}

function mits_cdb_restore_run($backup, $dirs, &$messages)
{
    if (!mits_cdb_restore_exec_enabled()) {
        $messages[] = TEXT_MITS_CDB_RESTORE_ERROR_EXEC_DISABLED;
        return false;
    }

    $lock_file = mits_cdb_restore_lock_file($dirs);
    if (!mits_cdb_restore_acquire_lock($lock_file)) {
        $messages[] = TEXT_MITS_CDB_RESTORE_ERROR_LOCKED;
        return false;
    }

    $temp_file = '';
    $success = false;

    try {
        $safety_backup = mits_cdb_restore_create_safety_backup($dirs[$backup['dir_key']]['path'], $messages);
        if ($safety_backup !== false) {
            $import_file = $backup['path'];

            if ($backup['compressed']) {
                $temp_file = $dirs[$backup['dir_key']]['path'] . basename($backup['filename'], '.gz') . '.restore_tmp_' . date('YmdHis') . '_' . mt_rand(1000, 9999) . '.sql';
                if (mits_cdb_restore_decompress_gz($backup['path'], $temp_file, $messages)) {
                    $messages[] = TEXT_MITS_CDB_RESTORE_GZ_UNPACKED;
                    $import_file = $temp_file;
                } else {
                    $import_file = '';
                }
            }

            if ($import_file != '') {
                $command = 'mysql --binary-mode=1' . mits_cdb_restore_mysql_args() . ' < ' . escapeshellarg($import_file) . ' 2>&1';
                $output = array();
                $return = 1;
                exec($command, $output, $return);

                if ($return === 0) {
                    $messages[] = sprintf(TEXT_MITS_CDB_RESTORE_SUCCESS, $backup['filename']);
                    $success = true;
                } else {
                    $messages[] = sprintf(TEXT_MITS_CDB_RESTORE_ERROR_IMPORT, $backup['filename']);
                    if (!empty($output)) {
                        $messages[] = implode("\n", $output);
                    }
                }
            }
        }
    } catch (Exception $e) {
        $messages[] = $e->getMessage();
    }

    if ($temp_file != '' && is_file($temp_file)) {
        @unlink($temp_file);
    }
    if (is_file($lock_file)) {
        @unlink($lock_file);
    }

    return $success;
}

$action = (isset($_POST['action']) ? $_POST['action'] : (isset($_GET['action']) ? $_GET['action'] : ''));
$dirs = mits_cdb_restore_backup_dirs();
$backups = mits_cdb_restore_collect_backups($dirs);
$selected_backup = false;
$messages = array();
$restore_done = false;
$restore_success = false;

if ($action == 'confirm' && isset($_GET['backup'])) {
    $selected_backup = mits_cdb_restore_resolve_backup($_GET['backup'], $dirs);
    if ($selected_backup === false) {
        $messageStack->add(TEXT_MITS_CDB_RESTORE_ERROR_INVALID_FILE, 'error');
        $action = '';
    }
}

if ($action == 'restore' && isset($_POST['backup'])) {
    $restore_done = true;
    $selected_backup = mits_cdb_restore_resolve_backup($_POST['backup'], $dirs);

    if (!mits_cdb_restore_check_admin_csrf()) {
        $messages[] = TEXT_MITS_CDB_RESTORE_ERROR_TOKEN;
    } elseif (!mits_cdb_restore_check_token(isset($_POST['mits_restore_token']) ? $_POST['mits_restore_token'] : '')) {
        $messages[] = TEXT_MITS_CDB_RESTORE_ERROR_TOKEN;
    } elseif ($selected_backup === false) {
        $messages[] = TEXT_MITS_CDB_RESTORE_ERROR_INVALID_FILE;
    } elseif (!isset($_POST['confirm_text']) || strtoupper(trim($_POST['confirm_text'])) !== 'RESTORE') {
        $messages[] = TEXT_MITS_CDB_RESTORE_ERROR_CONFIRM;
    } else {
        $restore_success = mits_cdb_restore_run($selected_backup, $dirs, $messages);
    }

    mits_cdb_restore_write_log($messages);
    if ($restore_success) {
        mits_cdb_restore_render_relogin_page($messages);
    }
    mits_cdb_restore_rotate_token();
}

$backup_count = count($backups);
$total_backup_size = 0;
$compressed_count = 0;
$latest_backup_text = '-';
foreach ($backups as $backup_stat) {
    $total_backup_size += (int)$backup_stat['size'];
    if (!empty($backup_stat['compressed'])) {
        $compressed_count++;
    }
}
if (!empty($backups)) {
    $latest_backup_text = date('d.m.Y H:i', $backups[0]['mtime']);
}
$exec_enabled = mits_cdb_restore_exec_enabled();

require(DIR_WS_INCLUDES . 'head.php');
?>
<style>
.mits-admin{--mits-ci-primary:#6a9;--mits-ci-primary-dark:#4f8e7e;--mits-ci-primary-soft:#edf7f4;--mits-ci-primary-soft-2:#f7fbfa;--mits-ci-line:#d4e7e0;--mits-ci-line-strong:#b9d6cb;--mits-ci-ink:#444;--mits-ci-heading:#30534b;--mits-ci-muted:#6d7b77;--mits-ci-shadow:rgba(76,110,101,.10);--mits-ci-danger-bg:#fdeeed;--mits-ci-danger-text:#a3483f;--mits-ci-warning-bg:#fff8e5;--mits-ci-warning-line:#f0d28a;--mits-ci-warning-text:#7a5a00;--mits-ci-success-bg:#e9f7f1;--mits-ci-success-text:#2c715d;padding:18px 18px 28px;color:var(--mits-ci-ink);font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:1.45}
.mits-admin *{box-sizing:border-box}
.mits-admin input,.mits-admin select,.mits-admin textarea,.mits-admin button{font-family:Arial,Helvetica,sans-serif}
.mits-admin__hero{display:flex;justify-content:space-between;gap:18px;align-items:flex-start;padding:24px;border:1px solid var(--mits-ci-line);border-radius:20px;background:linear-gradient(135deg,#ffffff 0%,var(--mits-ci-primary-soft) 100%);box-shadow:0 10px 25px var(--mits-ci-shadow);margin-bottom:18px}
.mits-admin__hero h1{margin:0 0 7px;font-size:26px;line-height:1.2;color:var(--mits-ci-heading)}
.mits-admin__hero p{margin:0;color:var(--mits-ci-muted);max-width:820px;line-height:1.55}
.mits-admin__hero-actions{display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end}
.mits-admin__stats{display:grid;grid-template-columns:repeat(4,minmax(170px,1fr));gap:14px;margin-bottom:18px}
.mits-stat{padding:18px;border-radius:18px;border:1px solid var(--mits-ci-line);background:#fff;box-shadow:0 8px 22px var(--mits-ci-shadow)}
.mits-stat__label{display:block;font-size:12px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:var(--mits-ci-muted);margin-bottom:8px}
.mits-stat__value{display:block;font-size:25px;font-weight:700;color:var(--mits-ci-heading);line-height:1.1;word-break:break-word}
.mits-stat__meta{display:block;margin-top:8px;color:var(--mits-ci-muted);font-size:12px}
.mits-admin__layout{display:grid;grid-template-columns:minmax(0,1fr) 360px;gap:18px;align-items:start}
.mits-admin__stack{display:grid;gap:18px}
.mits-card{background:#fff;border:1px solid var(--mits-ci-line);border-radius:20px;box-shadow:0 10px 24px var(--mits-ci-shadow);overflow:hidden}
.mits-card__header{padding:18px 22px;border-bottom:1px solid var(--mits-ci-line);background:var(--mits-ci-primary-soft-2)}
.mits-card__title{margin:0;font-size:18px;color:var(--mits-ci-heading)}
.mits-card__subtitle{margin:6px 0 0;color:var(--mits-ci-muted);font-size:13px;line-height:1.5}
.mits-card__body{padding:20px 22px}
.mits-alert{padding:15px 16px;border-radius:16px;border:1px solid var(--mits-ci-line);background:#fff;margin-bottom:16px;line-height:1.55}
.mits-alert strong{display:block;margin-bottom:4px;color:var(--mits-ci-heading)}
.mits-alert--warning{border-color:var(--mits-ci-warning-line);background:var(--mits-ci-warning-bg);color:var(--mits-ci-warning-text)}
.mits-alert--danger{border-color:#f1cec9;background:var(--mits-ci-danger-bg);color:var(--mits-ci-danger-text)}
.mits-alert--success{border-color:#c7dfd6;background:var(--mits-ci-success-bg);color:var(--mits-ci-success-text)}
.mits-table-wrap{overflow:auto;border:1px solid var(--mits-ci-line);border-radius:16px;background:#fff}
.mits-table{width:100%;border-collapse:separate;border-spacing:0;min-width:760px}
.mits-table th,.mits-table td{padding:13px 14px;border-bottom:1px solid var(--mits-ci-line);text-align:left;vertical-align:middle}
.mits-table th{background:#f7fbfa;color:var(--mits-ci-muted);font-size:12px;text-transform:uppercase;letter-spacing:.04em}
.mits-table tbody tr:hover td{background:#fcfefd}
.mits-table tbody tr:last-child td{border-bottom:0}
.mits-file{display:flex;align-items:center;gap:10px;min-width:260px}
.mits-file__icon{display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:12px;border:1px solid var(--mits-ci-line);background:var(--mits-ci-primary-soft);color:var(--mits-ci-heading);font-weight:800;font-size:12px;flex:0 0 auto}
.mits-file__name{font-weight:700;color:var(--mits-ci-heading);word-break:break-all}
.mits-file__meta{display:block;margin-top:3px;color:var(--mits-ci-muted);font-size:12px}
.mits-badge{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;font-size:12px;font-weight:700;background:#eef4f2;color:#536661;white-space:nowrap}
.mits-badge--success{background:var(--mits-ci-success-bg);color:var(--mits-ci-success-text)}
.mits-badge--danger{background:var(--mits-ci-danger-bg);color:var(--mits-ci-danger-text)}
.mits-badge--warning{background:var(--mits-ci-warning-bg);color:var(--mits-ci-warning-text)}
.mits-button,.mits-button:link,.mits-button:visited{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:40px;padding:0 16px;border-radius:12px;border:1px solid var(--mits-ci-line-strong);background:#fff;color:var(--mits-ci-heading);font-weight:700;text-decoration:none;cursor:pointer;transition:all .15s ease;font-family:inherit;font-size:13px}
.mits-button:hover,.mits-button:focus{border-color:var(--mits-ci-primary-dark);background:var(--mits-ci-primary-soft);color:var(--mits-ci-heading) !important;text-decoration:none !important}
.mits-button--primary{background:var(--mits-ci-primary-dark);border-color:var(--mits-ci-primary-dark);color:#fff !important}
.mits-button--primary:hover,.mits-button--primary:focus{background:#467d70;border-color:#467d70;color:#fff !important}
.mits-button--soft{background:var(--mits-ci-primary-soft);border-color:var(--mits-ci-line);color:var(--mits-ci-heading) !important}
.mits-button--soft:hover,.mits-button--soft:focus{background:#e5f4ef;border-color:var(--mits-ci-line-strong);color:var(--mits-ci-heading) !important}
.mits-button--danger{background:var(--mits-ci-danger-bg);border-color:#f1cec9;color:var(--mits-ci-danger-text) !important}
.mits-button--danger:hover,.mits-button--danger:focus{background:#f9d9d5;border-color:#e2aaa3;color:var(--mits-ci-danger-text) !important;text-decoration:none !important}
.mits-actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center;justify-content:flex-end}
.mits-subtle{color:var(--mits-ci-muted);font-size:12px;line-height:1.5}
.mits-detail-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-bottom:16px}
.mits-detail{padding:14px;border:1px solid var(--mits-ci-line);border-radius:16px;background:#fcfefd}
.mits-detail__label{display:block;margin-bottom:6px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--mits-ci-muted)}
.mits-detail__value{display:block;font-weight:700;color:var(--mits-ci-heading);word-break:break-all}
.mits-confirm-input{width:260px;max-width:100%;padding:11px 13px;border:1px solid var(--mits-ci-line-strong);border-radius:12px;background:#fff;color:var(--mits-ci-ink);font-weight:700;letter-spacing:.04em}
.mits-confirm-input:focus{border-color:var(--mits-ci-primary-dark);box-shadow:0 0 0 3px rgba(102,170,153,.16);outline:none}
.mits-log{white-space:pre-wrap;padding:14px 16px;border:1px solid var(--mits-ci-line);border-radius:14px;background:#fcfefd;max-height:280px;overflow:auto;text-align:left;color:#444;margin-top:12px}
.mits-steps{display:grid;gap:10px;margin:0;padding:0;list-style:none}
.mits-steps li{display:flex;gap:10px;align-items:flex-start;padding:12px;border:1px solid var(--mits-ci-line);border-radius:14px;background:#fff}
.mits-steps span{display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:999px;background:var(--mits-ci-primary-soft);color:var(--mits-ci-heading);font-weight:800;flex:0 0 auto}
.mits-empty{padding:24px;border:1px dashed var(--mits-ci-line-strong);border-radius:16px;background:#fcfefd;text-align:center;color:var(--mits-ci-muted)}
@media (max-width:1100px){.mits-admin__stats{grid-template-columns:repeat(2,minmax(170px,1fr))}.mits-admin__layout{grid-template-columns:1fr}.mits-admin__hero{display:block}.mits-admin__hero-actions{justify-content:flex-start;margin-top:14px}}
@media (max-width:640px){.mits-admin{padding:12px}.mits-admin__stats,.mits-detail-grid{grid-template-columns:1fr}.mits-card__body,.mits-card__header,.mits-admin__hero{padding:16px}.mits-table{min-width:680px}}
</style>
</head>
<body>
<!-- header //--> 
<?php require(DIR_WS_INCLUDES . 'header.php'); ?>
<!-- header_eof //--> 

<div class="mits-admin">
  <div class="mits-admin__hero">
    <div>
      <h1><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_PAGE_TITLE', 'MITS Cron Database Backups - Datenbankr&uuml;cksicherung'); ?></h1>
      <p><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_HERO_TEXT', TEXT_MITS_CDB_RESTORE_INTRO); ?></p>
    </div>
    <div class="mits-admin__hero-actions">
      <a class="mits-button mits-button--soft" href="<?php echo xtc_href_link((defined('FILENAME_MODULE_EXPORT') ? FILENAME_MODULE_EXPORT : 'modules.php'), 'set=system&module=mits_cron_database_backups'); ?>"><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_MODULE_SETTINGS', 'Moduleinstellungen'); ?></a>
      <a class="mits-button mits-button--soft" href="<?php echo xtc_href_link('mits_cron_database_restore.php'); ?>"><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_REFRESH', 'Liste aktualisieren'); ?></a>
    </div>
  </div>

  <div class="mits-admin__stats">
    <div class="mits-stat">
      <span class="mits-stat__label"><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_STAT_BACKUPS', 'Backups'); ?></span>
      <span class="mits-stat__value"><?php echo (int)$backup_count; ?></span>
      <span class="mits-stat__meta"><?php echo sprintf(mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_STAT_COMPRESSED', '%s komprimiert'), (int)$compressed_count); ?></span>
    </div>
    <div class="mits-stat">
      <span class="mits-stat__label"><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_STAT_TOTAL_SIZE', 'Gesamtgr&ouml;&szlig;e'); ?></span>
      <span class="mits-stat__value"><?php echo mits_cdb_restore_format_filesize($total_backup_size); ?></span>
      <span class="mits-stat__meta"><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_STAT_TOTAL_SIZE_META', 'alle gefundenen Dateien'); ?></span>
    </div>
    <div class="mits-stat">
      <span class="mits-stat__label"><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_STAT_LATEST', 'Letztes Backup'); ?></span>
      <span class="mits-stat__value"><?php echo mits_cdb_restore_html($latest_backup_text); ?></span>
      <span class="mits-stat__meta"><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_STAT_LATEST_META', 'neueste Datei zuerst'); ?></span>
    </div>
    <div class="mits-stat">
      <span class="mits-stat__label"><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_STAT_SYSTEM', 'Systemstatus'); ?></span>
      <span class="mits-stat__value"><span class="mits-badge <?php echo $exec_enabled ? 'mits-badge--success' : 'mits-badge--danger'; ?>"><?php echo $exec_enabled ? mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_EXEC_AVAILABLE', 'mysql bereit') : mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_EXEC_NOT_AVAILABLE', 'exec deaktiviert'); ?></span></span>
      <span class="mits-stat__meta"><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_STAT_SYSTEM_META', 'Import per Server-Client'); ?></span>
    </div>
  </div>

  <?php if ($restore_done) { ?>
    <div class="mits-card">
      <div class="mits-card__header">
        <h2 class="mits-card__title"><?php echo ($restore_success ? TEXT_MITS_CDB_RESTORE_RESULT_SUCCESS : TEXT_MITS_CDB_RESTORE_RESULT_ERROR); ?></h2>
        <p class="mits-card__subtitle"><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_RESULT_SUBTITLE', 'Das Ergebnis der R&uuml;cksicherung wurde protokolliert.'); ?></p>
      </div>
      <div class="mits-card__body">
        <div class="mits-alert <?php echo ($restore_success ? 'mits-alert--success' : 'mits-alert--danger'); ?>">
          <strong><?php echo ($restore_success ? TEXT_MITS_CDB_RESTORE_RESULT_SUCCESS : TEXT_MITS_CDB_RESTORE_RESULT_ERROR); ?></strong>
          <?php if (!empty($messages)) { ?>
            <div class="mits-log"><?php echo mits_cdb_restore_plain_html(implode("\n", $messages)); ?></div>
          <?php } ?>
        </div>
        <a class="mits-button mits-button--primary" href="<?php echo xtc_href_link('mits_cron_database_restore.php'); ?>"><?php echo BUTTON_BACK; ?></a>
      </div>
    </div>
  <?php } elseif ($action == 'confirm' && is_array($selected_backup)) { ?>
    <div class="mits-admin__layout">
      <div class="mits-card">
        <div class="mits-card__header">
          <h2 class="mits-card__title"><?php echo TEXT_MITS_CDB_RESTORE_CONFIRM_TITLE; ?></h2>
          <p class="mits-card__subtitle"><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_CONFIRM_SUBTITLE', 'Bitte pr&uuml;fen Sie die Datei und best&auml;tigen Sie die R&uuml;cksicherung bewusst.'); ?></p>
        </div>
        <div class="mits-card__body">
          <div class="mits-detail-grid">
            <div class="mits-detail">
              <span class="mits-detail__label"><?php echo TEXT_MITS_CDB_RESTORE_FILE; ?></span>
              <span class="mits-detail__value"><?php echo mits_cdb_restore_html($selected_backup['filename']); ?></span>
            </div>
            <div class="mits-detail">
              <span class="mits-detail__label"><?php echo TEXT_MITS_CDB_RESTORE_DIRECTORY; ?></span>
              <span class="mits-detail__value"><?php echo mits_cdb_restore_html($selected_backup['directory']); ?></span>
            </div>
            <div class="mits-detail">
              <span class="mits-detail__label"><?php echo TEXT_MITS_CDB_RESTORE_SIZE; ?></span>
              <span class="mits-detail__value"><?php echo mits_cdb_restore_format_filesize($selected_backup['size']); ?></span>
            </div>
            <div class="mits-detail">
              <span class="mits-detail__label"><?php echo TEXT_MITS_CDB_RESTORE_DATE; ?></span>
              <span class="mits-detail__value"><?php echo date('d.m.Y H:i:s', $selected_backup['mtime']); ?></span>
            </div>
          </div>

          <div class="mits-alert mits-alert--warning">
            <strong><?php echo TEXT_MITS_CDB_RESTORE_WARNING_TITLE; ?></strong>
            <?php echo TEXT_MITS_CDB_RESTORE_CONFIRM_WARNING; ?>
          </div>

          <?php echo xtc_draw_form('mits_cdb_restore', 'mits_cron_database_restore.php', '', 'post'); ?>
            <?php echo mits_cdb_restore_csrf_hidden_fields(); ?>
            <?php echo xtc_draw_hidden_field('action', 'restore'); ?>
            <?php echo xtc_draw_hidden_field('backup', $selected_backup['id']); ?>
            <p class="mits-subtle"><?php echo TEXT_MITS_CDB_RESTORE_CONFIRM_INPUT; ?></p>
            <p><input class="mits-confirm-input" type="text" name="confirm_text" value="" autocomplete="off" /></p>
            <div class="mits-actions" style="justify-content:flex-start;">
              <button type="submit" class="mits-button mits-button--danger"><?php echo BUTTON_RESTORE; ?></button>
              <a class="mits-button" href="<?php echo xtc_href_link('mits_cron_database_restore.php'); ?>"><?php echo BUTTON_CANCEL; ?></a>
            </div>
          </form>
        </div>
      </div>

      <div class="mits-card">
        <div class="mits-card__header">
          <h2 class="mits-card__title"><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_PROCESS_TITLE', 'Ablauf der R&uuml;cksicherung'); ?></h2>
          <p class="mits-card__subtitle"><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_PROCESS_SUBTITLE', 'Diese Sicherheitsstufen werden vor und w&auml;hrend des Imports ausgef&uuml;hrt.'); ?></p>
        </div>
        <div class="mits-card__body">
          <ol class="mits-steps">
            <li><span>1</span><div><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_STEP_BACKUP', 'Aktuelle Datenbank wird als Sicherheitsbackup gesichert.'); ?></div></li>
            <li><span>2</span><div><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_STEP_UNPACK', 'SQL.GZ-Dateien werden bei Bedarf tempor&auml;r entpackt.'); ?></div></li>
            <li><span>3</span><div><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_STEP_IMPORT', 'Der Import erfolgt per mysql-Client des Servers.'); ?></div></li>
            <li><span>4</span><div><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_STEP_RELOGIN', 'Nach erfolgreicher R&uuml;cksicherung wird die Admin-Session beendet.'); ?></div></li>
          </ol>
        </div>
      </div>
    </div>
  <?php } else { ?>
    <div class="mits-admin__layout">
      <div class="mits-card">
        <div class="mits-card__header">
          <h2 class="mits-card__title"><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_CARD_BACKUPS', 'Verf&uuml;gbare Backups'); ?></h2>
          <p class="mits-card__subtitle"><?php echo TEXT_MITS_CDB_RESTORE_INTRO; ?></p>
        </div>
        <div class="mits-card__body">
          <?php if (!$exec_enabled) { ?>
            <div class="mits-alert mits-alert--danger"><strong><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_EXEC_NOT_AVAILABLE', 'exec deaktiviert'); ?></strong><?php echo TEXT_MITS_CDB_RESTORE_ERROR_EXEC_DISABLED; ?></div>
          <?php } ?>

          <?php if (empty($backups)) { ?>
            <div class="mits-empty"><?php echo TEXT_MITS_CDB_RESTORE_NO_BACKUPS; ?></div>
          <?php } else { ?>
            <div class="mits-table-wrap">
              <table class="mits-table">
                <thead>
                  <tr>
                    <th><?php echo TEXT_MITS_CDB_RESTORE_FILE; ?></th>
                    <th><?php echo TEXT_MITS_CDB_RESTORE_DIRECTORY; ?></th>
                    <th><?php echo TEXT_MITS_CDB_RESTORE_SIZE; ?></th>
                    <th><?php echo TEXT_MITS_CDB_RESTORE_DATE; ?></th>
                    <th><?php echo TEXT_MITS_CDB_RESTORE_TYPE; ?></th>
                    <th>&nbsp;</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($backups as $backup) { ?>
                    <tr>
                      <td>
                        <div class="mits-file">
                          <span class="mits-file__icon">SQL</span>
                          <div>
                            <span class="mits-file__name"><?php echo mits_cdb_restore_html($backup['filename']); ?></span>
                            <span class="mits-file__meta"><?php echo mits_cdb_restore_html($backup['id']); ?></span>
                          </div>
                        </div>
                      </td>
                      <td><?php echo mits_cdb_restore_html($backup['directory']); ?></td>
                      <td><?php echo mits_cdb_restore_format_filesize($backup['size']); ?></td>
                      <td><?php echo date('d.m.Y H:i:s', $backup['mtime']); ?></td>
                      <td><span class="mits-badge <?php echo ($backup['compressed'] ? 'mits-badge--success' : ''); ?>"><?php echo ($backup['compressed'] ? 'SQL.GZ' : 'SQL'); ?></span></td>
                      <td>
                        <div class="mits-actions">
                          <a class="mits-button mits-button--danger" href="<?php echo xtc_href_link('mits_cron_database_restore.php', 'action=confirm&backup=' . rawurlencode($backup['id'])); ?>"><?php echo BUTTON_RESTORE; ?></a>
                        </div>
                      </td>
                    </tr>
                  <?php } ?>
                </tbody>
              </table>
            </div>
          <?php } ?>
        </div>
      </div>

      <div class="mits-admin__stack">
        <div class="mits-card">
          <div class="mits-card__header">
            <h2 class="mits-card__title"><?php echo TEXT_MITS_CDB_RESTORE_WARNING_TITLE; ?></h2>
            <p class="mits-card__subtitle"><?php echo mits_cdb_restore_text('TEXT_MITS_CDB_RESTORE_SAFETY_SUBTITLE', 'Bitte vor dem Start genau pr&uuml;fen.'); ?></p>
          </div>
          <div class="mits-card__body">
            <div class="mits-alert mits-alert--warning" style="margin-bottom:0;">
              <?php echo TEXT_MITS_CDB_RESTORE_WARNING; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  <?php } ?>
</div>

<!-- footer //--> 
<?php require(DIR_WS_INCLUDES . 'footer.php'); ?>
<!-- footer_eof //--> 
</body>
</html>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>
