<?php
/**
 * --------------------------------------------------------------
 * MITS CronDatabaseBackups - Database Synchronization
 * Version: 1.8.2
 * --------------------------------------------------------------
 */

require('includes/application_top.php');
@set_time_limit(0);
require_once DIR_FS_DOCUMENT_ROOT . 'includes/mits_cron_database_sync.php';

function mits_cdb_sync_admin_h($value)
{
    $charset = (defined('CHARSET') && CHARSET != '') ? CHARSET : 'UTF-8';
    return htmlspecialchars((string)$value, ENT_QUOTES, $charset);
}

function mits_cdb_sync_admin_token()
{
    if (empty($_SESSION['MITS_CDB_SYNC_TOKEN'])) {
        try {
            $_SESSION['MITS_CDB_SYNC_TOKEN'] = bin2hex(random_bytes(32));
        } catch (Exception $e) {
            $_SESSION['MITS_CDB_SYNC_TOKEN'] = md5(uniqid(mt_rand(), true));
        }
    }
    return $_SESSION['MITS_CDB_SYNC_TOKEN'];
}

function mits_cdb_sync_admin_check_token()
{
    if (empty($_POST['mits_sync_token']) || empty($_SESSION['MITS_CDB_SYNC_TOKEN'])) {
        return false;
    }
    if (function_exists('hash_equals')) {
        return hash_equals((string)$_SESSION['MITS_CDB_SYNC_TOKEN'], (string)$_POST['mits_sync_token']);
    }
    return (string)$_SESSION['MITS_CDB_SYNC_TOKEN'] === (string)$_POST['mits_sync_token'];
}

function mits_cdb_sync_admin_hidden()
{
    $html = '';
    if (isset($_SESSION['CSRFName']) && isset($_SESSION['CSRFToken'])) {
        $html .= xtc_draw_hidden_field($_SESSION['CSRFName'], $_SESSION['CSRFToken']);
    }
    $html .= xtc_draw_hidden_field('mits_sync_token', mits_cdb_sync_admin_token());
    return $html;
}

function mits_cdb_sync_admin_csrf_ok()
{
    if (!mits_cdb_sync_admin_check_token()) {
        return false;
    }
    if (isset($_SESSION['CSRFName']) && isset($_SESSION['CSRFToken'])) {
        $name = (string)$_SESSION['CSRFName'];
        if ($name === '' || !isset($_POST[$name])) {
            return false;
        }
        if (function_exists('hash_equals')) {
            return hash_equals((string)$_SESSION['CSRFToken'], (string)$_POST[$name]);
        }
        return (string)$_SESSION['CSRFToken'] === (string)$_POST[$name];
    }
    return true;
}

function mits_cdb_sync_admin_post($key, $default = '')
{
    return isset($_POST[$key]) ? $_POST[$key] : $default;
}

function mits_cdb_sync_admin_target_from_post()
{
    return array(
        'host' => trim((string)mits_cdb_sync_admin_post('target_host')),
        'port' => trim((string)mits_cdb_sync_admin_post('target_port')),
        'socket' => trim((string)mits_cdb_sync_admin_post('target_socket')),
        'database' => trim((string)mits_cdb_sync_admin_post('target_database')),
        'username' => trim((string)mits_cdb_sync_admin_post('target_username')),
        'password' => (string)mits_cdb_sync_admin_post('target_password'),
        'force_tcp' => !empty($_POST['target_force_tcp']),
        'ssl_mode' => in_array(mits_cdb_sync_admin_post('target_ssl_mode'), array('preferred', 'required', 'verify'), true) ? mits_cdb_sync_admin_post('target_ssl_mode') : 'preferred',
        'ssl_ca' => trim((string)mits_cdb_sync_admin_post('target_ssl_ca')),
    );
}

function mits_cdb_sync_admin_test_target($target)
{
    list($cnf, $error) = mits_cdb_sync_write_option_file($target, true);
    if ($cnf === false) {
        return array(false, $error, false);
    }
    list($identity, $identity_error) = mits_cdb_sync_target_identity($cnf, $target['database'], in_array(isset($target['ssl_mode']) ? $target['ssl_mode'] : 'preferred', array('required', 'verify'), true));
    @unlink($cnf);
    if ($identity === false) {
        return array(false, $identity_error, false);
    }
    if (mits_cdb_sync_is_same_database(mits_cdb_sync_source_identity(), $identity)) {
        return array(false, TEXT_MITS_CDB_SYNC_TEST_SAME_DB, true);
    }
    return array(true, sprintf(TEXT_MITS_CDB_SYNC_TEST_SUCCESS, $identity['host'], $identity['port'], $identity['db']), false);
}

function mits_cdb_sync_admin_format_time($timestamp)
{
    return ((int)$timestamp > 0) ? date('d.m.Y H:i:s', (int)$timestamp) : TEXT_MITS_CDB_SYNC_NEVER;
}

$message = '';
$message_type = '';
$action = isset($_POST['action']) ? (string)$_POST['action'] : '';
$table = mits_cdb_sync_profile_table();
$table_available = mits_cdb_sync_table_exists($table);

if ($action !== '') {
    if (!mits_cdb_sync_admin_csrf_ok()) {
        $message = TEXT_MITS_CDB_SYNC_ERROR_TOKEN;
        $message_type = 'error';
    } elseif (!$table_available && $action !== 'run_once') {
        $message = TEXT_MITS_CDB_SYNC_ERROR_PROFILE;
        $message_type = 'error';
    } elseif ($action === 'save_profile') {
        $profile_id = (int)mits_cdb_sync_admin_post('profile_id', 0);
        $existing = $profile_id > 0 ? mits_cdb_sync_get_profile($profile_id) : false;
        $name = trim((string)mits_cdb_sync_admin_post('name'));
        $target = mits_cdb_sync_admin_target_from_post();
        $mode = mits_cdb_sync_admin_post('sync_mode') === 'tables' ? 'tables' : 'full';
        $selected_tables = isset($_POST['tables']) && is_array($_POST['tables']) ? $_POST['tables'] : array();
        list($valid_tables, $invalid_tables) = mits_cdb_sync_validate_tables($selected_tables);
        $schedule_enabled = !empty($_POST['schedule_enabled']) ? 1 : 0;
        $schedule_regularity = max(1, min(999, (int)mits_cdb_sync_admin_post('schedule_regularity', 1)));
        $schedule_unit = in_array(mits_cdb_sync_admin_post('schedule_unit'), array('m', 'h', 'd', 'w'), true) ? mits_cdb_sync_admin_post('schedule_unit') : 'd';
        $schedule_time = preg_match('/^\d{1,2}:\d{2}$/', (string)mits_cdb_sync_admin_post('schedule_time')) ? (string)mits_cdb_sync_admin_post('schedule_time') : '04:00';
        $password = (string)$target['password'];
        $encrypted = $existing ? $existing['target_password'] : '';

        if ($name === '') {
            $message = TEXT_MITS_CDB_SYNC_ERROR_NAME;
        } elseif (($target['host'] === '' && $target['socket'] === '') || $target['database'] === '' || $target['username'] === '') {
            $message = TEXT_MITS_CDB_SYNC_ERROR_TARGET;
        } elseif ($mode === 'tables' && (empty($valid_tables) || !empty($invalid_tables))) {
            $message = TEXT_MITS_CDB_SYNC_ERROR_TABLES;
        } elseif ($schedule_enabled && empty($_POST['confirm_profile'])) {
            $message = TEXT_MITS_CDB_SYNC_ERROR_CONFIRM;
        } elseif ($password === '' && !$existing) {
            $message = TEXT_MITS_CDB_SYNC_ERROR_PASSWORD;
        } elseif ($password !== '' && !mits_cdb_sync_crypto_available()) {
            $message = TEXT_MITS_CDB_SYNC_ERROR_ENCRYPT;
        } else {
            if ($password !== '') {
                $encrypted = mits_cdb_sync_encrypt_password($password);
            }
            if ($encrypted === false || $encrypted === '') {
                $message = TEXT_MITS_CDB_SYNC_ERROR_ENCRYPT;
            }
        }

        if ($message === '') {
            $values = array(
                'name' => $name,
                'enabled' => !empty($_POST['enabled']) ? 1 : 0,
                'target_host' => $target['host'],
                'target_port' => $target['port'],
                'target_socket' => $target['socket'],
                'target_database' => $target['database'],
                'target_username' => $target['username'],
                'target_password' => $encrypted,
                'target_force_tcp' => $target['force_tcp'] ? 1 : 0,
                'target_ssl_mode' => $target['ssl_mode'],
                'target_ssl_ca' => $target['ssl_ca'],
                'sync_mode' => $mode,
                'tables_json' => json_encode($valid_tables),
                'schedule_enabled' => $schedule_enabled,
                'schedule_regularity' => $schedule_regularity,
                'schedule_unit' => $schedule_unit,
                'schedule_time' => $schedule_time,
                'next_run' => 0,
            );
            $sets = array();
            foreach ($values as $key => $value) {
                $sets[] = '`' . $key . "`='" . mits_cdb_sync_db_escape($value) . "'";
            }
            if ($existing) {
                xtc_db_query('UPDATE `' . $table . '` SET ' . implode(',', $sets) . ', date_updated=NOW() WHERE profile_id=' . (int)$profile_id);
            } else {
                xtc_db_query('INSERT INTO `' . $table . '` SET ' . implode(',', $sets) . ', date_added=NOW(), date_updated=NOW()');
                $profile_id = (int)xtc_db_insert_id();
            }

            if ($schedule_enabled) {
                $task_before = mits_cdb_sync_profile_task_row($profile_id);
                $initial_status = ($task_before === false) ? (!empty($_POST['enabled']) ? 1 : 0) : null;
                $task_result = mits_cdb_sync_ensure_profile_task($profile_id, $schedule_regularity, $schedule_unit, $schedule_time, $initial_status);
                if (!empty($task_result['success']) && !empty($task_result['task'])) {
                    xtc_db_query(
                        'UPDATE `' . $table . '` SET next_run=' . (int)$task_result['task']['time_next'] . ', date_updated=NOW() WHERE profile_id=' . (int)$profile_id
                    );
                    $message = TEXT_MITS_CDB_SYNC_SAVE_SUCCESS;
                    $message_type = 'success';
                } else {
                    xtc_db_query('UPDATE `' . $table . '` SET next_run=0, date_updated=NOW() WHERE profile_id=' . (int)$profile_id);
                    $message = sprintf(TEXT_MITS_CDB_SYNC_TASK_CREATE_ERROR, isset($task_result['message']) ? $task_result['message'] : '-');
                    $message_type = 'error';
                }
            } else {
                mits_cdb_sync_remove_profile_task($profile_id);
                $message = TEXT_MITS_CDB_SYNC_SAVE_SUCCESS;
                $message_type = 'success';
            }
        } else {
            $message_type = 'error';
        }
    } elseif ($action === 'delete_profile') {
        $profile_id = (int)mits_cdb_sync_admin_post('profile_id');
        mits_cdb_sync_remove_profile_task($profile_id);
        xtc_db_query('DELETE FROM `' . $table . '` WHERE profile_id=' . $profile_id);
        $message = TEXT_MITS_CDB_SYNC_DELETE_SUCCESS;
        $message_type = 'success';
    } elseif ($action === 'run_profile') {
        $result = mits_cdb_sync_run_profile((int)mits_cdb_sync_admin_post('profile_id'), true);
        $message = $result['message'];
        $message_type = $result['success'] ? 'success' : 'error';
    } elseif ($action === 'test_profile') {
        $profile = mits_cdb_sync_get_profile((int)mits_cdb_sync_admin_post('profile_id'));
        if (!$profile) {
            $message = TEXT_MITS_CDB_SYNC_ERROR_PROFILE;
            $message_type = 'error';
        } else {
            $target = mits_cdb_sync_profile_to_target($profile);
            if ($target === false) {
                $message = TEXT_MITS_CDB_SYNC_ERROR_ENCRYPT;
                $message_type = 'error';
            } else {
                list($ok, $test_message) = mits_cdb_sync_admin_test_target($target);
                $message = $ok ? $test_message : sprintf(TEXT_MITS_CDB_SYNC_TEST_ERROR, $test_message);
                $message_type = $ok ? 'success' : 'error';
            }
        }
    } elseif ($action === 'test_once') {
        $target = mits_cdb_sync_admin_target_from_post();
        if (($target['host'] === '' && $target['socket'] === '') || $target['database'] === '' || $target['username'] === '') {
            $message = TEXT_MITS_CDB_SYNC_ERROR_TARGET;
            $message_type = 'error';
        } else {
            list($ok, $test_message) = mits_cdb_sync_admin_test_target($target);
            $message = $ok ? $test_message : sprintf(TEXT_MITS_CDB_SYNC_TEST_ERROR, $test_message);
            $message_type = $ok ? 'success' : 'error';
        }
    } elseif ($action === 'run_once') {
        $target = mits_cdb_sync_admin_target_from_post();
        $mode = mits_cdb_sync_admin_post('sync_mode') === 'tables' ? 'tables' : 'full';
        $selected_tables = isset($_POST['tables']) && is_array($_POST['tables']) ? $_POST['tables'] : array();
        if (empty($_POST['confirm_manual'])) {
            $message = TEXT_MITS_CDB_SYNC_ERROR_CONFIRM;
            $message_type = 'error';
        } elseif (($target['host'] === '' && $target['socket'] === '') || $target['database'] === '' || $target['username'] === '') {
            $message = TEXT_MITS_CDB_SYNC_ERROR_TARGET;
            $message_type = 'error';
        } elseif ($mode === 'tables' && empty($selected_tables)) {
            $message = TEXT_MITS_CDB_SYNC_ERROR_TABLES;
            $message_type = 'error';
        } else {
            $result = mits_cdb_sync_execute($target, $mode, $selected_tables, array('profile' => 'one-time-manual'));
            $message = $result['message'];
            $message_type = $result['success'] ? 'success' : 'error';
        }
    }
}

$source_tables = mits_cdb_sync_source_tables();
$profiles = $table_available ? mits_cdb_sync_profiles() : array();
$scheduled_profile_count = 0;
$active_task_count = 0;
foreach ($profiles as $index => $profile_row) {
    if (mits_cdb_sync_sync_profile_schedule_from_task($profile_row)) {
        $scheduled_profile_count++;
        if (!empty($profile_row['_scheduled_task']['status']) && !empty($profile_row['_scheduled_task']['_module_exists'])) {
            $active_task_count++;
        }
    } else {
        $profile_row['_scheduled_task'] = false;
    }
    $profiles[$index] = $profile_row;
}

$edit_profile = false;
if (isset($_GET['edit']) && (int)$_GET['edit'] > 0) {
    $edit_profile = mits_cdb_sync_get_profile((int)$_GET['edit']);
    if ($edit_profile) {
        mits_cdb_sync_sync_profile_schedule_from_task($edit_profile);
    }
}
if (!$edit_profile) {
    $edit_profile = array(
        'profile_id' => 0, 'name' => '', 'enabled' => 1, 'target_host' => '', 'target_port' => '3306', 'target_socket' => '',
        'target_database' => '', 'target_username' => '', 'target_force_tcp' => 1, 'target_ssl_mode' => 'preferred', 'target_ssl_ca' => '',
        'sync_mode' => 'full', 'tables_json' => '[]', 'schedule_enabled' => 0, 'schedule_regularity' => 1, 'schedule_unit' => 'd', 'schedule_time' => '04:00', '_scheduled_task' => false
    );
}
$edit_tables = mits_cdb_sync_parse_tables($edit_profile['tables_json']);

$external_cron_url = '';
if (defined('MODULE_MITS_CRON_DATABASE_BACKUPS_HASH') && function_exists('xtc_catalog_href_link')) {
    $external_cron_url = str_replace('&amp;', '&', xtc_catalog_href_link('callback/mits_cron_database_backups/mits_cron_database_sync.php', 'pw=' . rawurlencode(MODULE_MITS_CRON_DATABASE_BACKUPS_HASH), 'SSL', false, true, false));
}
$profile_count = count($profiles);
$crypto_available = mits_cdb_sync_crypto_available();


require(DIR_WS_INCLUDES . 'head.php');
?>
<style>
.mits-admin{--mits-ci-primary:#6a9;--mits-ci-primary-dark:#4f8e7e;--mits-ci-primary-soft:#edf7f4;--mits-ci-primary-soft-2:#f7fbfa;--mits-ci-line:#d4e7e0;--mits-ci-line-strong:#b9d6cb;--mits-ci-ink:#444;--mits-ci-heading:#30534b;--mits-ci-muted:#6d7b77;--mits-ci-shadow:rgba(76,110,101,.10);--mits-ci-danger-bg:#fdeeed;--mits-ci-danger-text:#a3483f;--mits-ci-warning-bg:#fff8e5;--mits-ci-warning-line:#f0d28a;--mits-ci-warning-text:#7a5a00;--mits-ci-success-bg:#e9f7f1;--mits-ci-success-text:#2c715d;padding:18px 18px 28px;color:var(--mits-ci-ink);font-family:Arial,Helvetica,sans-serif;font-size:13px;line-height:1.45}
.mits-admin *{box-sizing:border-box}.mits-admin input,.mits-admin select,.mits-admin textarea,.mits-admin button{font-family:Arial,Helvetica,sans-serif}
.mits-admin__hero{display:flex;justify-content:space-between;gap:18px;align-items:flex-start;padding:24px;border:1px solid var(--mits-ci-line);border-radius:20px;background:linear-gradient(135deg,#ffffff 0%,var(--mits-ci-primary-soft) 100%);box-shadow:0 10px 25px var(--mits-ci-shadow);margin-bottom:18px}
.mits-admin__hero h1{margin:0 0 7px;font-size:26px;line-height:1.2;color:var(--mits-ci-heading)}.mits-admin__hero p{margin:0;color:var(--mits-ci-muted);max-width:820px;line-height:1.55}.mits-admin__hero-actions{display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end}
.mits-admin__stats{display:grid;grid-template-columns:repeat(4,minmax(170px,1fr));gap:14px;margin-bottom:18px}.mits-stat{padding:18px;border-radius:18px;border:1px solid var(--mits-ci-line);background:#fff;box-shadow:0 8px 22px var(--mits-ci-shadow)}.mits-stat__label{display:block;font-size:12px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:var(--mits-ci-muted);margin-bottom:8px}.mits-stat__value{display:block;font-size:25px;font-weight:700;color:var(--mits-ci-heading);line-height:1.1;word-break:break-word}.mits-stat__meta{display:block;margin-top:8px;color:var(--mits-ci-muted);font-size:12px}
.mits-admin__layout{display:grid;grid-template-columns:minmax(0,1fr) 390px;grid-template-areas:'main side';gap:18px;align-items:start}.mits-admin__stack{display:grid;gap:18px}.mits-admin__main-stack{grid-area:main}.mits-admin__side-stack{grid-area:side;align-self:start}
.mits-card{background:#fff;border:1px solid var(--mits-ci-line);border-radius:20px;box-shadow:0 10px 24px var(--mits-ci-shadow);overflow:hidden}.mits-card__header{padding:18px 22px;border-bottom:1px solid var(--mits-ci-line);background:var(--mits-ci-primary-soft-2)}.mits-card__title{margin:0;font-size:18px;color:var(--mits-ci-heading)}.mits-card__subtitle{margin:6px 0 0;color:var(--mits-ci-muted);font-size:13px;line-height:1.5}.mits-card__body{padding:20px 22px}
.mits-alert{padding:15px 16px;border-radius:16px;border:1px solid var(--mits-ci-line);background:#fff;margin-bottom:16px;line-height:1.55}.mits-alert strong{display:block;margin-bottom:4px;color:var(--mits-ci-heading)}.mits-alert--warning{border-color:var(--mits-ci-warning-line);background:var(--mits-ci-warning-bg);color:var(--mits-ci-warning-text)}.mits-alert--danger{border-color:#f1cec9;background:var(--mits-ci-danger-bg);color:var(--mits-ci-danger-text)}.mits-alert--success{border-color:#c7dfd6;background:var(--mits-ci-success-bg);color:var(--mits-ci-success-text)}
.mits-badge{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;font-size:12px;font-weight:700;background:#eef4f2;color:#536661;white-space:nowrap}.mits-badge--success{background:var(--mits-ci-success-bg);color:var(--mits-ci-success-text)}.mits-badge--danger{background:var(--mits-ci-danger-bg);color:var(--mits-ci-danger-text)}.mits-badge--warning{background:var(--mits-ci-warning-bg);color:var(--mits-ci-warning-text)}
.mits-button,.mits-button:link,.mits-button:visited{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:40px;padding:0 16px;border-radius:12px;border:1px solid var(--mits-ci-line-strong);background:#fff;color:var(--mits-ci-heading);font-weight:700;text-decoration:none;cursor:pointer;transition:all .15s ease;font-family:inherit;font-size:13px}.mits-button:hover,.mits-button:focus{border-color:var(--mits-ci-primary-dark);background:var(--mits-ci-primary-soft);color:var(--mits-ci-heading)!important;text-decoration:none!important}.mits-button.mits-button--primary,.mits-button.mits-button--primary:link,.mits-button.mits-button--primary:visited{background:var(--mits-ci-primary-dark);border-color:var(--mits-ci-primary-dark);color:#fff!important}.mits-button.mits-button--primary:hover,.mits-button.mits-button--primary:focus{background:#467d70;border-color:#467d70;color:#fff!important}.mits-button.mits-button--soft,.mits-button.mits-button--soft:link,.mits-button.mits-button--soft:visited{background:var(--mits-ci-primary-soft);border-color:var(--mits-ci-line);color:var(--mits-ci-heading)!important}.mits-button.mits-button--danger{background:var(--mits-ci-danger-bg);border-color:#f1cec9;color:var(--mits-ci-danger-text)!important}.mits-button.mits-button--danger:hover{background:#f9d9d5;border-color:#e2aaa3;color:var(--mits-ci-danger-text)!important}.mits-actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center}.mits-inline-form{display:inline-flex;margin:0}.mits-subtle{color:var(--mits-ci-muted);font-size:12px;line-height:1.5}
.mits-form-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.mits-field{margin:0 0 14px}.mits-field label{display:block;margin:0 0 6px;font-weight:700;color:var(--mits-ci-heading)}.mits-field input[type=text],.mits-field input[type=password],.mits-field input[type=number],.mits-field input[type=time],.mits-field select{width:100%;min-height:40px;padding:9px 12px;border:1px solid var(--mits-ci-line-strong);border-radius:12px;background:#fff;color:var(--mits-ci-ink)}.mits-field input:focus,.mits-field select:focus{border-color:var(--mits-ci-primary-dark);box-shadow:0 0 0 3px rgba(102,170,153,.16);outline:none}.mits-option{display:flex;align-items:flex-start;gap:9px;margin:10px 0;color:var(--mits-ci-heading);line-height:1.45}.mits-option input{margin-top:2px;flex:0 0 auto}.mits-section-title{margin:22px 0 12px;padding-top:18px;border-top:1px solid var(--mits-ci-line);font-size:16px;color:var(--mits-ci-heading)}
.mits-check-toolbar{display:flex;align-items:center;justify-content:space-between;gap:10px;margin:10px 0 8px;flex-wrap:wrap}.mits-check-list{max-height:320px;overflow:auto;border:1px solid var(--mits-ci-line);border-radius:14px;background:#fff;margin:12px 0;padding:8px;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:2px 8px}.mits-check-row{display:flex;align-items:center;gap:8px;padding:7px 8px;border-radius:10px;color:var(--mits-ci-heading);word-break:break-all}.mits-check-row:hover{background:var(--mits-ci-primary-soft-2)}.mits-check-row input{margin:0;flex:0 0 auto}
.mits-profile{border:1px solid var(--mits-ci-line);border-radius:16px;background:#fcfefd;padding:15px;margin-bottom:12px}.mits-profile:last-child{margin-bottom:0}.mits-profile__head{display:flex;justify-content:space-between;gap:10px;align-items:flex-start}.mits-profile__name{font-weight:700;color:var(--mits-ci-heading);font-size:14px}.mits-profile__badges{display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end}.mits-profile__meta{margin:10px 0;color:var(--mits-ci-muted);font-size:12px;line-height:1.6}.mits-profile__message{margin:9px 0;padding:9px 10px;border-radius:10px;background:#fff;border:1px solid var(--mits-ci-line);font-size:12px;word-break:break-word}.mits-code{font-family:Consolas,Monaco,monospace;word-break:break-all}.mits-status-box{padding:13px 14px;border:1px solid var(--mits-ci-line);border-radius:14px;background:#fcfefd;margin-bottom:10px}.mits-empty{padding:24px;border:1px dashed var(--mits-ci-line-strong);border-radius:16px;background:#fcfefd;text-align:center;color:var(--mits-ci-muted)}
@media (max-width:1100px){.mits-admin__stats{grid-template-columns:repeat(2,minmax(170px,1fr))}.mits-admin__layout{grid-template-columns:1fr;grid-template-areas:'main' 'side'}.mits-admin__hero{display:block}.mits-admin__hero-actions{justify-content:flex-start;margin-top:14px}}
@media (max-width:640px){.mits-admin{padding:12px}.mits-admin__stats,.mits-form-grid{grid-template-columns:1fr}.mits-card__body,.mits-card__header,.mits-admin__hero{padding:16px}.mits-check-list{grid-template-columns:1fr}.mits-profile__head{display:block}.mits-profile__badges{justify-content:flex-start;margin-top:8px}}
</style>
</head>
<body>
<!-- header //-->
<?php require(DIR_WS_INCLUDES . 'header.php'); ?>
<!-- header_eof //-->

<div class="mits-admin">
  <div class="mits-admin__hero">
    <div>
      <h1><?php echo TEXT_MITS_CDB_SYNC_PAGE_TITLE; ?></h1>
      <p><?php echo TEXT_MITS_CDB_SYNC_INTRO; ?></p>
    </div>
    <div class="mits-admin__hero-actions">
      <a class="mits-button mits-button--soft" href="<?php echo xtc_href_link((defined('FILENAME_MODULE_EXPORT') ? FILENAME_MODULE_EXPORT : 'modules.php'), 'set=system&module=mits_cron_database_backups'); ?>"><?php echo TEXT_MITS_CDB_SYNC_MODULE_SETTINGS; ?></a>
      <a class="mits-button mits-button--soft" href="<?php echo xtc_href_link('mits_cron_database_tools.php'); ?>"><?php echo TEXT_MITS_CDB_SYNC_DATABASE_MAINTENANCE; ?></a>
      <a class="mits-button mits-button--soft" href="<?php echo xtc_href_link('mits_cron_database_restore.php'); ?>"><?php echo TEXT_MITS_CDB_SYNC_BACK; ?></a>
      <a class="mits-button mits-button--soft" href="<?php echo xtc_href_link((defined('FILENAME_SCHEDULED_TASKS') ? FILENAME_SCHEDULED_TASKS : 'scheduled_tasks.php')); ?>"><?php echo TEXT_MITS_CDB_SYNC_OPEN_TASKS; ?></a>
      <a class="mits-button mits-button--primary" href="<?php echo xtc_href_link('mits_cron_database_sync.php'); ?>"><?php echo TEXT_MITS_CDB_SYNC_NEW_PROFILE; ?></a>
    </div>
  </div>

  <?php if ($message !== '') { ?>
    <div class="mits-alert <?php echo $message_type === 'success' ? 'mits-alert--success' : 'mits-alert--danger'; ?>">
      <strong><?php echo $message_type === 'success' ? TEXT_MITS_CDB_SYNC_RESULT_SUCCESS : TEXT_MITS_CDB_SYNC_RESULT_ERROR; ?></strong>
      <?php echo mits_cdb_sync_admin_h($message); ?>
    </div>
  <?php } ?>

  <div class="mits-admin__stats">
    <div class="mits-stat"><span class="mits-stat__label"><?php echo TEXT_MITS_CDB_SYNC_STAT_PROFILES; ?></span><span class="mits-stat__value"><?php echo (int)$profile_count; ?></span><span class="mits-stat__meta"><?php echo TEXT_MITS_CDB_SYNC_STAT_PROFILES_META; ?></span></div>
    <div class="mits-stat"><span class="mits-stat__label"><?php echo TEXT_MITS_CDB_SYNC_STAT_TASKS; ?></span><span class="mits-stat__value"><?php echo (int)$scheduled_profile_count; ?></span><span class="mits-stat__meta"><?php echo TEXT_MITS_CDB_SYNC_STAT_TASKS_META; ?></span></div>
    <div class="mits-stat"><span class="mits-stat__label"><?php echo TEXT_MITS_CDB_SYNC_STAT_ACTIVE_TASKS; ?></span><span class="mits-stat__value"><?php echo (int)$active_task_count; ?></span><span class="mits-stat__meta"><?php echo TEXT_MITS_CDB_SYNC_STAT_ACTIVE_TASKS_META; ?></span></div>
    <div class="mits-stat"><span class="mits-stat__label"><?php echo TEXT_MITS_CDB_SYNC_STAT_SECURITY; ?></span><span class="mits-stat__value"><span class="mits-badge <?php echo $crypto_available ? 'mits-badge--success' : 'mits-badge--danger'; ?>"><?php echo $crypto_available ? TEXT_MITS_CDB_SYNC_STAT_SECURITY_OK : TEXT_MITS_CDB_SYNC_STAT_SECURITY_ERROR; ?></span></span><span class="mits-stat__meta"><?php echo TEXT_MITS_CDB_SYNC_STAT_SECURITY_META; ?></span></div>
  </div>

  <div class="mits-admin__layout">
    <div class="mits-admin__stack mits-admin__main-stack">
      <section class="mits-card">
        <div class="mits-card__header">
          <h2 class="mits-card__title"><?php echo $edit_profile['profile_id'] ? TEXT_MITS_CDB_SYNC_EDIT . ': ' . mits_cdb_sync_admin_h($edit_profile['name']) : TEXT_MITS_CDB_SYNC_NEW_PROFILE; ?></h2>
          <p class="mits-card__subtitle"><?php echo TEXT_MITS_CDB_SYNC_PROFILE_FORM_INFO; ?></p>
        </div>
        <div class="mits-card__body">
          <form method="post" action="<?php echo xtc_href_link('mits_cron_database_sync.php'); ?>">
            <?php echo mits_cdb_sync_admin_hidden(); ?>
            <input type="hidden" name="action" value="save_profile">
            <input type="hidden" name="profile_id" value="<?php echo (int)$edit_profile['profile_id']; ?>">

            <div class="mits-field"><label><?php echo TEXT_MITS_CDB_SYNC_PROFILE_NAME; ?></label><input type="text" name="name" value="<?php echo mits_cdb_sync_admin_h($edit_profile['name']); ?>" required></div>
            <label class="mits-option"><input type="checkbox" name="enabled" value="1"<?php echo !empty($edit_profile['enabled']) ? ' checked' : ''; ?>> <span><?php echo TEXT_MITS_CDB_SYNC_ENABLED; ?></span></label>

            <h3 class="mits-section-title"><?php echo TEXT_MITS_CDB_SYNC_TARGET; ?></h3>
            <div class="mits-form-grid"><div class="mits-field"><label><?php echo TEXT_MITS_CDB_SYNC_TARGET_HOST; ?></label><input type="text" name="target_host" value="<?php echo mits_cdb_sync_admin_h($edit_profile['target_host']); ?>"></div><div class="mits-field"><label><?php echo TEXT_MITS_CDB_SYNC_TARGET_PORT; ?></label><input type="text" name="target_port" value="<?php echo mits_cdb_sync_admin_h($edit_profile['target_port']); ?>"></div></div>
            <div class="mits-field"><label><?php echo TEXT_MITS_CDB_SYNC_TARGET_SOCKET; ?></label><input type="text" name="target_socket" value="<?php echo mits_cdb_sync_admin_h($edit_profile['target_socket']); ?>"></div>
            <div class="mits-form-grid"><div class="mits-field"><label><?php echo TEXT_MITS_CDB_SYNC_TARGET_DATABASE; ?></label><input type="text" name="target_database" value="<?php echo mits_cdb_sync_admin_h($edit_profile['target_database']); ?>" required></div><div class="mits-field"><label><?php echo TEXT_MITS_CDB_SYNC_TARGET_USERNAME; ?></label><input type="text" name="target_username" value="<?php echo mits_cdb_sync_admin_h($edit_profile['target_username']); ?>" autocomplete="off" required></div></div>
            <div class="mits-field"><label><?php echo TEXT_MITS_CDB_SYNC_TARGET_PASSWORD; ?></label><input type="password" name="target_password" value="" autocomplete="new-password"><?php if ($edit_profile['profile_id']) { ?><div class="mits-subtle"><?php echo TEXT_MITS_CDB_SYNC_PASSWORD_KEEP; ?></div><?php } ?></div>
            <label class="mits-option"><input type="checkbox" name="target_force_tcp" value="1"<?php echo !empty($edit_profile['target_force_tcp']) ? ' checked' : ''; ?>> <span><?php echo TEXT_MITS_CDB_SYNC_FORCE_TCP; ?></span></label>
            <div class="mits-form-grid"><div class="mits-field"><label><?php echo TEXT_MITS_CDB_SYNC_TLS_MODE; ?></label><select name="target_ssl_mode"><option value="preferred"<?php echo $edit_profile['target_ssl_mode']==='preferred'?' selected':''; ?>><?php echo TEXT_MITS_CDB_SYNC_TLS_PREFERRED; ?></option><option value="required"<?php echo $edit_profile['target_ssl_mode']==='required'?' selected':''; ?>><?php echo TEXT_MITS_CDB_SYNC_TLS_REQUIRED; ?></option><option value="verify"<?php echo $edit_profile['target_ssl_mode']==='verify'?' selected':''; ?>><?php echo TEXT_MITS_CDB_SYNC_TLS_VERIFY; ?></option></select></div><div class="mits-field"><label><?php echo TEXT_MITS_CDB_SYNC_TLS_CA; ?></label><input type="text" name="target_ssl_ca" value="<?php echo mits_cdb_sync_admin_h($edit_profile['target_ssl_ca']); ?>"></div></div>
            <div class="mits-subtle"><?php echo TEXT_MITS_CDB_SYNC_TLS_INFO; ?></div>

            <h3 class="mits-section-title"><?php echo TEXT_MITS_CDB_SYNC_MODE; ?></h3>
            <div class="mits-field"><select name="sync_mode"><option value="full"<?php echo $edit_profile['sync_mode']==='full'?' selected':''; ?>><?php echo TEXT_MITS_CDB_SYNC_MODE_FULL; ?></option><option value="tables"<?php echo $edit_profile['sync_mode']==='tables'?' selected':''; ?>><?php echo TEXT_MITS_CDB_SYNC_MODE_TABLES; ?></option></select></div>
            <div class="mits-subtle"><?php echo TEXT_MITS_CDB_SYNC_FULL_INFO; ?><br><?php echo TEXT_MITS_CDB_SYNC_PROFILE_TABLE_EXCLUDED; ?></div>
            <div class="mits-check-toolbar"><label class="mits-option" style="margin:0"><input type="checkbox" class="mits-select-all"> <span><?php echo TEXT_MITS_CDB_SYNC_SELECT_ALL; ?></span></label></div>
            <div class="mits-check-list"><?php foreach ($source_tables as $source_table) { ?><label class="mits-check-row"><input type="checkbox" name="tables[]" value="<?php echo mits_cdb_sync_admin_h($source_table); ?>"<?php echo in_array($source_table,$edit_tables,true)?' checked':''; ?>> <?php echo mits_cdb_sync_admin_h($source_table); ?></label><?php } ?></div>

            <h3 class="mits-section-title"><?php echo TEXT_MITS_CDB_SYNC_SCHEDULE; ?></h3>
            <label class="mits-option"><input type="checkbox" name="schedule_enabled" value="1"<?php echo !empty($edit_profile['schedule_enabled'])?' checked':''; ?>> <span><?php echo TEXT_MITS_CDB_SYNC_SCHEDULE_ENABLED; ?></span></label>
            <?php if (!empty($edit_profile['_scheduled_task'])) { $edit_task = $edit_profile['_scheduled_task']; ?>
              <div class="mits-status-box">
                <?php if (empty($edit_task['_module_exists'])) { ?>
                  <span class="mits-badge mits-badge--danger"><?php echo TEXT_MITS_CDB_SYNC_TASK_MODULE_MISSING; ?></span>
                  <div class="mits-alert mits-alert--danger" style="margin:10px 0 0"><?php echo TEXT_MITS_CDB_SYNC_TASK_MODULE_MISSING_INFO; ?></div>
                <?php } else { ?>
                  <span class="mits-badge <?php echo !empty($edit_task['status']) ? 'mits-badge--success' : 'mits-badge--warning'; ?>"><?php echo !empty($edit_task['status']) ? TEXT_MITS_CDB_SYNC_TASK_ACTIVE : TEXT_MITS_CDB_SYNC_TASK_INACTIVE; ?></span>
                  <span class="mits-subtle"><?php echo TEXT_MITS_CDB_SYNC_NEXT_RUN . ': ' . mits_cdb_sync_admin_format_time($edit_task['time_next']); ?></span>
                <?php } ?>
                <div class="mits-actions" style="margin-top:10px"><a class="mits-button mits-button--soft" href="<?php echo xtc_href_link((defined('FILENAME_SCHEDULED_TASKS') ? FILENAME_SCHEDULED_TASKS : 'scheduled_tasks.php'), 'tID='.(int)$edit_task['tasks_id'].'&action=edit'); ?>"><?php echo TEXT_MITS_CDB_SYNC_EDIT_TASK; ?></a></div>
              </div>
            <?php } ?>
            <div class="mits-form-grid"><div class="mits-field"><label><?php echo TEXT_MITS_CDB_SYNC_EVERY; ?></label><input type="number" min="1" max="999" name="schedule_regularity" value="<?php echo (int)$edit_profile['schedule_regularity']; ?>"></div><div class="mits-field"><label>&nbsp;</label><select name="schedule_unit"><option value="m"<?php echo $edit_profile['schedule_unit']==='m'?' selected':''; ?>><?php echo TEXT_MITS_CDB_SYNC_UNIT_MINUTES; ?></option><option value="h"<?php echo $edit_profile['schedule_unit']==='h'?' selected':''; ?>><?php echo TEXT_MITS_CDB_SYNC_UNIT_HOURS; ?></option><option value="d"<?php echo $edit_profile['schedule_unit']==='d'?' selected':''; ?>><?php echo TEXT_MITS_CDB_SYNC_UNIT_DAYS; ?></option><option value="w"<?php echo $edit_profile['schedule_unit']==='w'?' selected':''; ?>><?php echo TEXT_MITS_CDB_SYNC_UNIT_WEEKS; ?></option></select></div></div>
            <div class="mits-field"><label><?php echo TEXT_MITS_CDB_SYNC_START_TIME; ?></label><input type="time" name="schedule_time" value="<?php echo mits_cdb_sync_admin_h($edit_profile['schedule_time']); ?>"></div>
            <div class="mits-subtle"><?php echo TEXT_MITS_CDB_SYNC_SCHEDULE_INFO; ?></div>
            <label class="mits-option"><input type="checkbox" name="confirm_profile" value="1"> <span><?php echo TEXT_MITS_CDB_SYNC_CONFIRM_PROFILE; ?></span></label>
            <div class="mits-actions"><button class="mits-button mits-button--primary" type="submit"><?php echo TEXT_MITS_CDB_SYNC_SAVE; ?></button><?php if ($edit_profile['profile_id']) { ?><a class="mits-button" href="<?php echo xtc_href_link('mits_cron_database_sync.php'); ?>"><?php echo TEXT_MITS_CDB_SYNC_CANCEL; ?></a><?php } ?></div>
          </form>
        </div>
      </section>

      <section class="mits-card">
        <div class="mits-card__header"><h2 class="mits-card__title"><?php echo TEXT_MITS_CDB_SYNC_MANUAL_TITLE; ?></h2><p class="mits-card__subtitle"><?php echo TEXT_MITS_CDB_SYNC_MANUAL_INFO; ?></p></div>
        <div class="mits-card__body">
          <form method="post" action="<?php echo xtc_href_link('mits_cron_database_sync.php'); ?>">
            <?php echo mits_cdb_sync_admin_hidden(); ?>
            <div class="mits-form-grid"><div class="mits-field"><label><?php echo TEXT_MITS_CDB_SYNC_TARGET_HOST; ?></label><input type="text" name="target_host"></div><div class="mits-field"><label><?php echo TEXT_MITS_CDB_SYNC_TARGET_PORT; ?></label><input type="text" name="target_port" value="3306"></div></div>
            <div class="mits-field"><label><?php echo TEXT_MITS_CDB_SYNC_TARGET_SOCKET; ?></label><input type="text" name="target_socket"></div>
            <div class="mits-form-grid"><div class="mits-field"><label><?php echo TEXT_MITS_CDB_SYNC_TARGET_DATABASE; ?></label><input type="text" name="target_database" required></div><div class="mits-field"><label><?php echo TEXT_MITS_CDB_SYNC_TARGET_USERNAME; ?></label><input type="text" name="target_username" autocomplete="off" required></div></div>
            <div class="mits-field"><label><?php echo TEXT_MITS_CDB_SYNC_TARGET_PASSWORD; ?></label><input type="password" name="target_password" autocomplete="new-password"></div>
            <label class="mits-option"><input type="checkbox" name="target_force_tcp" value="1" checked> <span><?php echo TEXT_MITS_CDB_SYNC_FORCE_TCP; ?></span></label>
            <div class="mits-form-grid"><div class="mits-field"><label><?php echo TEXT_MITS_CDB_SYNC_TLS_MODE; ?></label><select name="target_ssl_mode"><option value="preferred"><?php echo TEXT_MITS_CDB_SYNC_TLS_PREFERRED; ?></option><option value="required"><?php echo TEXT_MITS_CDB_SYNC_TLS_REQUIRED; ?></option><option value="verify"><?php echo TEXT_MITS_CDB_SYNC_TLS_VERIFY; ?></option></select></div><div class="mits-field"><label><?php echo TEXT_MITS_CDB_SYNC_TLS_CA; ?></label><input type="text" name="target_ssl_ca"></div></div>
            <div class="mits-field"><label><?php echo TEXT_MITS_CDB_SYNC_MODE; ?></label><select name="sync_mode"><option value="full"><?php echo TEXT_MITS_CDB_SYNC_MODE_FULL; ?></option><option value="tables"><?php echo TEXT_MITS_CDB_SYNC_MODE_TABLES; ?></option></select></div>
            <div class="mits-check-toolbar"><label class="mits-option" style="margin:0"><input type="checkbox" class="mits-select-all"> <span><?php echo TEXT_MITS_CDB_SYNC_SELECT_ALL; ?></span></label></div>
            <div class="mits-check-list"><?php foreach ($source_tables as $source_table) { ?><label class="mits-check-row"><input type="checkbox" name="tables[]" value="<?php echo mits_cdb_sync_admin_h($source_table); ?>"> <?php echo mits_cdb_sync_admin_h($source_table); ?></label><?php } ?></div>
            <label class="mits-option"><input type="checkbox" name="confirm_manual" value="1"> <span><?php echo TEXT_MITS_CDB_SYNC_MANUAL_CONFIRM; ?></span></label>
            <div class="mits-actions"><button class="mits-button" type="submit" name="action" value="test_once"><?php echo TEXT_MITS_CDB_SYNC_TEST; ?></button><button class="mits-button mits-button--primary" type="submit" name="action" value="run_once"><?php echo TEXT_MITS_CDB_SYNC_MANUAL_RUN; ?></button></div>
          </form>
        </div>
      </section>
    </div>

    <aside class="mits-admin__stack mits-admin__side-stack">
      <section class="mits-card">
        <div class="mits-card__header"><h2 class="mits-card__title"><?php echo TEXT_MITS_CDB_SYNC_PROFILES; ?></h2><p class="mits-card__subtitle"><?php echo TEXT_MITS_CDB_SYNC_PROFILES_INFO; ?></p></div>
        <div class="mits-card__body">
          <?php if (empty($profiles)) { ?><div class="mits-empty"><?php echo TEXT_MITS_CDB_SYNC_NO_PROFILES; ?></div><?php } ?>
          <?php foreach ($profiles as $profile) { $ptables = mits_cdb_sync_parse_tables($profile['tables_json']); $task = !empty($profile['_scheduled_task']) ? $profile['_scheduled_task'] : false; ?>
            <div class="mits-profile">
              <div class="mits-profile__head"><div class="mits-profile__name"><?php echo mits_cdb_sync_admin_h($profile['name']); ?></div><div class="mits-profile__badges"><span class="mits-badge <?php echo !empty($profile['enabled']) ? 'mits-badge--success' : 'mits-badge--warning'; ?>"><?php echo !empty($profile['enabled']) ? TEXT_MITS_CDB_SYNC_ACTIVE : TEXT_MITS_CDB_SYNC_INACTIVE; ?></span><?php if ($task && empty($task['_module_exists'])) { ?><span class="mits-badge mits-badge--danger"><?php echo TEXT_MITS_CDB_SYNC_TASK_MODULE_MISSING; ?></span><?php } elseif ($task) { ?><span class="mits-badge <?php echo !empty($task['status']) ? 'mits-badge--success' : 'mits-badge--warning'; ?>"><?php echo !empty($task['status']) ? TEXT_MITS_CDB_SYNC_TASK_ACTIVE : TEXT_MITS_CDB_SYNC_TASK_INACTIVE; ?></span><?php } elseif (!empty($profile['schedule_enabled'])) { ?><span class="mits-badge mits-badge--danger"><?php echo TEXT_MITS_CDB_SYNC_TASK_MISSING; ?></span><?php } ?></div></div>
              <div class="mits-profile__meta"><span class="mits-code"><?php echo mits_cdb_sync_admin_h($profile['target_host'] . ($profile['target_port']!==''?':'.$profile['target_port']:'') . ' / ' . $profile['target_database']); ?></span><br><?php echo $profile['sync_mode']==='tables'?sprintf(TEXT_MITS_CDB_SYNC_TABLE_COUNT,count($ptables)):TEXT_MITS_CDB_SYNC_MODE_FULL; ?><br><?php echo TEXT_MITS_CDB_SYNC_NEXT_RUN . ': ' . ($task ? mits_cdb_sync_admin_format_time($task['time_next']) : TEXT_MITS_CDB_SYNC_SCHEDULE_OFF); ?><br><?php echo TEXT_MITS_CDB_SYNC_LAST_RUN . ': ' . mits_cdb_sync_admin_format_time($profile['last_run']); ?></div>
              <?php if (!empty($profile['last_message'])) { ?><div class="mits-profile__message"><?php echo mits_cdb_sync_admin_h($profile['last_message']); ?></div><?php } ?>
              <?php if ($task && empty($task['_module_exists'])) { ?><div class="mits-alert mits-alert--danger" style="margin:10px 0"><?php echo TEXT_MITS_CDB_SYNC_TASK_MODULE_MISSING_INFO; ?></div><?php } elseif (!$task && !empty($profile['schedule_enabled'])) { ?><div class="mits-alert mits-alert--warning" style="margin:10px 0"><?php echo TEXT_MITS_CDB_SYNC_TASK_MISSING_INFO; ?></div><?php } ?>
              <div class="mits-actions">
                <a class="mits-button" href="<?php echo xtc_href_link('mits_cron_database_sync.php','edit='.(int)$profile['profile_id']); ?>"><?php echo TEXT_MITS_CDB_SYNC_EDIT; ?></a>
                <?php if ($task) { ?><a class="mits-button mits-button--soft" href="<?php echo xtc_href_link((defined('FILENAME_SCHEDULED_TASKS') ? FILENAME_SCHEDULED_TASKS : 'scheduled_tasks.php'), 'tID='.(int)$task['tasks_id'].'&action=edit'); ?>"><?php echo TEXT_MITS_CDB_SYNC_EDIT_TASK; ?></a><?php } ?>
                <form method="post" class="mits-inline-form" onsubmit="return confirm(<?php echo mits_cdb_sync_admin_h(json_encode(TEXT_MITS_CDB_SYNC_RUN_CONFIRM)); ?>)"><?php echo mits_cdb_sync_admin_hidden(); ?><input type="hidden" name="action" value="run_profile"><input type="hidden" name="profile_id" value="<?php echo (int)$profile['profile_id']; ?>"><button class="mits-button mits-button--primary" type="submit"><?php echo TEXT_MITS_CDB_SYNC_RUN; ?></button></form>
                <form method="post" class="mits-inline-form"><?php echo mits_cdb_sync_admin_hidden(); ?><input type="hidden" name="action" value="test_profile"><input type="hidden" name="profile_id" value="<?php echo (int)$profile['profile_id']; ?>"><button class="mits-button" type="submit"><?php echo TEXT_MITS_CDB_SYNC_TEST; ?></button></form>
                <form method="post" class="mits-inline-form" onsubmit="return confirm(<?php echo mits_cdb_sync_admin_h(json_encode(TEXT_MITS_CDB_SYNC_DELETE_CONFIRM)); ?>)"><?php echo mits_cdb_sync_admin_hidden(); ?><input type="hidden" name="action" value="delete_profile"><input type="hidden" name="profile_id" value="<?php echo (int)$profile['profile_id']; ?>"><button class="mits-button mits-button--danger" type="submit"><?php echo TEXT_MITS_CDB_SYNC_DELETE; ?></button></form>
              </div>
            </div>
          <?php } ?>
        </div>
      </section>

      <section class="mits-card">
        <div class="mits-card__header"><h2 class="mits-card__title"><?php echo TEXT_MITS_CDB_SYNC_TASKS_TITLE; ?></h2><p class="mits-card__subtitle"><?php echo TEXT_MITS_CDB_SYNC_TASKS_INFO; ?></p></div>
        <div class="mits-card__body"><div class="mits-status-box"><?php echo TEXT_MITS_CDB_SYNC_TASKS_EDIT_INFO; ?></div><a class="mits-button mits-button--soft" href="<?php echo xtc_href_link((defined('FILENAME_SCHEDULED_TASKS') ? FILENAME_SCHEDULED_TASKS : 'scheduled_tasks.php')); ?>"><?php echo TEXT_MITS_CDB_SYNC_OPEN_TASKS; ?></a></div>
      </section>

      <section class="mits-card">
        <div class="mits-card__header"><h2 class="mits-card__title"><?php echo TEXT_MITS_CDB_SYNC_EXTERNAL_CRON_TITLE; ?></h2></div>
        <div class="mits-card__body"><p><?php echo TEXT_MITS_CDB_SYNC_EXTERNAL_CRON_INFO; ?></p><?php if ($external_cron_url !== '') { ?><div class="mits-status-box mits-code"><?php echo mits_cdb_sync_admin_h($external_cron_url); ?></div><div class="mits-subtle"><?php echo TEXT_MITS_CDB_SYNC_EXTERNAL_CRON_SECRET; ?></div><?php } else { ?><div class="mits-alert mits-alert--warning"><?php echo TEXT_MITS_CDB_SYNC_EXTERNAL_CRON_UNAVAILABLE; ?></div><?php } ?></div>
      </section>

      <section class="mits-card">
        <div class="mits-card__header"><h2 class="mits-card__title"><?php echo TEXT_MITS_CDB_SYNC_SECURITY_TITLE; ?></h2></div>
        <div class="mits-card__body"><p><?php echo TEXT_MITS_CDB_SYNC_SECURITY_INFO; ?></p><div class="mits-subtle"><?php echo TEXT_MITS_CDB_SYNC_PASSWORD_CHANGE_NOTE; ?></div></div>
      </section>
    </aside>
  </div>
</div>
<script>
document.querySelectorAll('.mits-select-all').forEach(function(toggle){toggle.addEventListener('change',function(){var box=this.closest('form').querySelector('.mits-check-list');if(!box)return;box.querySelectorAll('input[type=checkbox][name="tables[]"]').forEach(function(cb){cb.checked=toggle.checked;});});});
</script>
<!-- footer //-->
<?php require(DIR_WS_INCLUDES . 'footer.php'); ?>
<!-- footer_eof //-->
<br />
</body>
</html>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>
