<?php
/**
 * --------------------------------------------------------------
 * File: mits_cron_database_tools.php
 * Created by PhpStorm
 * Date: 21.08.2026
 * Time: 05:16
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

function mits_cdb_tools_charset()
{
    if (!empty($_SESSION['language_charset'])) {
        return (string)$_SESSION['language_charset'];
    }
    if (defined('CHARSET') && CHARSET != '') {
        return CHARSET;
    }
    return 'UTF-8';
}

function mits_cdb_tools_html($value)
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, mits_cdb_tools_charset());
}

function mits_cdb_tools_text($key, $fallback = '')
{
    $constant = 'TEXT_MITS_CDB_TOOLS_' . strtoupper((string)$key);
    if (defined($constant)) {
        return (string)constant($constant);
    }
    if (defined($key)) {
        return (string)constant($key);
    }
    return (string)$fallback;
}

function mits_cdb_tools_format($key)
{
    $args = func_get_args();
    array_shift($args);
    return vsprintf(mits_cdb_tools_text($key), $args);
}

function mits_cdb_tools_qi($identifier)
{
    return '`' . str_replace('`', '``', (string)$identifier) . '`';
}

function mits_cdb_tools_query($sql)
{
    $result = xtc_db_query($sql);
    if ($result === false) {
        global $db_link;
        $error = '';
        if (isset($db_link) && $db_link instanceof mysqli) {
            $error = trim((string)$db_link->error);
        }
        throw new RuntimeException(($error != '' ? $error . "\n" : '') . 'SQL: ' . $sql);
    }
    return $result;
}

function mits_cdb_tools_scalar($sql)
{
    $result = mits_cdb_tools_query($sql);
    if (!is_object($result)) {
        return '';
    }
    $row = xtc_db_fetch_array($result);
    if (!$row) {
        return '';
    }
    return (string)reset($row);
}

function mits_cdb_tools_table_exists($table)
{
    $sql = "SELECT COUNT(*) AS cnt
              FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name = '" . xtc_db_input((string)$table) . "'
               AND table_type = 'BASE TABLE'";
    return (int)mits_cdb_tools_scalar($sql) > 0;
}

function mits_cdb_tools_column_exists($table, $column)
{
    $sql = "SELECT COUNT(*) AS cnt
              FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = '" . xtc_db_input((string)$table) . "'
               AND column_name = '" . xtc_db_input((string)$column) . "'";
    return (int)mits_cdb_tools_scalar($sql) > 0;
}

function mits_cdb_tools_table($constant, $fallback = '')
{
    if (defined($constant)) {
        return (string)constant($constant);
    }
    return (string)$fallback;
}

function mits_cdb_tools_post($key, $default = '')
{
    return isset($_POST[$key]) ? $_POST[$key] : $default;
}

function mits_cdb_tools_int($key, $default = 0)
{
    return (int)mits_cdb_tools_post($key, $default);
}

function mits_cdb_tools_parse_ids($value)
{
    $parts = preg_split('/[^0-9]+/', (string)$value, -1, PREG_SPLIT_NO_EMPTY);
    $ids = array();
    foreach ($parts as $part) {
        $id = (int)$part;
        if ($id > 0) {
            $ids[$id] = $id;
        }
    }
    return array_values($ids);
}

function mits_cdb_tools_ids_sql($ids)
{
    if (empty($ids)) {
        return '0';
    }
    return implode(',', array_map('intval', $ids));
}

function mits_cdb_tools_date($value)
{
    $value = trim((string)$value);
    if ($value === '') {
        return '';
    }
    $dt = DateTime::createFromFormat('Y-m-d', $value);
    if (!$dt || $dt->format('Y-m-d') !== $value) {
        throw new RuntimeException(mits_cdb_tools_text('error_date'));
    }
    return $value;
}

function mits_cdb_tools_make_token()
{
    if (function_exists('random_bytes')) {
        return bin2hex(random_bytes(32));
    }
    return md5(uniqid(mt_rand(), true));
}

function mits_cdb_tools_token()
{
    if (empty($_SESSION['MITS_CDB_TOOLS_TOKEN'])) {
        $_SESSION['MITS_CDB_TOOLS_TOKEN'] = mits_cdb_tools_make_token();
    }
    return (string)$_SESSION['MITS_CDB_TOOLS_TOKEN'];
}

function mits_cdb_tools_rotate_token()
{
    $_SESSION['MITS_CDB_TOOLS_TOKEN'] = mits_cdb_tools_make_token();
}

function mits_cdb_tools_check_post_security()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }

    $token = (string)mits_cdb_tools_post('mits_tools_token', '');
    $sessionToken = mits_cdb_tools_token();
    $valid = function_exists('hash_equals') ? hash_equals($sessionToken, $token) : ($sessionToken === $token);
    if (!$valid) {
        throw new RuntimeException(mits_cdb_tools_text('error_token'));
    }

    if (isset($_SESSION['CSRFName']) && isset($_SESSION['CSRFToken'])) {
        $csrfName = (string)$_SESSION['CSRFName'];
        if ($csrfName === '' || !isset($_POST[$csrfName])) {
            throw new RuntimeException(mits_cdb_tools_text('error_token'));
        }
        $posted = (string)$_POST[$csrfName];
        $expected = (string)$_SESSION['CSRFToken'];
        $validCsrf = function_exists('hash_equals') ? hash_equals($expected, $posted) : ($expected === $posted);
        if (!$validCsrf) {
            throw new RuntimeException(mits_cdb_tools_text('error_token'));
        }
    }
}

function mits_cdb_tools_hidden($tool)
{
    $html = xtc_draw_hidden_field('tool', $tool);
    $html .= xtc_draw_hidden_field('mits_tools_token', mits_cdb_tools_token());
    if (isset($_SESSION['CSRFName']) && isset($_SESSION['CSRFToken'])) {
        $html .= xtc_draw_hidden_field($_SESSION['CSRFName'], $_SESSION['CSRFToken']);
    }
    return $html;
}

function mits_cdb_tools_require_live($confirmWord, $backupRequired = true)
{
    if ((string)mits_cdb_tools_post('run_mode', '') !== 'live') {
        return false;
    }
    if ($backupRequired && (string)mits_cdb_tools_post('backup_confirmed', '') !== '1') {
        throw new RuntimeException(mits_cdb_tools_text('error_backup'));
    }
    if (trim((string)mits_cdb_tools_post('confirm_text', '')) !== $confirmWord) {
        throw new RuntimeException(mits_cdb_tools_format('error_confirm', $confirmWord));
    }
    return true;
}

function mits_cdb_tools_result($type, $title, $message, $details = array())
{
    return array(
        'type' => $type,
        'title' => $title,
        'message' => $message,
        'details' => $details,
    );
}

function mits_cdb_tools_fetch_all($sql, $limit = 50)
{
    if ($limit > 0) {
        $sql .= ' LIMIT ' . (int)$limit;
    }
    $result = mits_cdb_tools_query($sql);
    $rows = array();
    while ($row = xtc_db_fetch_array($result)) {
        $rows[] = $row;
    }
    return $rows;
}

function mits_cdb_tools_temp_ids($selectSql, $name = '_mits_cdb_tools_ids')
{
    $name = preg_replace('/[^A-Za-z0-9_]/', '', (string)$name);
    if ($name === '') {
        $name = '_mits_cdb_tools_ids';
    }
    mits_cdb_tools_query('DROP TEMPORARY TABLE IF EXISTS ' . mits_cdb_tools_qi($name));
    mits_cdb_tools_query('CREATE TEMPORARY TABLE ' . mits_cdb_tools_qi($name) . ' (`id` BIGINT UNSIGNED NOT NULL PRIMARY KEY) ENGINE=MEMORY');
    mits_cdb_tools_query('INSERT IGNORE INTO ' . mits_cdb_tools_qi($name) . ' (`id`) ' . $selectSql);
    return $name;
}

function mits_cdb_tools_delete_join($table, $column, $tempTable, $label, &$log)
{
    if ($table === '' || !mits_cdb_tools_table_exists($table) || !mits_cdb_tools_column_exists($table, $column)) {
        return;
    }
    $sql = 'DELETE t FROM ' . mits_cdb_tools_qi($table) . ' t'
         . ' JOIN ' . mits_cdb_tools_qi($tempTable) . ' x ON t.' . mits_cdb_tools_qi($column) . ' = x.id';
    mits_cdb_tools_query($sql);
    $log[] = $label . ': ' . (int)xtc_db_affected_rows();
}

function mits_cdb_tools_reset_ai($table)
{
    if ($table === '' || !mits_cdb_tools_table_exists($table)) {
        return;
    }
    $hasAutoIncrement = (int)mits_cdb_tools_scalar(
        "SELECT COUNT(*) FROM information_schema.columns
          WHERE table_schema = DATABASE()
            AND table_name = '" . xtc_db_input($table) . "'
            AND EXTRA LIKE '%auto_increment%'"
    );
    if ($hasAutoIncrement > 0) {
        mits_cdb_tools_query('ALTER TABLE ' . mits_cdb_tools_qi($table) . ' AUTO_INCREMENT = 1');
    }
}

function mits_cdb_tools_ai_info($table, $idColumn)
{
    $max = 0;
    if (mits_cdb_tools_table_exists($table) && mits_cdb_tools_column_exists($table, $idColumn)) {
        $max = (int)mits_cdb_tools_scalar('SELECT COALESCE(MAX(' . mits_cdb_tools_qi($idColumn) . '), 0) FROM ' . mits_cdb_tools_qi($table));
    }
    $current = (int)mits_cdb_tools_scalar(
        "SELECT COALESCE(AUTO_INCREMENT, 0)
           FROM information_schema.tables
          WHERE table_schema = DATABASE()
            AND table_name = '" . xtc_db_input($table) . "'"
    );
    return array('max' => $max, 'current' => $current, 'minimum' => $max + 1);
}

function mits_cdb_tools_admin_ids()
{
    $customers = mits_cdb_tools_table('TABLE_CUSTOMERS', 'customers');
    $result = mits_cdb_tools_query('SELECT customers_id FROM ' . mits_cdb_tools_qi($customers) . ' WHERE customers_status = 0 ORDER BY customers_id');
    $ids = array();
    while ($row = xtc_db_fetch_array($result)) {
        $ids[] = (int)$row['customers_id'];
    }
    return $ids;
}

function mits_cdb_tools_check_admin()
{
    $current = isset($_SESSION['customer_id']) ? (int)$_SESSION['customer_id'] : 0;
    if ($current <= 0) {
        throw new RuntimeException(mits_cdb_tools_text('error_admin_login'));
    }
    $customers = mits_cdb_tools_table('TABLE_CUSTOMERS', 'customers');
    $count = (int)mits_cdb_tools_scalar(
        'SELECT COUNT(*) FROM ' . mits_cdb_tools_qi($customers)
        . ' WHERE customers_id = ' . $current . ' AND customers_status = 0'
    );
    if ($count !== 1) {
        throw new RuntimeException(mits_cdb_tools_text('error_admin_account'));
    }
    return $current;
}

function mits_cdb_tools_customer_filter()
{
    $customers = mits_cdb_tools_table('TABLE_CUSTOMERS', 'customers');
    $info = mits_cdb_tools_table('TABLE_CUSTOMERS_INFO', 'customers_info');
    $mode = (string)mits_cdb_tools_post('customer_mode', 'all_non_admin');
    $where = array('c.customers_status <> 0');
    $joins = '';
    $description = '';

    if ($mode === 'guests') {
        if (!mits_cdb_tools_column_exists($customers, 'account_type')) {
            throw new RuntimeException(mits_cdb_tools_text('error_account_type_missing'));
        }
        $where[] = "c.account_type = '1'";
        $description = mits_cdb_tools_text('desc_customers_guests');
    } elseif ($mode === 'inactive') {
        $days = max(1, mits_cdb_tools_int('customer_days', 1095));
        $joins = ' JOIN ' . mits_cdb_tools_qi($info) . ' ci ON ci.customers_info_id = c.customers_id';
        $where[] = 'ci.customers_info_date_of_last_logon < DATE_SUB(NOW(), INTERVAL ' . $days . ' DAY)';
        $description = mits_cdb_tools_format('desc_customers_inactive', $days);
    } elseif ($mode === 'never_logged_in') {
        $days = max(1, mits_cdb_tools_int('customer_days', 1095));
        $joins = ' JOIN ' . mits_cdb_tools_qi($info) . ' ci ON ci.customers_info_id = c.customers_id';
        $where[] = 'ci.customers_info_number_of_logons = 0';
        $where[] = 'ci.customers_info_date_account_created < DATE_SUB(NOW(), INTERVAL ' . $days . ' DAY)';
        $description = mits_cdb_tools_format('desc_customers_never', $days);
    } elseif ($mode === 'ids') {
        $ids = mits_cdb_tools_parse_ids(mits_cdb_tools_post('customer_ids', ''));
        if (empty($ids)) {
            throw new RuntimeException(mits_cdb_tools_text('error_customer_ids'));
        }
        $where[] = 'c.customers_id IN (' . mits_cdb_tools_ids_sql($ids) . ')';
        $description = mits_cdb_tools_text('desc_customers_ids');
    } else {
        $mode = 'all_non_admin';
        $description = mits_cdb_tools_text('desc_customers_all');
    }

    $keepIds = mits_cdb_tools_parse_ids(mits_cdb_tools_post('customer_keep_ids', ''));
    if (!empty($keepIds)) {
        $where[] = 'c.customers_id NOT IN (' . mits_cdb_tools_ids_sql($keepIds) . ')';
    }

    $email = trim((string)mits_cdb_tools_post('customer_email', ''));
    if ($email !== '') {
        $where[] = "c.customers_email_address LIKE '%" . xtc_db_input($email) . "%'";
    }

    return array(
        'table' => $customers,
        'info' => $info,
        'joins' => $joins,
        'where' => implode(' AND ', $where),
        'description' => $description,
        'keep_ids' => $keepIds,
        'mode' => $mode,
    );
}

function mits_cdb_tools_customer_preview($filter)
{
    $sql = 'SELECT c.customers_id, c.customers_firstname, c.customers_lastname, c.customers_email_address, c.customers_status'
         . (mits_cdb_tools_column_exists($filter['table'], 'account_type') ? ', c.account_type' : '')
         . ' FROM ' . mits_cdb_tools_qi($filter['table']) . ' c'
         . $filter['joins']
         . ' WHERE ' . $filter['where']
         . ' ORDER BY c.customers_id';
    $rows = mits_cdb_tools_fetch_all($sql, 25);
    $count = (int)mits_cdb_tools_scalar(
        'SELECT COUNT(DISTINCT c.customers_id) FROM ' . mits_cdb_tools_qi($filter['table']) . ' c'
        . $filter['joins'] . ' WHERE ' . $filter['where']
    );
    return array($count, $rows);
}

function mits_cdb_tools_delete_customers($filter, $resetAi, $deleteReviews, $deleteOrders)
{
    $select = 'SELECT DISTINCT c.customers_id FROM ' . mits_cdb_tools_qi($filter['table']) . ' c'
            . $filter['joins'] . ' WHERE ' . $filter['where'];
    $tmp = mits_cdb_tools_temp_ids($select, '_mits_cdb_tools_customer_ids');
    $count = (int)mits_cdb_tools_scalar('SELECT COUNT(*) FROM ' . mits_cdb_tools_qi($tmp));
    if ($count < 1) {
        return array(0, array(mits_cdb_tools_text('log_no_customers')));
    }

    $log = array();

    if ($deleteOrders) {
        $orders = mits_cdb_tools_table('TABLE_ORDERS', 'orders');
        if ($orders !== '' && mits_cdb_tools_table_exists($orders) && mits_cdb_tools_column_exists($orders, 'customers_id')) {
            $orderFilter = array(
                'table' => $orders,
                'where' => 'o.customers_id IN (SELECT id FROM ' . mits_cdb_tools_qi($tmp) . ')'
            );
            list($deletedOrders, $orderLog) = mits_cdb_tools_delete_orders($orderFilter, false);
            $log[] = mits_cdb_tools_format('log_customer_orders', (int)$deletedOrders);
            foreach ($orderLog as $line) {
                $log[] = mits_cdb_tools_format('log_orders_prefix', $line);
            }
        }
    }

    $reviews = mits_cdb_tools_table('TABLE_REVIEWS', 'reviews');
    $reviewsDescription = mits_cdb_tools_table('TABLE_REVIEWS_DESCRIPTION', 'reviews_description');
    if (mits_cdb_tools_table_exists($reviews) && mits_cdb_tools_column_exists($reviews, 'customers_id')) {
        if ($deleteReviews) {
            $reviewTmp = '_mits_cdb_tools_review_ids';
            mits_cdb_tools_query('DROP TEMPORARY TABLE IF EXISTS ' . mits_cdb_tools_qi($reviewTmp));
            mits_cdb_tools_query('CREATE TEMPORARY TABLE ' . mits_cdb_tools_qi($reviewTmp) . ' (`id` BIGINT UNSIGNED NOT NULL PRIMARY KEY) ENGINE=MEMORY');
            mits_cdb_tools_query('INSERT IGNORE INTO ' . mits_cdb_tools_qi($reviewTmp) . ' (`id`) SELECT r.reviews_id FROM ' . mits_cdb_tools_qi($reviews) . ' r JOIN ' . mits_cdb_tools_qi($tmp) . ' x ON r.customers_id = x.id');
            mits_cdb_tools_delete_join($reviewsDescription, 'reviews_id', $reviewTmp, $reviewsDescription, $log);
            mits_cdb_tools_delete_join($reviews, 'reviews_id', $reviewTmp, $reviews, $log);
        } else {
            mits_cdb_tools_query('UPDATE ' . mits_cdb_tools_qi($reviews) . ' r JOIN ' . mits_cdb_tools_qi($tmp) . ' x ON r.customers_id = x.id SET r.customers_id = NULL');
            $log[] = mits_cdb_tools_format('log_reviews_anonymized', $reviews, (int)xtc_db_affected_rows());
        }
    }

    $deps = array(
        array('TABLE_CUSTOMERS_BASKET_ATTRIBUTES', 'customers_basket_attributes', 'customers_id'),
        array('TABLE_CUSTOMERS_BASKET', 'customers_basket', 'customers_id'),
        array('TABLE_CUSTOMERS_WISHLIST_ATTRIBUTES', 'customers_wishlist_attributes', 'customers_id'),
        array('TABLE_CUSTOMERS_WISHLIST', 'customers_wishlist', 'customers_id'),
        array('TABLE_CUSTOMERS_CHECKOUT', 'customers_checkout', 'customers_id'),
        array('TABLE_PRODUCTS_NOTIFICATIONS', 'products_notifications', 'customers_id'),
        array('TABLE_ADDRESS_BOOK', 'address_book', 'customers_id'),
        array('TABLE_CUSTOMERS_STATUS_HISTORY', 'customers_status_history', 'customers_id'),
        array('TABLE_CUSTOMERS_IP', 'customers_ip', 'customers_id'),
        array('TABLE_CUSTOMERS_MEMO', 'customers_memo', 'customers_id'),
        array('TABLE_ADMIN_ACCESS', 'admin_access', 'customers_id'),
        array('TABLE_WHOS_ONLINE', 'whos_online', 'customer_id'),
        array('TABLE_NEWSLETTER_RECIPIENTS', 'newsletter_recipients', 'customers_id'),
        array('TABLE_COUPON_GV_CUSTOMER', 'coupon_gv_customer', 'customer_id'),
    );
    foreach ($deps as $dep) {
        $table = mits_cdb_tools_table($dep[0], $dep[1]);
        mits_cdb_tools_delete_join($table, $dep[2], $tmp, $table, $log);
    }

    $login = mits_cdb_tools_table('TABLE_CUSTOMERS_LOGIN', 'customers_login');
    if (mits_cdb_tools_table_exists($login) && mits_cdb_tools_column_exists($login, 'customers_email_address')) {
        $sql = 'DELETE cl FROM ' . mits_cdb_tools_qi($login) . ' cl'
             . ' JOIN ' . mits_cdb_tools_qi($filter['table']) . ' c ON c.customers_email_address = cl.customers_email_address'
             . ' JOIN ' . mits_cdb_tools_qi($tmp) . ' x ON x.id = c.customers_id'
             . ' WHERE NOT EXISTS ('
             . 'SELECT 1 FROM ' . mits_cdb_tools_qi($filter['table']) . ' ca'
             . ' WHERE ca.customers_status = 0 AND ca.customers_email_address = cl.customers_email_address)';
        mits_cdb_tools_query($sql);
        $log[] = $login . ': ' . (int)xtc_db_affected_rows();
    }

    $info = mits_cdb_tools_table('TABLE_CUSTOMERS_INFO', 'customers_info');
    mits_cdb_tools_delete_join($info, 'customers_info_id', $tmp, $info, $log);
    mits_cdb_tools_delete_join($filter['table'], 'customers_id', $tmp, $filter['table'], $log);

    if ($resetAi) {
        foreach (array($filter['table'], $info, mits_cdb_tools_table('TABLE_ADDRESS_BOOK', 'address_book'), mits_cdb_tools_table('TABLE_CUSTOMERS_MEMO', 'customers_memo'), mits_cdb_tools_table('TABLE_CUSTOMERS_IP', 'customers_ip'), mits_cdb_tools_table('TABLE_CUSTOMERS_STATUS_HISTORY', 'customers_status_history')) as $table) {
            mits_cdb_tools_reset_ai($table);
        }
        $log[] = mits_cdb_tools_text('log_ai_min_reset');
    }

    return array($count, $log);
}

function mits_cdb_tools_order_filter()
{
    $orders = mits_cdb_tools_table('TABLE_ORDERS', 'orders');
    $scope = (string)mits_cdb_tools_post('order_scope', 'filtered');
    $where = array('1=1');
    $used = 0;

    if ($scope === 'all') {
        return array('table' => $orders, 'where' => '1=1', 'description' => mits_cdb_tools_text('desc_orders_all'), 'scope' => 'all');
    }

    $from = mits_cdb_tools_int('order_id_from', 0);
    $to = mits_cdb_tools_int('order_id_to', 0);
    $before = mits_cdb_tools_date(mits_cdb_tools_post('order_date_before', ''));
    $status = mits_cdb_tools_int('order_status', 0);
    $customerId = mits_cdb_tools_int('order_customer_id', 0);

    if ($from > 0) {
        $where[] = 'o.orders_id >= ' . $from;
        $used++;
    }
    if ($to > 0) {
        $where[] = 'o.orders_id <= ' . $to;
        $used++;
    }
    if ($before !== '') {
        $where[] = "o.date_purchased < '" . xtc_db_input($before . ' 00:00:00') . "'";
        $used++;
    }
    if ($status > 0) {
        $where[] = 'o.orders_status = ' . $status;
        $used++;
    }
    if ($customerId > 0) {
        $where[] = 'o.customers_id = ' . $customerId;
        $used++;
    }
    if ($used === 0) {
        throw new RuntimeException(mits_cdb_tools_text('error_order_filter'));
    }

    return array('table' => $orders, 'where' => implode(' AND ', $where), 'description' => mits_cdb_tools_text('desc_orders_filtered'), 'scope' => 'filtered');
}

function mits_cdb_tools_order_preview($filter)
{
    $count = (int)mits_cdb_tools_scalar('SELECT COUNT(*) FROM ' . mits_cdb_tools_qi($filter['table']) . ' o WHERE ' . $filter['where']);
    $rows = mits_cdb_tools_fetch_all(
        'SELECT o.orders_id, o.customers_id, o.customers_name, o.customers_email_address, o.orders_status, o.date_purchased'
        . ' FROM ' . mits_cdb_tools_qi($filter['table']) . ' o'
        . ' WHERE ' . $filter['where'] . ' ORDER BY o.orders_id',
        25
    );
    return array($count, $rows);
}

function mits_cdb_tools_order_reference_columns($ordersTable)
{
    $sql = "SELECT TABLE_NAME, COLUMN_NAME
              FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND COLUMN_NAME IN ('orders_id','order_id')
               AND TABLE_NAME <> '" . xtc_db_input($ordersTable) . "'
             ORDER BY TABLE_NAME, COLUMN_NAME";
    $result = mits_cdb_tools_query($sql);
    $refs = array();
    while ($row = xtc_db_fetch_array($result)) {
        $key = $row['TABLE_NAME'] . '.' . $row['COLUMN_NAME'];
        $refs[$key] = array('table' => (string)$row['TABLE_NAME'], 'column' => (string)$row['COLUMN_NAME']);
    }
    return array_values($refs);
}

function mits_cdb_tools_delete_orders($filter, $resetAi)
{
    $select = 'SELECT o.orders_id FROM ' . mits_cdb_tools_qi($filter['table']) . ' o WHERE ' . $filter['where'];
    $tmp = mits_cdb_tools_temp_ids($select);
    $count = (int)mits_cdb_tools_scalar('SELECT COUNT(*) FROM ' . mits_cdb_tools_qi($tmp));
    if ($count < 1) {
        return array(0, array(mits_cdb_tools_text('log_no_orders')));
    }

    $log = array();
    $refs = mits_cdb_tools_order_reference_columns($filter['table']);
    $priority = array(
        'orders_products_download' => 10,
        'orders_products_attributes' => 20,
        'orders_products' => 30,
        'orders_status_history' => 40,
        'orders_total' => 50,
        'orders_recalculate' => 60,
        'orders_tracking' => 70,
        'coupon_gv_queue' => 80,
    );
    usort($refs, function ($a, $b) use ($priority) {
        $pa = isset($priority[$a['table']]) ? $priority[$a['table']] : 500;
        $pb = isset($priority[$b['table']]) ? $priority[$b['table']] : 500;
        if ($pa === $pb) {
            return strcmp($a['table'] . '.' . $a['column'], $b['table'] . '.' . $b['column']);
        }
        return $pa <=> $pb;
    });

    mits_cdb_tools_query('SET FOREIGN_KEY_CHECKS = 0');
    try {
        foreach ($refs as $ref) {
            mits_cdb_tools_delete_join($ref['table'], $ref['column'], $tmp, $ref['table'] . '.' . $ref['column'], $log);
        }
        mits_cdb_tools_delete_join($filter['table'], 'orders_id', $tmp, $filter['table'], $log);
    } catch (Throwable $e) {
        try { mits_cdb_tools_query('SET FOREIGN_KEY_CHECKS = 1'); } catch (Throwable $ignore) {}
        throw $e;
    }
    mits_cdb_tools_query('SET FOREIGN_KEY_CHECKS = 1');

    if ($resetAi) {
        $tables = array($filter['table']);
        foreach ($refs as $ref) {
            $tables[$ref['table']] = $ref['table'];
        }
        foreach ($tables as $table) {
            mits_cdb_tools_reset_ai($table);
        }
        $log[] = mits_cdb_tools_text('log_orders_ai_reset');
    }

    return array($count, $log);
}

function mits_cdb_tools_review_filter()
{
    $reviews = mits_cdb_tools_table('TABLE_REVIEWS', 'reviews');
    $mode = (string)mits_cdb_tools_post('review_mode', 'all');
    $where = '1=1';
    $description = mits_cdb_tools_text('desc_reviews_all');
    if ($mode === 'guests') {
        $where = 'r.customers_id = 0';
        $description = mits_cdb_tools_text('desc_reviews_guests');
    } elseif ($mode === 'products') {
        $ids = mits_cdb_tools_parse_ids(mits_cdb_tools_post('review_product_ids', ''));
        if (empty($ids)) {
            throw new RuntimeException(mits_cdb_tools_text('error_review_product_ids'));
        }
        $where = 'r.products_id IN (' . mits_cdb_tools_ids_sql($ids) . ')';
        $description = mits_cdb_tools_text('desc_reviews_products');
    }
    return array('table' => $reviews, 'where' => $where, 'description' => $description, 'mode' => $mode);
}

function mits_cdb_tools_review_preview($filter)
{
    $count = (int)mits_cdb_tools_scalar('SELECT COUNT(*) FROM ' . mits_cdb_tools_qi($filter['table']) . ' r WHERE ' . $filter['where']);
    $rows = mits_cdb_tools_fetch_all(
        'SELECT r.reviews_id, r.products_id, r.customers_id, r.customers_name, r.reviews_rating, r.date_added'
        . ' FROM ' . mits_cdb_tools_qi($filter['table']) . ' r WHERE ' . $filter['where'] . ' ORDER BY r.reviews_id',
        25
    );
    return array($count, $rows);
}

function mits_cdb_tools_delete_reviews($filter, $resetAi)
{
    $tmp = mits_cdb_tools_temp_ids('SELECT r.reviews_id FROM ' . mits_cdb_tools_qi($filter['table']) . ' r WHERE ' . $filter['where']);
    $count = (int)mits_cdb_tools_scalar('SELECT COUNT(*) FROM ' . mits_cdb_tools_qi($tmp));
    $log = array();
    $desc = mits_cdb_tools_table('TABLE_REVIEWS_DESCRIPTION', 'reviews_description');
    mits_cdb_tools_delete_join($desc, 'reviews_id', $tmp, $desc, $log);
    mits_cdb_tools_delete_join($filter['table'], 'reviews_id', $tmp, $filter['table'], $log);
    if ($resetAi) {
        mits_cdb_tools_reset_ai($filter['table']);
        mits_cdb_tools_reset_ai($desc);
        $log[] = mits_cdb_tools_text('log_reviews_ai_reset');
    }
    return array($count, $log);
}

function mits_cdb_tools_product_filter()
{
    $products = mits_cdb_tools_table('TABLE_PRODUCTS', 'products');
    $ptc = mits_cdb_tools_table('TABLE_PRODUCTS_TO_CATEGORIES', 'products_to_categories');
    $scope = (string)mits_cdb_tools_post('product_scope', 'filtered');
    if ($scope === 'all') {
        return array('table' => $products, 'where' => '1=1', 'joins' => '', 'scope' => 'all', 'description' => mits_cdb_tools_text('desc_products_all'));
    }

    $where = array('1=1');
    $joins = '';
    $used = 0;
    $status = (string)mits_cdb_tools_post('product_status', '');
    if ($status === '0' || $status === '1') {
        $where[] = 'p.products_status = ' . (int)$status;
        $used++;
    }
    $ids = mits_cdb_tools_parse_ids(mits_cdb_tools_post('product_ids', ''));
    if (!empty($ids)) {
        $where[] = 'p.products_id IN (' . mits_cdb_tools_ids_sql($ids) . ')';
        $used++;
    }
    $categoryIds = mits_cdb_tools_parse_ids(mits_cdb_tools_post('product_category_ids', ''));
    if (!empty($categoryIds)) {
        $where[] = 'EXISTS (SELECT 1 FROM ' . mits_cdb_tools_qi($ptc) . ' pc WHERE pc.products_id = p.products_id AND pc.categories_id IN (' . mits_cdb_tools_ids_sql($categoryIds) . '))';
        $used++;
    }
    $before = mits_cdb_tools_date(mits_cdb_tools_post('product_date_before', ''));
    if ($before !== '') {
        $where[] = "p.products_date_added < '" . xtc_db_input($before . ' 00:00:00') . "'";
        $used++;
    }
    if ((string)mits_cdb_tools_post('product_without_category', '') === '1') {
        $where[] = 'NOT EXISTS (SELECT 1 FROM ' . mits_cdb_tools_qi($ptc) . ' pc2 WHERE pc2.products_id = p.products_id)';
        $used++;
    }
    if ($used === 0) {
        throw new RuntimeException(mits_cdb_tools_text('error_product_filter'));
    }
    return array('table' => $products, 'where' => implode(' AND ', $where), 'joins' => $joins, 'scope' => 'filtered', 'description' => mits_cdb_tools_text('desc_products_filtered'));
}

function mits_cdb_tools_product_preview($filter)
{
    $desc = mits_cdb_tools_table('TABLE_PRODUCTS_DESCRIPTION', 'products_description');
    $lang = isset($_SESSION['languages_id']) ? (int)$_SESSION['languages_id'] : 1;
    $count = (int)mits_cdb_tools_scalar('SELECT COUNT(*) FROM ' . mits_cdb_tools_qi($filter['table']) . ' p WHERE ' . $filter['where']);
    $rows = mits_cdb_tools_fetch_all(
        'SELECT p.products_id, p.products_model, p.products_status, p.products_date_added, pd.products_name'
        . ' FROM ' . mits_cdb_tools_qi($filter['table']) . ' p'
        . ' LEFT JOIN ' . mits_cdb_tools_qi($desc) . ' pd ON pd.products_id = p.products_id AND pd.language_id = ' . $lang
        . ' WHERE ' . $filter['where'] . ' ORDER BY p.products_id',
        25
    );
    return array($count, $rows);
}

function mits_cdb_tools_load_categories_class()
{
    require_once(DIR_WS_CLASSES . 'categories.php');
    return new categories();
}

function mits_cdb_tools_delete_products($filter, $resetAi, $clearMasterData)
{
    if ($clearMasterData && $filter['scope'] !== 'all') {
        throw new RuntimeException(mits_cdb_tools_text('error_product_masterdata'));
    }

    $result = mits_cdb_tools_query('SELECT p.products_id FROM ' . mits_cdb_tools_qi($filter['table']) . ' p WHERE ' . $filter['where'] . ' ORDER BY p.products_id');
    $ids = array();
    while ($row = xtc_db_fetch_array($result)) {
        $ids[] = (int)$row['products_id'];
    }
    if (empty($ids)) {
        return array(0, array(mits_cdb_tools_text('log_no_products')));
    }

    $catfunc = mits_cdb_tools_load_categories_class();
    $done = 0;
    foreach ($ids as $id) {
        $catfunc->remove_product($id);

        foreach (array(
            array('TABLE_PRODUCTS_GRADUATED_PRICES', 'products_graduated_prices'),
            array('TABLE_PRODUCTS_NOTIFICATIONS', 'products_notifications'),
            array('TABLE_PRODUCTS_GEO_ZONES_TO_TAX_CLASS', 'products_geo_zones_to_tax_class')
        ) as $extraRef) {
            $extraTable = mits_cdb_tools_table($extraRef[0], $extraRef[1]);
            if ($extraTable !== '' && mits_cdb_tools_table_exists($extraTable) && mits_cdb_tools_column_exists($extraTable, 'products_id')) {
                mits_cdb_tools_query('DELETE FROM ' . mits_cdb_tools_qi($extraTable) . ' WHERE products_id = ' . (int)$id);
            }
        }
        $done++;
    }
    $log = array(mits_cdb_tools_format('log_products_deleted', $done));

    if ($clearMasterData) {
        $masterTables = array(
            mits_cdb_tools_table('TABLE_PRODUCTS_OPTIONS', 'products_options'),
            mits_cdb_tools_table('TABLE_PRODUCTS_OPTIONS_VALUES', 'products_options_values'),
            mits_cdb_tools_table('TABLE_PRODUCTS_OPTIONS_VALUES_TO_PRODUCTS_OPTIONS', 'products_options_values_to_products_options'),
            mits_cdb_tools_table('TABLE_PRODUCTS_VPE', 'products_vpe'),
            mits_cdb_tools_table('TABLE_PRODUCTS_XSELL_GROUPS', 'products_xsell_grp_name'),
        );
        foreach ($masterTables as $table) {
            if ($table !== '' && mits_cdb_tools_table_exists($table)) {
                mits_cdb_tools_query('DELETE FROM ' . mits_cdb_tools_qi($table));
                $log[] = $table . ': ' . (int)xtc_db_affected_rows();
            }
        }
    }

    if ($resetAi) {
        $resetTables = array(
            $filter['table'],
            mits_cdb_tools_table('TABLE_PRODUCTS_ATTRIBUTES', 'products_attributes'),
            mits_cdb_tools_table('TABLE_PRODUCTS_CONTENT', 'products_content'),
            mits_cdb_tools_table('TABLE_PRODUCTS_IMAGES', 'products_images'),
            mits_cdb_tools_table('TABLE_PRODUCTS_XSELL', 'products_xsell'),
            mits_cdb_tools_table('TABLE_REVIEWS', 'reviews'),
            mits_cdb_tools_table('TABLE_SPECIALS', 'specials'),
        );
        foreach ($resetTables as $table) {
            mits_cdb_tools_reset_ai($table);
        }
        $log[] = mits_cdb_tools_text('log_products_ai_reset');
    }

    return array(count($ids), $log);
}

function mits_cdb_tools_category_ids($includeChildren)
{
    $categories = mits_cdb_tools_table('TABLE_CATEGORIES', 'categories');
    $scope = (string)mits_cdb_tools_post('category_scope', 'ids');
    if ($scope === 'all') {
        $result = mits_cdb_tools_query('SELECT categories_id, parent_id FROM ' . mits_cdb_tools_qi($categories));
        $ids = array();
        $parents = array();
        while ($row = xtc_db_fetch_array($result)) {
            $id = (int)$row['categories_id'];
            $ids[$id] = $id;
            $parents[$id] = (int)$row['parent_id'];
        }
        return array(array_values($ids), $parents, 'all');
    }

    $ids = mits_cdb_tools_parse_ids(mits_cdb_tools_post('category_ids', ''));
    if (empty($ids)) {
        throw new RuntimeException(mits_cdb_tools_text('error_category_ids'));
    }
    $selected = array();
    foreach ($ids as $id) {
        $selected[$id] = $id;
    }
    $parents = array();

    $result = mits_cdb_tools_query('SELECT categories_id, parent_id FROM ' . mits_cdb_tools_qi($categories));
    $children = array();
    while ($row = xtc_db_fetch_array($result)) {
        $id = (int)$row['categories_id'];
        $parent = (int)$row['parent_id'];
        $parents[$id] = $parent;
        if (!isset($children[$parent])) {
            $children[$parent] = array();
        }
        $children[$parent][] = $id;
    }

    if ($includeChildren) {
        $queue = array_values($selected);
        while (!empty($queue)) {
            $parent = array_shift($queue);
            if (empty($children[$parent])) {
                continue;
            }
            foreach ($children[$parent] as $child) {
                if (!isset($selected[$child])) {
                    $selected[$child] = $child;
                    $queue[] = $child;
                }
            }
        }
    }
    return array(array_values($selected), $parents, 'ids');
}

function mits_cdb_tools_category_depth($id, $parents)
{
    $depth = 0;
    $seen = array();
    while (isset($parents[$id]) && (int)$parents[$id] > 0 && !isset($seen[$id])) {
        $seen[$id] = true;
        $id = (int)$parents[$id];
        $depth++;
        if ($depth > 100) {
            break;
        }
    }
    return $depth;
}

function mits_cdb_tools_category_preview($ids)
{
    $categories = mits_cdb_tools_table('TABLE_CATEGORIES', 'categories');
    $desc = mits_cdb_tools_table('TABLE_CATEGORIES_DESCRIPTION', 'categories_description');
    $lang = isset($_SESSION['languages_id']) ? (int)$_SESSION['languages_id'] : 1;
    if (empty($ids)) {
        return array(0, array());
    }
    $rows = mits_cdb_tools_fetch_all(
        'SELECT c.categories_id, c.parent_id, c.categories_status, cd.categories_name'
        . ' FROM ' . mits_cdb_tools_qi($categories) . ' c'
        . ' LEFT JOIN ' . mits_cdb_tools_qi($desc) . ' cd ON cd.categories_id = c.categories_id AND cd.language_id = ' . $lang
        . ' WHERE c.categories_id IN (' . mits_cdb_tools_ids_sql($ids) . ') ORDER BY c.categories_id',
        25
    );
    $count = (int)mits_cdb_tools_scalar('SELECT COUNT(*) FROM ' . mits_cdb_tools_qi($categories) . ' WHERE categories_id IN (' . mits_cdb_tools_ids_sql($ids) . ')');
    return array($count, $rows);
}

function mits_cdb_tools_delete_categories($ids, $parents, $deleteOrphans, $resetAi)
{
    $categories = mits_cdb_tools_table('TABLE_CATEGORIES', 'categories');
    $ptc = mits_cdb_tools_table('TABLE_PRODUCTS_TO_CATEGORIES', 'products_to_categories');
    $existing = array();
    if (!empty($ids)) {
        $result = mits_cdb_tools_query('SELECT categories_id FROM ' . mits_cdb_tools_qi($categories) . ' WHERE categories_id IN (' . mits_cdb_tools_ids_sql($ids) . ')');
        while ($row = xtc_db_fetch_array($result)) {
            $existing[] = (int)$row['categories_id'];
        }
    }
    if (empty($existing)) {
        return array(0, array(mits_cdb_tools_text('log_no_categories')));
    }

    $candidateProducts = array();
    if ($deleteOrphans && mits_cdb_tools_table_exists($ptc)) {
        $result = mits_cdb_tools_query('SELECT DISTINCT products_id FROM ' . mits_cdb_tools_qi($ptc) . ' WHERE categories_id IN (' . mits_cdb_tools_ids_sql($existing) . ')');
        while ($row = xtc_db_fetch_array($result)) {
            $candidateProducts[] = (int)$row['products_id'];
        }
    }

    usort($existing, function ($a, $b) use ($parents) {
        return mits_cdb_tools_category_depth($b, $parents) <=> mits_cdb_tools_category_depth($a, $parents);
    });

    $catfunc = mits_cdb_tools_load_categories_class();
    foreach ($existing as $id) {
        $catfunc->remove_category($id);
    }

    $deletedProducts = 0;
    if ($deleteOrphans && !empty($candidateProducts)) {
        foreach ($candidateProducts as $productId) {
            $remaining = (int)mits_cdb_tools_scalar('SELECT COUNT(*) FROM ' . mits_cdb_tools_qi($ptc) . ' WHERE products_id = ' . $productId);
            if ($remaining === 0) {
                $catfunc->remove_product($productId);
                $deletedProducts++;
            }
        }
    }

    $log = array(mits_cdb_tools_format('log_categories_deleted', count($existing)));
    if ($deleteOrphans) {
        $log[] = mits_cdb_tools_format('log_orphan_products_deleted', $deletedProducts);
    }
    if ($resetAi) {
        mits_cdb_tools_reset_ai($categories);
        $log[] = mits_cdb_tools_text('log_categories_ai_reset');
    }
    return array(count($existing), $log);
}

function mits_cdb_tools_customer_related_counts($filter)
{
    $select = 'SELECT DISTINCT c.customers_id FROM ' . mits_cdb_tools_qi($filter['table']) . ' c'
            . $filter['joins'] . ' WHERE ' . $filter['where'];
    $counts = array('orders' => 0, 'newsletter' => 0);

    $orders = mits_cdb_tools_table('TABLE_ORDERS', 'orders');
    if ($orders !== '' && mits_cdb_tools_table_exists($orders) && mits_cdb_tools_column_exists($orders, 'customers_id')) {
        $counts['orders'] = (int)mits_cdb_tools_scalar(
            'SELECT COUNT(*) FROM ' . mits_cdb_tools_qi($orders) . ' o WHERE o.customers_id IN (' . $select . ')'
        );
    }

    $newsletter = mits_cdb_tools_table('TABLE_NEWSLETTER_RECIPIENTS', 'newsletter_recipients');
    if ($newsletter !== '' && mits_cdb_tools_table_exists($newsletter) && mits_cdb_tools_column_exists($newsletter, 'customers_id')) {
        $counts['newsletter'] = (int)mits_cdb_tools_scalar(
            'SELECT COUNT(*) FROM ' . mits_cdb_tools_qi($newsletter) . ' n WHERE n.customers_id IN (' . $select . ')'
        );
    }
    return $counts;
}

function mits_cdb_tools_options_preview($scope)
{
    $options = mits_cdb_tools_table('TABLE_PRODUCTS_OPTIONS', 'products_options');
    $values = mits_cdb_tools_table('TABLE_PRODUCTS_OPTIONS_VALUES', 'products_options_values');
    $attributes = mits_cdb_tools_table('TABLE_PRODUCTS_ATTRIBUTES', 'products_attributes');

    if (!mits_cdb_tools_table_exists($options) || !mits_cdb_tools_table_exists($values) || !mits_cdb_tools_table_exists($attributes)) {
        throw new RuntimeException(mits_cdb_tools_text('error_options_tables'));
    }

    if ($scope === 'all') {
        return array(
            'options' => (int)mits_cdb_tools_scalar('SELECT COUNT(DISTINCT products_options_id) FROM ' . mits_cdb_tools_qi($options)),
            'values' => (int)mits_cdb_tools_scalar('SELECT COUNT(DISTINCT products_options_values_id) FROM ' . mits_cdb_tools_qi($values)),
            'relations' => (int)mits_cdb_tools_scalar('SELECT COUNT(*) FROM ' . mits_cdb_tools_qi($attributes)),
        );
    }

    return array(
        'options' => (int)mits_cdb_tools_scalar(
            'SELECT COUNT(DISTINCT po.products_options_id) FROM ' . mits_cdb_tools_qi($options) . ' po'
            . ' WHERE NOT EXISTS (SELECT 1 FROM ' . mits_cdb_tools_qi($attributes) . ' pa WHERE pa.options_id = po.products_options_id)'
        ),
        'values' => (int)mits_cdb_tools_scalar(
            'SELECT COUNT(DISTINCT pov.products_options_values_id) FROM ' . mits_cdb_tools_qi($values) . ' pov'
            . ' WHERE NOT EXISTS (SELECT 1 FROM ' . mits_cdb_tools_qi($attributes) . ' pa WHERE pa.options_values_id = pov.products_options_values_id)'
        ),
        'relations' => 0,
    );
}

function mits_cdb_tools_delete_options($scope, $resetAi)
{
    $options = mits_cdb_tools_table('TABLE_PRODUCTS_OPTIONS', 'products_options');
    $values = mits_cdb_tools_table('TABLE_PRODUCTS_OPTIONS_VALUES', 'products_options_values');
    $map = mits_cdb_tools_table('TABLE_PRODUCTS_OPTIONS_VALUES_TO_PRODUCTS_OPTIONS', 'products_options_values_to_products_options');
    $attributes = mits_cdb_tools_table('TABLE_PRODUCTS_ATTRIBUTES', 'products_attributes');
    $downloads = mits_cdb_tools_table('TABLE_PRODUCTS_ATTRIBUTES_DOWNLOAD', 'products_attributes_download');
    $tagOptions = mits_cdb_tools_table('TABLE_PRODUCTS_TAGS_OPTIONS', 'products_tags_options');
    $tagValues = mits_cdb_tools_table('TABLE_PRODUCTS_TAGS_VALUES', 'products_tags_values');
    $tags = mits_cdb_tools_table('TABLE_PRODUCTS_TAGS', 'products_tags');
    $log = array();

    if ($scope === 'all') {
        if (mits_cdb_tools_table_exists($downloads)) {
            mits_cdb_tools_query('DELETE FROM ' . mits_cdb_tools_qi($downloads));
            $log[] = $downloads . ': ' . (int)xtc_db_affected_rows();
        }
        if (mits_cdb_tools_table_exists($attributes)) {
            mits_cdb_tools_query('DELETE FROM ' . mits_cdb_tools_qi($attributes));
            $log[] = $attributes . ': ' . (int)xtc_db_affected_rows();
        }
        foreach (array($map, $values, $options) as $table) {
            if ($table !== '' && mits_cdb_tools_table_exists($table)) {
                mits_cdb_tools_query('DELETE FROM ' . mits_cdb_tools_qi($table));
                $log[] = $table . ': ' . (int)xtc_db_affected_rows();
            }
        }
        if (mits_cdb_tools_table_exists($tagOptions) && mits_cdb_tools_column_exists($tagOptions, 'products_options_id')) {
            mits_cdb_tools_query('UPDATE ' . mits_cdb_tools_qi($tagOptions) . ' SET products_options_id = 0 WHERE products_options_id <> 0');
            $log[] = mits_cdb_tools_format('log_reference_detached', $tagOptions . '.products_options_id', (int)xtc_db_affected_rows());
        }
        if (mits_cdb_tools_table_exists($tagValues) && mits_cdb_tools_column_exists($tagValues, 'products_options_values_id')) {
            mits_cdb_tools_query('UPDATE ' . mits_cdb_tools_qi($tagValues) . ' SET products_options_values_id = 0 WHERE products_options_values_id <> 0');
            $log[] = mits_cdb_tools_format('log_reference_detached', $tagValues . '.products_options_values_id', (int)xtc_db_affected_rows());
        }
        if (mits_cdb_tools_table_exists($tags)) {
            if (mits_cdb_tools_column_exists($tags, 'products_options_id')) {
                mits_cdb_tools_query('UPDATE ' . mits_cdb_tools_qi($tags) . ' SET products_options_id = 0 WHERE products_options_id <> 0');
            }
            if (mits_cdb_tools_column_exists($tags, 'products_options_values_id')) {
                mits_cdb_tools_query('UPDATE ' . mits_cdb_tools_qi($tags) . ' SET products_options_values_id = 0 WHERE products_options_values_id <> 0');
            }
        }
    } else {
        $valueTmp = mits_cdb_tools_temp_ids(
            'SELECT DISTINCT pov.products_options_values_id FROM ' . mits_cdb_tools_qi($values) . ' pov'
            . ' WHERE NOT EXISTS (SELECT 1 FROM ' . mits_cdb_tools_qi($attributes) . ' pa WHERE pa.options_values_id = pov.products_options_values_id)',
            '_mits_cdb_tools_option_value_ids'
        );
        $valueCount = (int)mits_cdb_tools_scalar('SELECT COUNT(*) FROM ' . mits_cdb_tools_qi($valueTmp));
        if ($valueCount > 0) {
            if (mits_cdb_tools_table_exists($tagValues) && mits_cdb_tools_column_exists($tagValues, 'products_options_values_id')) {
                mits_cdb_tools_query('UPDATE ' . mits_cdb_tools_qi($tagValues) . ' tv JOIN ' . mits_cdb_tools_qi($valueTmp) . ' x ON tv.products_options_values_id = x.id SET tv.products_options_values_id = 0');
            }
            if (mits_cdb_tools_table_exists($tags) && mits_cdb_tools_column_exists($tags, 'products_options_values_id')) {
                mits_cdb_tools_query('UPDATE ' . mits_cdb_tools_qi($tags) . ' t JOIN ' . mits_cdb_tools_qi($valueTmp) . ' x ON t.products_options_values_id = x.id SET t.products_options_values_id = 0');
            }
            mits_cdb_tools_delete_join($map, 'products_options_values_id', $valueTmp, mits_cdb_tools_format('log_option_value_unused', $map), $log);
            mits_cdb_tools_delete_join($values, 'products_options_values_id', $valueTmp, mits_cdb_tools_format('log_option_value_unused', $values), $log);
        }

        $optionTmp = mits_cdb_tools_temp_ids(
            'SELECT DISTINCT po.products_options_id FROM ' . mits_cdb_tools_qi($options) . ' po'
            . ' WHERE NOT EXISTS (SELECT 1 FROM ' . mits_cdb_tools_qi($attributes) . ' pa WHERE pa.options_id = po.products_options_id)',
            '_mits_cdb_tools_option_ids'
        );
        $optionCount = (int)mits_cdb_tools_scalar('SELECT COUNT(*) FROM ' . mits_cdb_tools_qi($optionTmp));
        if ($optionCount > 0) {
            if (mits_cdb_tools_table_exists($tagOptions) && mits_cdb_tools_column_exists($tagOptions, 'products_options_id')) {
                mits_cdb_tools_query('UPDATE ' . mits_cdb_tools_qi($tagOptions) . ' t JOIN ' . mits_cdb_tools_qi($optionTmp) . ' x ON t.products_options_id = x.id SET t.products_options_id = 0');
            }
            if (mits_cdb_tools_table_exists($tags) && mits_cdb_tools_column_exists($tags, 'products_options_id')) {
                mits_cdb_tools_query('UPDATE ' . mits_cdb_tools_qi($tags) . ' t JOIN ' . mits_cdb_tools_qi($optionTmp) . ' x ON t.products_options_id = x.id SET t.products_options_id = 0');
            }
            mits_cdb_tools_delete_join($map, 'products_options_id', $optionTmp, mits_cdb_tools_format('log_option_unused', $map), $log);
            mits_cdb_tools_delete_join($options, 'products_options_id', $optionTmp, mits_cdb_tools_format('log_option_unused', $options), $log);
        }
    }

    if ($resetAi) {
        foreach (array($attributes, $map) as $table) {
            mits_cdb_tools_reset_ai($table);
        }
        $log[] = mits_cdb_tools_text('log_options_ai_reset');
    }
    return $log;
}

function mits_cdb_tools_tags_preview($scope)
{
    $tags = mits_cdb_tools_table('TABLE_PRODUCTS_TAGS', 'products_tags');
    $options = mits_cdb_tools_table('TABLE_PRODUCTS_TAGS_OPTIONS', 'products_tags_options');
    $values = mits_cdb_tools_table('TABLE_PRODUCTS_TAGS_VALUES', 'products_tags_values');
    if (!mits_cdb_tools_table_exists($tags) || !mits_cdb_tools_table_exists($options) || !mits_cdb_tools_table_exists($values)) {
        throw new RuntimeException(mits_cdb_tools_text('error_tags_tables'));
    }
    if ($scope === 'all') {
        return array(
            'options' => (int)mits_cdb_tools_scalar('SELECT COUNT(DISTINCT options_id) FROM ' . mits_cdb_tools_qi($options)),
            'values' => (int)mits_cdb_tools_scalar('SELECT COUNT(DISTINCT values_id) FROM ' . mits_cdb_tools_qi($values)),
            'relations' => (int)mits_cdb_tools_scalar('SELECT COUNT(*) FROM ' . mits_cdb_tools_qi($tags)),
        );
    }
    return array(
        'options' => (int)mits_cdb_tools_scalar(
            'SELECT COUNT(DISTINCT o.options_id) FROM ' . mits_cdb_tools_qi($options) . ' o'
            . ' WHERE NOT EXISTS (SELECT 1 FROM ' . mits_cdb_tools_qi($tags) . ' t WHERE t.options_id = o.options_id)'
        ),
        'values' => (int)mits_cdb_tools_scalar(
            'SELECT COUNT(DISTINCT v.values_id) FROM ' . mits_cdb_tools_qi($values) . ' v'
            . ' WHERE NOT EXISTS (SELECT 1 FROM ' . mits_cdb_tools_qi($tags) . ' t WHERE t.values_id = v.values_id)'
        ),
        'relations' => 0,
    );
}

function mits_cdb_tools_delete_tag_images_for_ids($tempTable, &$log)
{
    $values = mits_cdb_tools_table('TABLE_PRODUCTS_TAGS_VALUES', 'products_tags_values');
    if (!defined('DIR_FS_CATALOG_IMAGES') || !mits_cdb_tools_table_exists($values) || !mits_cdb_tools_column_exists($values, 'values_image')) {
        return;
    }
    $result = mits_cdb_tools_query(
        'SELECT DISTINCT v.values_image FROM ' . mits_cdb_tools_qi($values) . ' v'
        . ' JOIN ' . mits_cdb_tools_qi($tempTable) . ' x ON v.values_id = x.id'
        . " WHERE v.values_image <> ''"
    );
    $deleted = 0;
    while ($row = xtc_db_fetch_array($result)) {
        $image = ltrim(str_replace('\\', '/', (string)$row['values_image']), '/');
        if (strpos($image, 'tags/') !== 0 || strpos($image, '../') !== false) {
            continue;
        }
        $file = rtrim(DIR_FS_CATALOG_IMAGES, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $image);
        if (is_file($file) && @unlink($file)) {
            $deleted++;
        }
    }
    $log[] = mits_cdb_tools_format('log_tag_image_deleted', $deleted);
}

function mits_cdb_tools_delete_tags($scope, $deleteImages, $resetAi)
{
    $tags = mits_cdb_tools_table('TABLE_PRODUCTS_TAGS', 'products_tags');
    $options = mits_cdb_tools_table('TABLE_PRODUCTS_TAGS_OPTIONS', 'products_tags_options');
    $values = mits_cdb_tools_table('TABLE_PRODUCTS_TAGS_VALUES', 'products_tags_values');
    $log = array();

    if ($scope === 'all') {
        $valueTmp = mits_cdb_tools_temp_ids('SELECT DISTINCT values_id FROM ' . mits_cdb_tools_qi($values), '_mits_cdb_tools_tag_value_ids');
        if ($deleteImages) {
            mits_cdb_tools_delete_tag_images_for_ids($valueTmp, $log);
        }
        foreach (array($tags, $values, $options) as $table) {
            mits_cdb_tools_query('DELETE FROM ' . mits_cdb_tools_qi($table));
            $log[] = $table . ': ' . (int)xtc_db_affected_rows();
        }
    } else {
        $valueTmp = mits_cdb_tools_temp_ids(
            'SELECT DISTINCT v.values_id FROM ' . mits_cdb_tools_qi($values) . ' v'
            . ' WHERE NOT EXISTS (SELECT 1 FROM ' . mits_cdb_tools_qi($tags) . ' t WHERE t.values_id = v.values_id)',
            '_mits_cdb_tools_tag_value_ids'
        );
        if ($deleteImages) {
            mits_cdb_tools_delete_tag_images_for_ids($valueTmp, $log);
        }
        mits_cdb_tools_delete_join($values, 'values_id', $valueTmp, mits_cdb_tools_format('log_without_product_reference', $values), $log);

        $optionTmp = mits_cdb_tools_temp_ids(
            'SELECT DISTINCT o.options_id FROM ' . mits_cdb_tools_qi($options) . ' o'
            . ' WHERE NOT EXISTS (SELECT 1 FROM ' . mits_cdb_tools_qi($tags) . ' t WHERE t.options_id = o.options_id)',
            '_mits_cdb_tools_tag_option_ids'
        );
        mits_cdb_tools_delete_join($options, 'options_id', $optionTmp, mits_cdb_tools_format('log_without_product_reference', $options), $log);
    }

    if ($resetAi) {
        mits_cdb_tools_reset_ai($tags);
        $log[] = mits_cdb_tools_text('log_tags_ai_reset');
    }
    return $log;
}

function mits_cdb_tools_manufacturer_preview($scope)
{
    $manufacturers = mits_cdb_tools_table('TABLE_MANUFACTURERS', 'manufacturers');
    $products = mits_cdb_tools_table('TABLE_PRODUCTS', 'products');
    $where = '1=1';
    if ($scope !== 'all') {
        $where = 'NOT EXISTS (SELECT 1 FROM ' . mits_cdb_tools_qi($products) . ' p WHERE p.manufacturers_id = m.manufacturers_id)';
    }
    $count = (int)mits_cdb_tools_scalar('SELECT COUNT(*) FROM ' . mits_cdb_tools_qi($manufacturers) . ' m WHERE ' . $where);
    $rows = mits_cdb_tools_fetch_all(
        'SELECT m.manufacturers_id, m.manufacturers_name FROM ' . mits_cdb_tools_qi($manufacturers) . ' m WHERE ' . $where . ' ORDER BY m.manufacturers_id',
        25
    );
    return array($count, $rows, $where);
}

function mits_cdb_tools_delete_manufacturer_images($tempTable, &$log)
{
    $manufacturers = mits_cdb_tools_table('TABLE_MANUFACTURERS', 'manufacturers');
    if (!defined('DIR_FS_CATALOG_IMAGES') || !mits_cdb_tools_table_exists($manufacturers) || !mits_cdb_tools_column_exists($manufacturers, 'manufacturers_image')) {
        return;
    }
    $result = mits_cdb_tools_query(
        'SELECT DISTINCT m.manufacturers_image FROM ' . mits_cdb_tools_qi($manufacturers) . ' m'
        . ' JOIN ' . mits_cdb_tools_qi($tempTable) . ' x ON m.manufacturers_id = x.id'
        . " WHERE m.manufacturers_image <> ''"
    );
    $deleted = 0;
    while ($row = xtc_db_fetch_array($result)) {
        $image = basename((string)$row['manufacturers_image']);
        if ($image === '' || $image === '.' || $image === '..') {
            continue;
        }
        $candidates = array($image);
        if (defined('IMAGE_TYPE_EXTENSION') && IMAGE_TYPE_EXTENSION !== 'default' && strrpos($image, '.') !== false) {
            $candidates[] = substr($image, 0, strrpos($image, '.')) . '.' . IMAGE_TYPE_EXTENSION;
        }
        foreach (array_unique($candidates) as $name) {
            foreach (array('manufacturers/', 'manufacturers/original_images/') as $dir) {
                $file = rtrim(DIR_FS_CATALOG_IMAGES, '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $dir . $name);
                if (is_file($file) && @unlink($file)) {
                    $deleted++;
                }
            }
        }
    }
    $log[] = mits_cdb_tools_format('log_manufacturer_image_deleted', $deleted);
}

function mits_cdb_tools_delete_manufacturers($scope, $deleteImages, $resetAi)
{
    $manufacturers = mits_cdb_tools_table('TABLE_MANUFACTURERS', 'manufacturers');
    $info = mits_cdb_tools_table('TABLE_MANUFACTURERS_INFO', 'manufacturers_info');
    $products = mits_cdb_tools_table('TABLE_PRODUCTS', 'products');
    $select = 'SELECT m.manufacturers_id FROM ' . mits_cdb_tools_qi($manufacturers) . ' m';
    if ($scope !== 'all') {
        $select .= ' WHERE NOT EXISTS (SELECT 1 FROM ' . mits_cdb_tools_qi($products) . ' p WHERE p.manufacturers_id = m.manufacturers_id)';
    }
    $tmp = mits_cdb_tools_temp_ids($select, '_mits_cdb_tools_manufacturer_ids');
    $count = (int)mits_cdb_tools_scalar('SELECT COUNT(*) FROM ' . mits_cdb_tools_qi($tmp));
    $log = array();
    if ($count < 1) {
        return array(0, array(mits_cdb_tools_text('log_no_manufacturers')));
    }
    if ($deleteImages) {
        mits_cdb_tools_delete_manufacturer_images($tmp, $log);
    }
    if ($scope === 'all' && mits_cdb_tools_table_exists($products) && mits_cdb_tools_column_exists($products, 'manufacturers_id')) {
        mits_cdb_tools_query('UPDATE ' . mits_cdb_tools_qi($products) . ' p JOIN ' . mits_cdb_tools_qi($tmp) . ' x ON p.manufacturers_id = x.id SET p.manufacturers_id = 0');
        $log[] = mits_cdb_tools_format('log_products_unlinked_manufacturer', (int)xtc_db_affected_rows());
    }
    mits_cdb_tools_delete_join($info, 'manufacturers_id', $tmp, $info, $log);
    mits_cdb_tools_delete_join($manufacturers, 'manufacturers_id', $tmp, $manufacturers, $log);
    if ($resetAi) {
        mits_cdb_tools_reset_ai($manufacturers);
        $log[] = mits_cdb_tools_text('log_manufacturers_ai_reset');
    }
    return array($count, $log);
}

function mits_cdb_tools_faq_available()
{
    return defined('MODULE_MITS_FAQ_MANAGER_STATUS')
        && MODULE_MITS_FAQ_MANAGER_STATUS == 'true'
        && defined('TABLE_MITS_FAQ_CATEGORIES_DESCRIPTION')
        && defined('TABLE_MITS_FAQS_DESCRIPTION')
        && mits_cdb_tools_table_exists(TABLE_MITS_FAQ_CATEGORIES_DESCRIPTION)
        && mits_cdb_tools_table_exists(TABLE_MITS_FAQS_DESCRIPTION);
}

function mits_cdb_tools_imageslider_available()
{
    return defined('MODULE_MITS_IMAGESLIDER_STATUS')
        && MODULE_MITS_IMAGESLIDER_STATUS == 'true'
        && defined('TABLE_MITS_IMAGESLIDER')
        && defined('TABLE_MITS_IMAGESLIDER_INFO')
        && mits_cdb_tools_table_exists(TABLE_MITS_IMAGESLIDER)
        && mits_cdb_tools_table_exists(TABLE_MITS_IMAGESLIDER_INFO);
}

function mits_cdb_tools_replace_targets()
{
    $targets = array();
    if ((string)mits_cdb_tools_post('replace_categories', '') === '1') {
        $targets[] = array(
            'label' => mits_cdb_tools_text('replace_target_categories'),
            'table' => mits_cdb_tools_table('TABLE_CATEGORIES_DESCRIPTION', 'categories_description'),
            'language' => 'language_id',
            'columns' => array('categories_description', 'categories_name', 'categories_heading_title', 'categories_meta_title', 'categories_meta_description', 'categories_meta_keywords')
        );
    }
    if ((string)mits_cdb_tools_post('replace_products', '') === '1') {
        $targets[] = array(
            'label' => mits_cdb_tools_text('replace_target_products'),
            'table' => mits_cdb_tools_table('TABLE_PRODUCTS_DESCRIPTION', 'products_description'),
            'language' => 'language_id',
            'columns' => array('products_description', 'products_short_description', 'products_order_description', 'products_name', 'products_heading_title', 'products_meta_title', 'products_meta_description', 'products_meta_keywords', 'products_keywords')
        );
    }
    if ((string)mits_cdb_tools_post('replace_content', '') === '1') {
        $targets[] = array(
            'label' => mits_cdb_tools_text('replace_target_content'),
            'table' => mits_cdb_tools_table('TABLE_CONTENT_MANAGER', 'content_manager'),
            'language' => 'languages_id',
            'columns' => array('content_text', 'content_title', 'content_heading', 'content_meta_keywords', 'content_meta_title', 'content_meta_description')
        );
    }
    if ((string)mits_cdb_tools_post('replace_faq', '') === '1' && mits_cdb_tools_faq_available()) {
        $targets[] = array(
            'label' => mits_cdb_tools_text('replace_target_faq_categories'),
            'table' => (string)TABLE_MITS_FAQ_CATEGORIES_DESCRIPTION,
            'language' => 'languages_id',
            'columns' => array('name', 'description', 'slug', 'meta_title', 'meta_description', 'meta_keywords')
        );
        $targets[] = array(
            'label' => 'MITS FAQs',
            'table' => (string)TABLE_MITS_FAQS_DESCRIPTION,
            'language' => 'languages_id',
            'columns' => array('question', 'answer', 'slug', 'meta_title', 'meta_description', 'meta_keywords')
        );
    }
    if ((string)mits_cdb_tools_post('replace_imageslider', '') === '1' && mits_cdb_tools_imageslider_available()) {
        $targets[] = array(
            'label' => 'MITS ImageSlider',
            'table' => (string)TABLE_MITS_IMAGESLIDER,
            'language' => '',
            'columns' => array('imagesliders_name')
        );
        $targets[] = array(
            'label' => mits_cdb_tools_text('replace_target_imageslider'),
            'table' => (string)TABLE_MITS_IMAGESLIDER_INFO,
            'language' => 'languages_id',
            'columns' => array('imagesliders_title', 'imagesliders_alt', 'imagesliders_linktitle', 'imagesliders_url', 'imagesliders_description')
        );
    }
    if (empty($targets)) {
        throw new RuntimeException(mits_cdb_tools_text('error_replace_targets'));
    }
    return $targets;
}

function mits_cdb_tools_replace_preview($targets, $search, $languageId)
{
    $search = (string)$search;
    if ($search === '') {
        throw new RuntimeException(mits_cdb_tools_text('error_replace_search'));
    }
    $needle = "'" . xtc_db_input($search) . "'";
    $details = array();
    $total = 0;
    foreach ($targets as $target) {
        if (!mits_cdb_tools_table_exists($target['table'])) {
            continue;
        }
        foreach ($target['columns'] as $column) {
            if (!mits_cdb_tools_column_exists($target['table'], $column)) {
                continue;
            }
            $where = 'INSTR(BINARY ' . mits_cdb_tools_qi($column) . ', BINARY ' . $needle . ') > 0';
            if ($languageId > 0 && $target['language'] !== '' && mits_cdb_tools_column_exists($target['table'], $target['language'])) {
                $where .= ' AND ' . mits_cdb_tools_qi($target['language']) . ' = ' . (int)$languageId;
            }
            $count = (int)mits_cdb_tools_scalar('SELECT COUNT(*) FROM ' . mits_cdb_tools_qi($target['table']) . ' WHERE ' . $where);
            if ($count > 0) {
                $details[] = mits_cdb_tools_format('log_replace_hits', $target['label'] . ': ' . $target['table'] . '.' . $column, $count);
                $total += $count;
            }
        }
    }
    return array($total, $details);
}

function mits_cdb_tools_execute_replace($targets, $search, $replace, $languageId)
{
    list($total, $previewDetails) = mits_cdb_tools_replace_preview($targets, $search, $languageId);
    $searchSql = "'" . xtc_db_input((string)$search) . "'";
    $replaceSql = "'" . xtc_db_input((string)$replace) . "'";
    $log = array();
    foreach ($targets as $target) {
        if (!mits_cdb_tools_table_exists($target['table'])) {
            continue;
        }
        foreach ($target['columns'] as $column) {
            if (!mits_cdb_tools_column_exists($target['table'], $column)) {
                continue;
            }
            $where = 'INSTR(BINARY ' . mits_cdb_tools_qi($column) . ', BINARY ' . $searchSql . ') > 0';
            if ($languageId > 0 && $target['language'] !== '' && mits_cdb_tools_column_exists($target['table'], $target['language'])) {
                $where .= ' AND ' . mits_cdb_tools_qi($target['language']) . ' = ' . (int)$languageId;
            }
            mits_cdb_tools_query(
                'UPDATE ' . mits_cdb_tools_qi($target['table'])
                . ' SET ' . mits_cdb_tools_qi($column) . ' = REPLACE(' . mits_cdb_tools_qi($column) . ', ' . $searchSql . ', ' . $replaceSql . ')'
                . ' WHERE ' . $where
            );
            $affected = (int)xtc_db_affected_rows();
            if ($affected > 0) {
                $log[] = mits_cdb_tools_format('log_replace_changed', $target['label'] . ': ' . $target['table'] . '.' . $column, $affected);
            }
        }
    }
    if (empty($log) && $total === 0) {
        $log[] = mits_cdb_tools_text('log_no_content');
    }
    return array($total, $log, $previewDetails);
}

function mits_cdb_tools_prepare_session_for_swap()
{
    $sid = function_exists('session_id') ? (string)session_id() : '';
    $savePath = function_exists('session_save_path') ? (string)session_save_path() : '';
    $sessionName = function_exists('session_name') ? (string)session_name() : 'MODsid';

    if (function_exists('session_status') && session_status() === PHP_SESSION_ACTIVE) {
        @session_write_close();
    }

    if (defined('STORE_SESSIONS') && STORE_SESSIONS === 'files' && $sid !== '') {
        if ($savePath === '' && defined('SESSION_WRITE_DIRECTORY')) {
            $savePath = (string)SESSION_WRITE_DIRECTORY;
        }
        if ($savePath !== '') {
            $sessionFile = rtrim($savePath, '/\\') . DIRECTORY_SEPARATOR . 'sess_' . preg_replace('/[^A-Za-z0-9,-]/', '', $sid);
            if (is_file($sessionFile)) {
                @unlink($sessionFile);
            }
        }
    }

    if ($sessionName !== '' && isset($_COOKIE[$sessionName])) {
        @setcookie($sessionName, '', time() - 3600, '/');
    }
}

function mits_cdb_tools_mysqli()
{
    global $db_link;
    if (!isset($db_link) || !($db_link instanceof mysqli)) {
        throw new RuntimeException(mits_cdb_tools_text('error_mysqli'));
    }
    return $db_link;
}

function mits_cdb_tools_mysqli_query(mysqli $db, $sql)
{
    $res = $db->query($sql);
    if ($res === false) {
        throw new RuntimeException($db->error . "\nSQL: " . $sql);
    }
    return $res;
}

function mits_cdb_tools_mysqli_scalar(mysqli $db, $sql)
{
    $res = mits_cdb_tools_mysqli_query($db, $sql);
    if (!($res instanceof mysqli_result)) {
        return '';
    }
    $row = $res->fetch_row();
    return (string)($row[0] ?? '');
}

function mits_cdb_tools_sql_literal(mysqli $db, $value)
{
    if ($value === null) {
        return 'NULL';
    }
    return "'" . $db->real_escape_string((string)$value) . "'";
}

function mits_cdb_tools_mysql_range($type, $unsigned)
{
    $ranges = array(
        'tinyint' => array(-128, 127, 255),
        'smallint' => array(-32768, 32767, 65535),
        'mediumint' => array(-8388608, 8388607, 16777215),
        'int' => array(-2147483648, 2147483647, 4294967295),
        'bigint' => array(-9223372036854775807, 9223372036854775807, 9223372036854775807),
    );
    $type = strtolower((string)$type);
    if (!isset($ranges[$type])) {
        return array(null, null);
    }
    return $unsigned ? array(0, $ranges[$type][2]) : array($ranges[$type][0], $ranges[$type][1]);
}

function mits_cdb_tools_swap_temp_map(mysqli $db, $columns, $sourceIds)
{
    $minAllowed = null;
    $maxAllowed = null;
    $hasUnsigned = false;
    foreach ($columns as $col) {
        list($min, $max) = mits_cdb_tools_mysql_range($col['data_type'], $col['unsigned']);
        if ($min === null) {
            continue;
        }
        $minAllowed = $minAllowed === null ? $min : max($minAllowed, $min);
        $maxAllowed = $maxAllowed === null ? $max : min($maxAllowed, $max);
        if ($col['unsigned']) {
            $hasUnsigned = true;
        }
    }
    if ($minAllowed === null || $maxAllowed === null) {
        throw new RuntimeException(mits_cdb_tools_text('error_swap_temp'));
    }

    $candidates = array();
    if (!$hasUnsigned && $minAllowed <= -count($sourceIds)) {
        $candidate = array();
        $n = -1;
        foreach ($sourceIds as $id) {
            $candidate[$id] = $n--;
        }
        $candidates[] = $candidate;
    }

    $maxSeen = 0;
    foreach ($columns as $col) {
        if (!empty($col['string'])) {
            continue;
        }
        $value = mits_cdb_tools_mysqli_scalar($db, 'SELECT COALESCE(MAX(' . mits_cdb_tools_qi($col['column']) . '),0) FROM ' . mits_cdb_tools_qi($col['table']));
        if (is_numeric($value)) {
            $maxSeen = max($maxSeen, (int)$value);
        }
    }
    $start = max($maxSeen + 1, max($sourceIds) + 1);
    for ($offset = 0; $offset <= 1000; $offset += 10) {
        $base = $start + $offset;
        if ($base + count($sourceIds) - 1 > $maxAllowed) {
            continue;
        }
        $candidate = array();
        $i = 0;
        foreach ($sourceIds as $id) {
            $candidate[$id] = $base + $i++;
        }
        $candidates[] = $candidate;
    }

    foreach ($candidates as $candidate) {
        $collision = false;
        foreach ($columns as $col) {
            if (!empty($col['string'])) {
                $quotedValues = array();
                foreach (array_values($candidate) as $value) {
                    $quotedValues[] = "'" . $db->real_escape_string((string)(int)$value) . "'";
                }
                $values = implode(',', $quotedValues);
            } else {
                $values = implode(',', array_map('intval', array_values($candidate)));
            }
            $cnt = (int)mits_cdb_tools_mysqli_scalar($db, 'SELECT COUNT(*) FROM ' . mits_cdb_tools_qi($col['table']) . ' WHERE ' . mits_cdb_tools_qi($col['column']) . ' IN (' . $values . ')');
            if ($cnt > 0) {
                $collision = true;
                break;
            }
        }
        if (!$collision) {
            return $candidate;
        }
    }
    throw new RuntimeException(mits_cdb_tools_text('error_swap_collision'));
}

function mits_cdb_tools_swap_plan($idA, $idB)
{
    $sourceIds = array((int)$idA, (int)$idB);
    if (min($sourceIds) < 1 || count(array_unique($sourceIds)) !== 2) {
        throw new RuntimeException(mits_cdb_tools_text('error_swap_ids'));
    }

    $db = mits_cdb_tools_mysqli();
    $customers = mits_cdb_tools_table('TABLE_CUSTOMERS', 'customers');
    $sessions = mits_cdb_tools_table('TABLE_SESSIONS', 'sessions');
    $whosOnline = mits_cdb_tools_table('TABLE_WHOS_ONLINE', 'whos_online');
    $finalMap = array($idA => $idB, $idB => $idA);
    $inverseMap = array();
    foreach ($finalMap as $old => $new) {
        $inverseMap[$new] = $old;
    }
    $idsSql = mits_cdb_tools_ids_sql($sourceIds);

    $found = array();
    $res = mits_cdb_tools_mysqli_query($db, 'SELECT customers_id, customers_email_address, customers_firstname, customers_lastname, customers_status FROM ' . mits_cdb_tools_qi($customers) . ' WHERE customers_id IN (' . $idsSql . ') ORDER BY customers_id');
    while ($row = $res->fetch_assoc()) {
        $found[(int)$row['customers_id']] = $row;
    }
    foreach ($sourceIds as $id) {
        if (!isset($found[$id])) {
            throw new RuntimeException(mits_cdb_tools_format('error_swap_customer_missing', $id));
        }
    }

    $payloadColumns = array();
    $res = mits_cdb_tools_mysqli_query($db,
        "SELECT COLUMN_NAME, EXTRA FROM information_schema.columns
          WHERE table_schema = DATABASE()
            AND table_name = '" . $db->real_escape_string($customers) . "'
          ORDER BY ORDINAL_POSITION"
    );
    while ($row = $res->fetch_assoc()) {
        $name = (string)$row['COLUMN_NAME'];
        $extra = strtolower((string)$row['EXTRA']);
        if ($name === 'customers_id' || strpos($extra, 'generated') !== false) {
            continue;
        }
        $payloadColumns[] = $name;
    }

    $candidateColumns = array('customers_id', 'customer_id', 'customer_id_sent', 'customers_info_id');
    $quoted = array();
    foreach ($candidateColumns as $column) {
        $quoted[] = "'" . $db->real_escape_string($column) . "'";
    }
    $res = mits_cdb_tools_mysqli_query($db,
        "SELECT c.TABLE_NAME, c.COLUMN_NAME, c.DATA_TYPE, c.COLUMN_TYPE, c.EXTRA
           FROM information_schema.columns c
           JOIN information_schema.tables t ON t.TABLE_SCHEMA=c.TABLE_SCHEMA AND t.TABLE_NAME=c.TABLE_NAME
          WHERE c.TABLE_SCHEMA = DATABASE()
            AND c.COLUMN_NAME IN (" . implode(',', $quoted) . ")
            AND t.TABLE_TYPE='BASE TABLE'
          ORDER BY c.TABLE_NAME, c.ORDINAL_POSITION"
    );
    $numeric = array('tinyint', 'smallint', 'mediumint', 'int', 'bigint');
    $stringTypes = array('char', 'varchar');
    $columns = array();
    $skipped = array();
    $tables = array($customers => true);
    while ($row = $res->fetch_assoc()) {
        $type = strtolower((string)$row['DATA_TYPE']);
        $extra = strtolower((string)$row['EXTRA']);
        if ((!in_array($type, $numeric, true) && !in_array($type, $stringTypes, true)) || strpos($extra, 'auto_increment') !== false) {
            $skipped[] = array('table' => (string)$row['TABLE_NAME'], 'column' => (string)$row['COLUMN_NAME'], 'type' => (string)$row['COLUMN_TYPE']);
            continue;
        }
        $columnType = strtolower((string)$row['COLUMN_TYPE']);
        $columns[] = array(
            'table' => (string)$row['TABLE_NAME'],
            'column' => (string)$row['COLUMN_NAME'],
            'type' => (string)$row['COLUMN_TYPE'],
            'data_type' => $type,
            'unsigned' => in_array($type, $numeric, true) && strpos($columnType, 'unsigned') !== false,
            'string' => in_array($type, $stringTypes, true),
        );
        $tables[(string)$row['TABLE_NAME']] = true;
    }
    if (empty($columns)) {
        throw new RuntimeException(mits_cdb_tools_text('error_swap_columns'));
    }
    foreach (array($sessions, $whosOnline) as $table) {
        if ($table !== '' && mits_cdb_tools_table_exists($table)) {
            $tables[$table] = true;
        }
    }

    $autoBefore = (int)mits_cdb_tools_mysqli_scalar($db,
        "SELECT COALESCE(AUTO_INCREMENT,0) FROM information_schema.tables
          WHERE table_schema = DATABASE() AND table_name='" . $db->real_escape_string($customers) . "'"
    );
    $tmpMap = mits_cdb_tools_swap_temp_map($db, $columns, $sourceIds);

    return array(
        'db' => $db,
        'customers' => $customers,
        'sessions' => $sessions,
        'whos_online' => $whosOnline,
        'source_ids' => $sourceIds,
        'ids_sql' => $idsSql,
        'final_map' => $finalMap,
        'inverse_map' => $inverseMap,
        'found' => $found,
        'payload_columns' => $payloadColumns,
        'columns' => $columns,
        'skipped' => $skipped,
        'tables' => array_keys($tables),
        'tmp_map' => $tmpMap,
        'auto_before' => $autoBefore,
    );
}

function mits_cdb_tools_swap_case(mysqli $db, $col, $map)
{
    $caseParts = array();
    $whereValues = array();
    foreach ($map as $old => $new) {
        if (!empty($col['string'])) {
            $oldSql = "'" . $db->real_escape_string((string)$old) . "'";
            $newSql = "'" . $db->real_escape_string((string)$new) . "'";
        } else {
            $oldSql = (string)(int)$old;
            $newSql = (string)(int)$new;
        }
        $caseParts[] = 'WHEN ' . $oldSql . ' THEN ' . $newSql;
        $whereValues[] = $oldSql;
    }
    $column = mits_cdb_tools_qi($col['column']);
    return array(
        'expr' => 'CASE ' . $column . ' ' . implode(' ', $caseParts) . ' ELSE ' . $column . ' END',
        'where' => implode(',', $whereValues),
    );
}

function mits_cdb_tools_execute_swap($plan)
{
    
    /** @var mysqli $db */
    $db = $plan['db'];
    $log = array();
    $lockParts = array();
    foreach ($plan['tables'] as $table) {
        $lockParts[] = mits_cdb_tools_qi($table) . ' WRITE';
    }

    mits_cdb_tools_mysqli_query($db, 'SET FOREIGN_KEY_CHECKS = 0');
    mits_cdb_tools_mysqli_query($db, 'LOCK TABLES ' . implode(', ', $lockParts));
    try {
        $selectColumns = array_merge(array('customers_id'), $plan['payload_columns']);
        $quoted = array();
        foreach ($selectColumns as $column) {
            $quoted[] = mits_cdb_tools_qi($column);
        }
        $res = mits_cdb_tools_mysqli_query($db, 'SELECT ' . implode(',', $quoted) . ' FROM ' . mits_cdb_tools_qi($plan['customers']) . ' WHERE customers_id IN (' . $plan['ids_sql'] . ')');
        $payloads = array();
        while ($row = $res->fetch_assoc()) {
            $payloads[(int)$row['customers_id']] = $row;
        }
        foreach ($plan['inverse_map'] as $targetId => $sourceId) {
            $source = $payloads[$sourceId];
            $sets = array();
            foreach ($plan['payload_columns'] as $column) {
                $sets[] = mits_cdb_tools_qi($column) . ' = ' . mits_cdb_tools_sql_literal($db, array_key_exists($column, $source) ? $source[$column] : null);
            }
            mits_cdb_tools_mysqli_query($db, 'UPDATE ' . mits_cdb_tools_qi($plan['customers']) . ' SET ' . implode(',', $sets) . ' WHERE customers_id = ' . (int)$targetId . ' LIMIT 1');
            $log[] = mits_cdb_tools_format('log_swap_payload', (int)$targetId, (int)$db->affected_rows);
        }

        foreach ($plan['columns'] as $col) {
            $caseSql = mits_cdb_tools_swap_case($db, $col, $plan['tmp_map']);
            mits_cdb_tools_mysqli_query($db, 'UPDATE ' . mits_cdb_tools_qi($col['table']) . ' SET ' . mits_cdb_tools_qi($col['column']) . ' = ' . $caseSql['expr'] . ' WHERE ' . mits_cdb_tools_qi($col['column']) . ' IN (' . $caseSql['where'] . ')');
        }

        $tmpToFinal = array();
        foreach ($plan['final_map'] as $old => $new) {
            $tmpToFinal[$plan['tmp_map'][$old]] = $new;
        }
        foreach ($plan['columns'] as $col) {
            $caseSql = mits_cdb_tools_swap_case($db, $col, $tmpToFinal);
            mits_cdb_tools_mysqli_query($db, 'UPDATE ' . mits_cdb_tools_qi($col['table']) . ' SET ' . mits_cdb_tools_qi($col['column']) . ' = ' . $caseSql['expr'] . ' WHERE ' . mits_cdb_tools_qi($col['column']) . ' IN (' . $caseSql['where'] . ')');
        }

        if (in_array($plan['sessions'], $plan['tables'], true)) {
            mits_cdb_tools_mysqli_query($db, 'DELETE FROM ' . mits_cdb_tools_qi($plan['sessions']));
            $log[] = mits_cdb_tools_format('log_table_cleared', $plan['sessions']);
        }
        if (in_array($plan['whos_online'], $plan['tables'], true)) {
            mits_cdb_tools_mysqli_query($db, 'DELETE FROM ' . mits_cdb_tools_qi($plan['whos_online']));
            $log[] = mits_cdb_tools_format('log_table_cleared', $plan['whos_online']);
        }

        mits_cdb_tools_mysqli_query($db, 'UNLOCK TABLES');
        mits_cdb_tools_mysqli_query($db, 'SET FOREIGN_KEY_CHECKS = 1');
    } catch (Throwable $e) {
        try { $db->query('UNLOCK TABLES'); } catch (Throwable $ignore) {}
        try { $db->query('SET FOREIGN_KEY_CHECKS = 1'); } catch (Throwable $ignore) {}
        throw $e;
    }
    return $log;
}

$currentAdminId = 0;
$resultBox = null;
$activeTool = (string)mits_cdb_tools_post('tool', '');

try {
    $currentAdminId = mits_cdb_tools_check_admin();
    mits_cdb_tools_check_post_security();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $activeTool !== '') {
        $live = ((string)mits_cdb_tools_post('run_mode', '') === 'live');

        if ($activeTool === 'order_ai') {
            $orders = mits_cdb_tools_table('TABLE_ORDERS', 'orders');
            $info = mits_cdb_tools_ai_info($orders, 'orders_id');
            $next = max(1, mits_cdb_tools_int('order_next_value', $info['minimum']));
            if ($next < $info['minimum']) {
                throw new RuntimeException(mits_cdb_tools_format('error_order_next_min', $info['minimum']));
            }
            if ($live) {
                mits_cdb_tools_require_live(mits_cdb_tools_text('confirm_set'), false);
                mits_cdb_tools_query('ALTER TABLE ' . mits_cdb_tools_qi($orders) . ' AUTO_INCREMENT = ' . $next);
                mits_cdb_tools_rotate_token();
                $resultBox = mits_cdb_tools_result(
                    'success',
                    mits_cdb_tools_text('result_order_set_title'),
                    mits_cdb_tools_format('result_order_set_message', $next),
                    array(
                        mits_cdb_tools_format('result_order_old_ai', $info['current']),
                        mits_cdb_tools_format('result_order_highest', $info['max'])
                    )
                );
            } else {
                $resultBox = mits_cdb_tools_result(
                    'info',
                    mits_cdb_tools_text('result_order_preview_title'),
                    mits_cdb_tools_format('result_order_preview_message', $next),
                    array(
                        mits_cdb_tools_format('result_order_current_ai', $info['current']),
                        mits_cdb_tools_format('result_order_highest', $info['max']),
                        mits_cdb_tools_format('result_order_minimum', $info['minimum'])
                    )
                );
            }
        } elseif ($activeTool === 'customers') {
            $filter = mits_cdb_tools_customer_filter();
            list($count, $rows) = mits_cdb_tools_customer_preview($filter);
            if ($live) {
                mits_cdb_tools_require_live(mits_cdb_tools_text('confirm_delete'), true);
                list($deleted, $log) = mits_cdb_tools_delete_customers(
                    $filter,
                    (string)mits_cdb_tools_post('reset_ai', '') === '1',
                    (string)mits_cdb_tools_post('customer_delete_reviews', '') === '1',
                    (string)mits_cdb_tools_post('customer_delete_orders', '') === '1'
                );
                mits_cdb_tools_rotate_token();
                $resultBox = mits_cdb_tools_result(
                    'success',
                    mits_cdb_tools_text('result_customers_title'),
                    mits_cdb_tools_format('result_customers_message', $deleted),
                    $log
                );
            } else {
                $related = mits_cdb_tools_customer_related_counts($filter);
                $details = array(
                    mits_cdb_tools_format('result_customer_newsletter', (int)$related['newsletter']),
                    ((string)mits_cdb_tools_post('customer_delete_orders', '') === '1')
                        ? mits_cdb_tools_format('result_customer_orders_delete', (int)$related['orders'])
                        : mits_cdb_tools_format('result_customer_orders_keep', (int)$related['orders'])
                );
                foreach ($rows as $row) {
                    $details[] = '#' . (int)$row['customers_id'] . ' - ' . trim((string)$row['customers_firstname'] . ' ' . (string)$row['customers_lastname']) . ' - ' . (string)$row['customers_email_address'];
                }
                $resultBox = mits_cdb_tools_result(
                    'info',
                    mits_cdb_tools_text('result_customers_preview_title'),
                    mits_cdb_tools_format('result_customers_preview_message', $count, $filter['description']),
                    $details
                );
            }
        } elseif ($activeTool === 'orders') {
            $filter = mits_cdb_tools_order_filter();
            list($count, $rows) = mits_cdb_tools_order_preview($filter);
            if ($live) {
                mits_cdb_tools_require_live(mits_cdb_tools_text('confirm_delete'), true);
                list($deleted, $log) = mits_cdb_tools_delete_orders($filter, (string)mits_cdb_tools_post('reset_ai', '') === '1');
                mits_cdb_tools_rotate_token();
                $resultBox = mits_cdb_tools_result(
                    'success',
                    mits_cdb_tools_text('result_orders_title'),
                    mits_cdb_tools_format('result_orders_message', $deleted),
                    $log
                );
            } else {
                $refs = mits_cdb_tools_order_reference_columns($filter['table']);
                $details = array(mits_cdb_tools_format('result_orders_refs', count($refs)));
                foreach ($refs as $ref) {
                    $details[] = mits_cdb_tools_format('result_reference', $ref['table'] . '.' . $ref['column']);
                }
                foreach ($rows as $row) {
                    $details[] = mits_cdb_tools_format(
                        'result_order_row',
                        (int)$row['orders_id'],
                        (string)$row['date_purchased'],
                        (string)$row['customers_name'],
                        (int)$row['orders_status']
                    );
                }
                $resultBox = mits_cdb_tools_result(
                    'info',
                    mits_cdb_tools_text('result_orders_preview_title'),
                    mits_cdb_tools_format('result_orders_preview_message', $count),
                    $details
                );
            }
        } elseif ($activeTool === 'reviews') {
            $filter = mits_cdb_tools_review_filter();
            list($count, $rows) = mits_cdb_tools_review_preview($filter);
            if ($live) {
                mits_cdb_tools_require_live(mits_cdb_tools_text('confirm_delete'), true);
                list($deleted, $log) = mits_cdb_tools_delete_reviews($filter, (string)mits_cdb_tools_post('reset_ai', '') === '1');
                mits_cdb_tools_rotate_token();
                $resultBox = mits_cdb_tools_result(
                    'success',
                    mits_cdb_tools_text('result_reviews_title'),
                    mits_cdb_tools_format('result_reviews_message', $deleted),
                    $log
                );
            } else {
                $details = array();
                foreach ($rows as $row) {
                    $details[] = mits_cdb_tools_format(
                        'result_review_row',
                        (int)$row['reviews_id'],
                        (int)$row['products_id'],
                        (int)$row['customers_id'],
                        (int)$row['reviews_rating']
                    );
                }
                $resultBox = mits_cdb_tools_result(
                    'info',
                    mits_cdb_tools_text('result_reviews_preview_title'),
                    mits_cdb_tools_format('result_reviews_preview_message', $count, $filter['description']),
                    $details
                );
            }
        } elseif ($activeTool === 'newsletter') {
            $table = mits_cdb_tools_table('TABLE_NEWSLETTER_RECIPIENTS', 'newsletter_recipients');
            $history = mits_cdb_tools_table('TABLE_NEWSLETTER_RECIPIENTS_HISTORY', 'newsletter_recipients_history');
            $count = (int)mits_cdb_tools_scalar('SELECT COUNT(*) FROM ' . mits_cdb_tools_qi($table));
            if ($live) {
                mits_cdb_tools_require_live(mits_cdb_tools_text('confirm_delete'), true);
                $log = array();
                mits_cdb_tools_query('DELETE FROM ' . mits_cdb_tools_qi($table));
                $log[] = $table . ': ' . (int)xtc_db_affected_rows();
                if ((string)mits_cdb_tools_post('newsletter_history', '') === '1' && mits_cdb_tools_table_exists($history)) {
                    mits_cdb_tools_query('DELETE FROM ' . mits_cdb_tools_qi($history));
                    $log[] = $history . ': ' . (int)xtc_db_affected_rows();
                }
                mits_cdb_tools_rotate_token();
                $resultBox = mits_cdb_tools_result(
                    'success',
                    mits_cdb_tools_text('result_newsletter_title'),
                    mits_cdb_tools_format('result_newsletter_message', $count),
                    $log
                );
            } else {
                $resultBox = mits_cdb_tools_result(
                    'info',
                    mits_cdb_tools_text('result_newsletter_preview_title'),
                    mits_cdb_tools_format('result_newsletter_preview_message', $count),
                    array(
                        ((string)mits_cdb_tools_post('newsletter_history', '') === '1')
                            ? mits_cdb_tools_text('result_newsletter_history_delete')
                            : mits_cdb_tools_text('result_newsletter_history_keep')
                    )
                );
            }
        } elseif ($activeTool === 'products') {
            $filter = mits_cdb_tools_product_filter();
            list($count, $rows) = mits_cdb_tools_product_preview($filter);
            if ($live) {
                mits_cdb_tools_require_live(mits_cdb_tools_text('confirm_delete'), true);
                $clearMaster = (string)mits_cdb_tools_post('product_masterdata', '') === '1';
                list($deleted, $log) = mits_cdb_tools_delete_products($filter, (string)mits_cdb_tools_post('reset_ai', '') === '1', $clearMaster);
                mits_cdb_tools_rotate_token();
                $resultBox = mits_cdb_tools_result(
                    'success',
                    mits_cdb_tools_text('result_products_title'),
                    mits_cdb_tools_format('result_products_message', $deleted),
                    $log
                );
            } else {
                $details = array();
                foreach ($rows as $row) {
                    $details[] = mits_cdb_tools_format(
                        'result_product_row',
                        (int)$row['products_id'],
                        (string)$row['products_model'],
                        (string)$row['products_name'],
                        (int)$row['products_status']
                    );
                }
                $resultBox = mits_cdb_tools_result(
                    'info',
                    mits_cdb_tools_text('result_products_preview_title'),
                    mits_cdb_tools_format('result_products_preview_message', $count),
                    $details
                );
            }
        } elseif ($activeTool === 'categories') {
            $includeChildren = (string)mits_cdb_tools_post('category_children', '') === '1';
            list($ids, $parents, $scope) = mits_cdb_tools_category_ids($includeChildren);
            list($count, $rows) = mits_cdb_tools_category_preview($ids);
            if ($live) {
                mits_cdb_tools_require_live(mits_cdb_tools_text('confirm_delete'), true);
                list($deleted, $log) = mits_cdb_tools_delete_categories($ids, $parents, (string)mits_cdb_tools_post('category_delete_orphans', '') === '1', (string)mits_cdb_tools_post('reset_ai', '') === '1');
                mits_cdb_tools_rotate_token();
                $resultBox = mits_cdb_tools_result(
                    'success',
                    mits_cdb_tools_text('result_categories_title'),
                    mits_cdb_tools_format('result_categories_message', $deleted),
                    $log
                );
            } else {
                $details = array();
                foreach ($rows as $row) {
                    $details[] = mits_cdb_tools_format(
                        'result_category_row',
                        (int)$row['categories_id'],
                        (string)$row['categories_name'],
                        (int)$row['parent_id']
                    );
                }
                $resultBox = mits_cdb_tools_result(
                    'info',
                    mits_cdb_tools_text('result_categories_preview_title'),
                    mits_cdb_tools_format('result_categories_preview_message', $count),
                    $details
                );
            }
        } elseif ($activeTool === 'options') {
            $scope = (string)mits_cdb_tools_post('options_scope', 'unused');
            $scope = $scope === 'all' ? 'all' : 'unused';
            $preview = mits_cdb_tools_options_preview($scope);
            if ($live) {
                mits_cdb_tools_require_live(mits_cdb_tools_text('confirm_delete'), true);
                $log = mits_cdb_tools_delete_options($scope, (string)mits_cdb_tools_post('reset_ai', '') === '1');
                mits_cdb_tools_rotate_token();
                $resultBox = mits_cdb_tools_result(
                    'success',
                    mits_cdb_tools_text('result_options_title'),
                    mits_cdb_tools_text('result_options_message'),
                    $log
                );
            } else {
                $details = array(
                    mits_cdb_tools_format('result_options_count', $preview['options']),
                    mits_cdb_tools_format('result_options_values_count', $preview['values']),
                    mits_cdb_tools_format('result_relations_all_count', $preview['relations'])
                );
                $resultBox = mits_cdb_tools_result(
                    'info',
                    mits_cdb_tools_text('result_options_preview_title'),
                    $scope === 'all' ? mits_cdb_tools_text('result_options_preview_all') : mits_cdb_tools_text('result_options_preview_unused'),
                    $details
                );
            }
        } elseif ($activeTool === 'tags') {
            $scope = (string)mits_cdb_tools_post('tags_scope', 'unused');
            $scope = $scope === 'all' ? 'all' : 'unused';
            $preview = mits_cdb_tools_tags_preview($scope);
            if ($live) {
                mits_cdb_tools_require_live(mits_cdb_tools_text('confirm_delete'), true);
                $log = mits_cdb_tools_delete_tags($scope, (string)mits_cdb_tools_post('tags_delete_images', '') === '1', (string)mits_cdb_tools_post('reset_ai', '') === '1');
                mits_cdb_tools_rotate_token();
                $resultBox = mits_cdb_tools_result(
                    'success',
                    mits_cdb_tools_text('result_tags_title'),
                    mits_cdb_tools_text('result_tags_message'),
                    $log
                );
            } else {
                $details = array(
                    mits_cdb_tools_format('result_tags_options_count', $preview['options']),
                    mits_cdb_tools_format('result_tags_values_count', $preview['values']),
                    mits_cdb_tools_format('result_relations_all_count', $preview['relations']),
                    ((string)mits_cdb_tools_post('tags_delete_images', '') === '1')
                        ? mits_cdb_tools_text('result_tags_images_delete')
                        : mits_cdb_tools_text('result_tags_images_keep')
                );
                $resultBox = mits_cdb_tools_result(
                    'info',
                    mits_cdb_tools_text('result_tags_preview_title'),
                    $scope === 'all' ? mits_cdb_tools_text('result_tags_preview_all') : mits_cdb_tools_text('result_tags_preview_unused'),
                    $details
                );
            }
        } elseif ($activeTool === 'manufacturers') {
            $scope = (string)mits_cdb_tools_post('manufacturer_scope', 'unused');
            $scope = $scope === 'all' ? 'all' : 'unused';
            list($count, $rows) = mits_cdb_tools_manufacturer_preview($scope);
            if ($live) {
                mits_cdb_tools_require_live(mits_cdb_tools_text('confirm_delete'), true);
                list($deleted, $log) = mits_cdb_tools_delete_manufacturers($scope, (string)mits_cdb_tools_post('manufacturer_delete_images', '') === '1', (string)mits_cdb_tools_post('reset_ai', '') === '1');
                mits_cdb_tools_rotate_token();
                $resultBox = mits_cdb_tools_result(
                    'success',
                    mits_cdb_tools_text('result_manufacturers_title'),
                    mits_cdb_tools_format('result_manufacturers_message', $deleted),
                    $log
                );
            } else {
                $details = array();
                foreach ($rows as $row) {
                    $details[] = '#' . (int)$row['manufacturers_id'] . ' - ' . (string)$row['manufacturers_name'];
                }
                if ($scope === 'all') {
                    array_unshift($details, mits_cdb_tools_text('result_manufacturers_unlink'));
                }
                $resultBox = mits_cdb_tools_result(
                    'info',
                    mits_cdb_tools_text('result_manufacturers_preview_title'),
                    mits_cdb_tools_format('result_manufacturers_preview_message', $count),
                    $details
                );
            }
        } elseif ($activeTool === 'replace_content') {
            $search = (string)mits_cdb_tools_post('replace_search', '');
            $replacement = (string)mits_cdb_tools_post('replace_with', '');
            $languageId = max(0, mits_cdb_tools_int('replace_language_id', 0));
            $targets = mits_cdb_tools_replace_targets();
            list($count, $details) = mits_cdb_tools_replace_preview($targets, $search, $languageId);
            if ($live) {
                mits_cdb_tools_require_live(mits_cdb_tools_text('confirm_replace'), true);
                list($matched, $log) = mits_cdb_tools_execute_replace($targets, $search, $replacement, $languageId);
                mits_cdb_tools_rotate_token();
                $resultBox = mits_cdb_tools_result(
                    'success',
                    mits_cdb_tools_text('result_replace_title'),
                    mits_cdb_tools_format('result_replace_message', $matched),
                    $log
                );
            } else {
                $resultBox = mits_cdb_tools_result(
                    'info',
                    mits_cdb_tools_text('result_replace_preview_title'),
                    mits_cdb_tools_format('result_replace_preview_message', $count),
                    $details
                );
            }
        } elseif ($activeTool === 'swap_accounts') {
            $idA = mits_cdb_tools_int('swap_id_a', 1);
            $idB = mits_cdb_tools_int('swap_id_b', 2);
            $plan = mits_cdb_tools_swap_plan($idA, $idB);
            if ($live) {
                mits_cdb_tools_require_live(mits_cdb_tools_text('confirm_swap'), true);
                mits_cdb_tools_prepare_session_for_swap();
                $log = mits_cdb_tools_execute_swap($plan);
                $resultBox = mits_cdb_tools_result(
                    'success',
                    mits_cdb_tools_text('result_swap_title'),
                    mits_cdb_tools_text('result_swap_message'),
                    $log
                );
            } else {
                $details = array(mits_cdb_tools_format('result_swap_ai', $plan['auto_before']));
                foreach ($plan['final_map'] as $old => $new) {
                    $row = $plan['found'][$old];
                    $details[] = mits_cdb_tools_format(
                        'result_swap_mapping',
                        $old,
                        $new,
                        trim((string)$row['customers_firstname'] . ' ' . (string)$row['customers_lastname']),
                        (string)$row['customers_email_address']
                    );
                }
                $details[] = mits_cdb_tools_format('result_swap_refs', count($plan['columns']));
                foreach ($plan['columns'] as $column) {
                    $details[] = mits_cdb_tools_format('result_reference', $column['table'] . '.' . $column['column'] . ' (' . $column['type'] . ')');
                }
                $resultBox = mits_cdb_tools_result(
                    'info',
                    mits_cdb_tools_text('result_swap_preview_title'),
                    mits_cdb_tools_text('result_swap_preview_message'),
                    $details
                );
            }
        }
    }
} catch (Throwable $e) {
    $resultBox = mits_cdb_tools_result('danger', mits_cdb_tools_text('error_action_aborted'), $e->getMessage());
}

$ordersTable = mits_cdb_tools_table('TABLE_ORDERS', 'orders');
$orderAi = mits_cdb_tools_ai_info($ordersTable, 'orders_id');
$adminIds = array();
try {
    $adminIds = mits_cdb_tools_admin_ids();
} catch (Throwable $ignore) {}

$orderStatuses = array();
try {
    $ordersStatusTable = mits_cdb_tools_table('TABLE_ORDERS_STATUS', 'orders_status');
    $langId = isset($_SESSION['languages_id']) ? (int)$_SESSION['languages_id'] : 1;
    $statusResult = mits_cdb_tools_query('SELECT orders_status_id, orders_status_name FROM ' . mits_cdb_tools_qi($ordersStatusTable) . ' WHERE language_id = ' . $langId . ' ORDER BY orders_status_id');
    while ($statusRow = xtc_db_fetch_array($statusResult)) {
        $orderStatuses[(int)$statusRow['orders_status_id']] = (string)$statusRow['orders_status_name'];
    }
} catch (Throwable $ignore) {}

$toolLanguages = array();
try {
    $languagesTable = mits_cdb_tools_table('TABLE_LANGUAGES', 'languages');
    $languageResult = mits_cdb_tools_query('SELECT languages_id, name FROM ' . mits_cdb_tools_qi($languagesTable) . ' ORDER BY sort_order, languages_id');
    while ($languageRow = xtc_db_fetch_array($languageResult)) {
        $toolLanguages[(int)$languageRow['languages_id']] = (string)$languageRow['name'];
    }
} catch (Throwable $ignore) {}
$faqToolsAvailable = false;
$imageSliderToolsAvailable = false;
try { $faqToolsAvailable = mits_cdb_tools_faq_available(); } catch (Throwable $ignore) {}
try { $imageSliderToolsAvailable = mits_cdb_tools_imageslider_available(); } catch (Throwable $ignore) {}

require(DIR_WS_INCLUDES . 'head.php');
?>
<style>
.mits-tools{--p:#4f8e7e;--ps:#edf7f4;--line:#d4e7e0;--ink:#444;--head:#30534b;--muted:#6d7b77;--danger:#a3483f;--danger-bg:#fdeeed;--warning:#7a5a00;--warning-bg:#fff8e5;--success:#2c715d;--success-bg:#e9f7f1;padding:18px 18px 30px;color:var(--ink);font:13px/1.45 Arial,Helvetica,sans-serif}.mits-tools *{box-sizing:border-box}.mits-tools__hero{display:flex;justify-content:space-between;gap:18px;align-items:flex-start;padding:24px;border:1px solid var(--line);border-radius:20px;background:linear-gradient(135deg,#fff 0%,var(--ps) 100%);margin-bottom:18px}.mits-tools__hero h1{margin:0 0 7px;font-size:26px;color:var(--head)}.mits-tools__hero p{margin:0;color:var(--muted);max-width:850px}.mits-tools__actions{display:flex;gap:9px;flex-wrap:wrap}.mits-button,.mits-button:link,.mits-button:visited{display:inline-flex;align-items:center;justify-content:center;min-height:40px;padding:0 15px;border-radius:12px;border:1px solid #b9d6cb;background:#fff;color:var(--head)!important;text-decoration:none;font-weight:700;cursor:pointer}.mits-button:hover{background:var(--ps);text-decoration:none!important}.mits-button--primary{background:var(--p)!important;border-color:var(--p)!important;color:#fff!important}.mits-button--danger{background:var(--danger-bg)!important;border-color:#efc7c2!important;color:var(--danger)!important}.mits-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.mits-card{border:1px solid var(--line);border-radius:20px;background:#fff;overflow:hidden;box-shadow:0 8px 22px rgba(76,110,101,.08)}.mits-card>summary{cursor:pointer;list-style:none;padding:18px 22px;background:#f7fbfa;border-bottom:1px solid transparent;position:relative}.mits-card[open]>summary{border-bottom-color:var(--line)}.mits-card>summary::-webkit-details-marker{display:none}.mits-card>summary:after{content:'+';position:absolute;right:20px;top:18px;width:26px;height:26px;border-radius:50%;background:var(--ps);display:flex;align-items:center;justify-content:center;font-weight:800}.mits-card[open]>summary:after{content:'-'}.mits-card h2{margin:0 36px 5px 0;font-size:18px;color:var(--head)}.mits-card__sub{color:var(--muted);margin:0}.mits-card__body{padding:20px 22px}.mits-field{margin:0 0 13px}.mits-field label,.mits-label{display:block;margin-bottom:6px;font-weight:700;color:var(--head)}.mits-input,.mits-select{width:100%;padding:10px 12px;border:1px solid #b9d6cb;border-radius:12px;background:#fff;color:var(--ink)}.mits-row{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.mits-check{display:flex;align-items:flex-start;gap:8px;margin:9px 0;color:var(--head)}.mits-check input{margin-top:2px}.mits-live{margin-top:16px;padding-top:16px;border-top:1px dashed var(--line)}.mits-live .mits-input{max-width:220px;font-family:monospace;font-weight:700}.mits-note{padding:12px 14px;border-radius:14px;background:var(--ps);border:1px solid var(--line);margin:0 0 14px;color:var(--head)}.mits-note--warning{background:var(--warning-bg);color:var(--warning);border-color:#f0d28a}.mits-note--danger{background:var(--danger-bg);color:var(--danger);border-color:#efc7c2}.mits-result{margin-bottom:18px;padding:18px 20px;border-radius:18px;border:1px solid var(--line);background:#fff}.mits-result--info{background:var(--ps)}.mits-result--success{background:var(--success-bg);color:var(--success)}.mits-result--danger{background:var(--danger-bg);color:var(--danger)}.mits-result h2{margin:0 0 7px;color:inherit;font-size:18px}.mits-result p{margin:0}.mits-result ul{margin:10px 0 0;padding-left:20px;max-height:280px;overflow:auto}.mits-stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px;margin-bottom:18px}.mits-stat{padding:15px;border:1px solid var(--line);border-radius:16px;background:#fff}.mits-stat strong{display:block;font-size:22px;color:var(--head)}.mits-stat span{color:var(--muted);font-size:12px}.mits-expert{grid-column:1/-1}.mits-small{font-size:12px;color:var(--muted)}@media(max-width:1000px){.mits-grid{grid-template-columns:1fr}.mits-tools__hero{display:block}.mits-tools__actions{margin-top:14px}.mits-expert{grid-column:auto}}@media(max-width:640px){.mits-tools{padding:12px}.mits-row,.mits-stats{grid-template-columns:1fr}.mits-card__body,.mits-card>summary,.mits-tools__hero{padding:16px}}
</style>
</head>
<body>

<!-- header //-->
<?php require(DIR_WS_INCLUDES . 'header.php'); ?>
<!-- header_eof //-->

<div class="mits-tools">
  <div class="mits-tools__hero">
    <div>
      <h1><?php echo mits_cdb_tools_text('page_title'); ?></h1>
      <p><?php echo mits_cdb_tools_text('intro'); ?></p>
    </div>
    <div class="mits-tools__actions">
      <a class="mits-button" href="<?php echo xtc_href_link('mits_cron_database_restore.php'); ?>"><?php echo mits_cdb_tools_text('backups'); ?></a>
      <a class="mits-button" href="<?php echo xtc_href_link('mits_cron_database_tools.php'); ?>"><?php echo mits_cdb_tools_text('refresh'); ?></a>
    </div>
  </div>

  <div class="mits-stats">
    <div class="mits-stat"><span><?php echo mits_cdb_tools_text('stat_logged_admin'); ?></span><strong>#<?php echo (int)$currentAdminId; ?></strong><span>customers_status = 0</span></div>
    <div class="mits-stat"><span><?php echo mits_cdb_tools_text('stat_protected_admins'); ?></span><strong><?php echo count($adminIds); ?></strong><span><?php echo mits_cdb_tools_html(implode(', ', $adminIds)); ?></span></div>
    <div class="mits-stat"><span><?php echo mits_cdb_tools_text('stat_next_order_id'); ?></span><strong><?php echo (int)$orderAi['current']; ?></strong><span><?php echo mits_cdb_tools_format('stat_highest_existing_id', (int)$orderAi['max']); ?></span></div>
  </div>

  <div class="mits-note mits-note--warning"><strong><?php echo mits_cdb_tools_text('warning_title'); ?></strong> <?php echo mits_cdb_tools_text('warning_live'); ?></div>

  <?php if ($resultBox !== null) { ?>
    <div class="mits-result mits-result--<?php echo mits_cdb_tools_html($resultBox['type']); ?>">
      <h2><?php echo mits_cdb_tools_html(html_entity_decode($resultBox['title'], ENT_QUOTES | ENT_HTML5, mits_cdb_tools_charset())); ?></h2>
      <p><?php echo nl2br(mits_cdb_tools_html(html_entity_decode($resultBox['message'], ENT_QUOTES | ENT_HTML5, mits_cdb_tools_charset()))); ?></p>
      <?php if (!empty($resultBox['details'])) { ?><ul><?php foreach ($resultBox['details'] as $detail) { ?><li><?php echo mits_cdb_tools_html(html_entity_decode((string)$detail, ENT_QUOTES | ENT_HTML5, mits_cdb_tools_charset())); ?></li><?php } ?></ul><?php } ?>
    </div>
  <?php } ?>

  <div class="mits-grid">
    <details class="mits-card"<?php echo $activeTool === 'order_ai' ? ' open' : ''; ?>>
      <summary><h2><?php echo mits_cdb_tools_text('order_title'); ?></h2><p class="mits-card__sub"><?php echo mits_cdb_tools_text('order_sub'); ?></p></summary>
      <div class="mits-card__body">
        <div class="mits-note"><?php echo mits_cdb_tools_format('order_note', (int)$orderAi['current'], (int)$orderAi['max'], (int)$orderAi['minimum']); ?></div>
        <?php echo xtc_draw_form('mits_order_ai', 'mits_cron_database_tools.php', '', 'post'); ?>
          <?php echo mits_cdb_tools_hidden('order_ai'); ?>
          <div class="mits-field"><label for="order_next_value"><?php echo mits_cdb_tools_text('order_next_id'); ?></label><input class="mits-input" id="order_next_value" type="number" min="1" name="order_next_value" value="<?php echo (int)($activeTool === 'order_ai' ? mits_cdb_tools_int('order_next_value', $orderAi['minimum']) : $orderAi['minimum']); ?>"></div>
          <div class="mits-tools__actions"><button class="mits-button" type="submit" name="run_mode" value="preview"><?php echo mits_cdb_tools_text('common_preview'); ?></button></div>
          <div class="mits-live"><div class="mits-field"><label><?php echo mits_cdb_tools_text('common_confirm_exact'); ?> <code><?php echo mits_cdb_tools_html(mits_cdb_tools_text('confirm_set')); ?></code></label><input class="mits-input" type="text" name="confirm_text" autocomplete="off"></div><button class="mits-button mits-button--primary" type="submit" name="run_mode" value="live" data-confirm="<?php echo mits_cdb_tools_html(mits_cdb_tools_text('order_confirm')); ?>" onclick="return confirm(this.getAttribute('data-confirm')); "><?php echo mits_cdb_tools_text('order_set_value'); ?></button></div>
        </form>
      </div>
    </details>

    <details class="mits-card"<?php echo $activeTool === 'customers' ? ' open' : ''; ?>>
      <summary><h2><?php echo mits_cdb_tools_text('customers_title'); ?></h2><p class="mits-card__sub"><?php echo mits_cdb_tools_text('customers_sub'); ?></p></summary>
      <div class="mits-card__body">
        <div class="mits-note"><?php echo mits_cdb_tools_text('customers_note'); ?></div>
        <?php echo xtc_draw_form('mits_customers', 'mits_cron_database_tools.php', '', 'post'); ?><?php echo mits_cdb_tools_hidden('customers'); ?>
          <div class="mits-field"><label><?php echo mits_cdb_tools_text('common_selection'); ?></label><select class="mits-select" name="customer_mode">
            <?php $cm = $activeTool === 'customers' ? (string)mits_cdb_tools_post('customer_mode','all_non_admin') : 'all_non_admin'; ?>
            <option value="all_non_admin"<?php echo $cm==='all_non_admin'?' selected':''; ?>><?php echo mits_cdb_tools_text('customers_mode_all'); ?></option>
            <option value="guests"<?php echo $cm==='guests'?' selected':''; ?>><?php echo mits_cdb_tools_text('customers_mode_guests'); ?></option>
            <option value="inactive"<?php echo $cm==='inactive'?' selected':''; ?>><?php echo mits_cdb_tools_text('customers_mode_inactive'); ?></option>
            <option value="never_logged_in"<?php echo $cm==='never_logged_in'?' selected':''; ?>><?php echo mits_cdb_tools_text('customers_mode_never'); ?></option>
            <option value="ids"<?php echo $cm==='ids'?' selected':''; ?>><?php echo mits_cdb_tools_text('customers_mode_ids'); ?></option>
          </select></div>
          <div class="mits-row"><div class="mits-field"><label><?php echo mits_cdb_tools_text('common_days'); ?></label><input class="mits-input" type="number" min="1" name="customer_days" value="<?php echo (int)($activeTool==='customers'?mits_cdb_tools_int('customer_days',1095):1095); ?>"></div><div class="mits-field"><label><?php echo mits_cdb_tools_text('customers_ids'); ?></label><input class="mits-input" type="text" name="customer_ids" value="<?php echo mits_cdb_tools_html($activeTool==='customers'?mits_cdb_tools_post('customer_ids',''):''); ?>" placeholder="<?php echo mits_cdb_tools_html(mits_cdb_tools_text('placeholder_ids')); ?>"></div></div>
          <div class="mits-row"><div class="mits-field"><label><?php echo mits_cdb_tools_text('customers_keep_ids'); ?></label><input class="mits-input" type="text" name="customer_keep_ids" value="<?php echo mits_cdb_tools_html($activeTool==='customers'?mits_cdb_tools_post('customer_keep_ids',''):''); ?>" placeholder="<?php echo mits_cdb_tools_html(mits_cdb_tools_text('placeholder_keep_ids')); ?>"></div><div class="mits-field"><label><?php echo mits_cdb_tools_text('customers_email_contains'); ?></label><input class="mits-input" type="text" name="customer_email" value="<?php echo mits_cdb_tools_html($activeTool==='customers'?mits_cdb_tools_post('customer_email',''):''); ?>"></div></div>
          <label class="mits-check"><input type="checkbox" name="customer_delete_reviews" value="1"<?php echo ($activeTool==='customers' && (string)mits_cdb_tools_post('customer_delete_reviews','')==='1')?' checked':''; ?>> <?php echo mits_cdb_tools_text('customers_delete_reviews'); ?></label>
          <label class="mits-check"><input type="checkbox" name="customer_delete_orders" value="1"<?php echo ($activeTool==='customers' && (string)mits_cdb_tools_post('customer_delete_orders','')==='1')?' checked':''; ?>> <?php echo mits_cdb_tools_text('customers_delete_orders'); ?></label>
          <label class="mits-check"><input type="checkbox" name="reset_ai" value="1"<?php echo ($activeTool==='customers' && (string)mits_cdb_tools_post('reset_ai','')==='1')?' checked':''; ?>> <?php echo mits_cdb_tools_text('customers_reset_ai'); ?></label>
          <div class="mits-tools__actions"><button class="mits-button" type="submit" name="run_mode" value="preview"><?php echo mits_cdb_tools_text('common_preview'); ?></button></div>
          <div class="mits-live"><label class="mits-check"><input type="checkbox" name="backup_confirmed" value="1"> <?php echo mits_cdb_tools_text('common_backup_confirmed'); ?></label><div class="mits-field"><label><?php echo mits_cdb_tools_text('common_confirm_exact'); ?> <code><?php echo mits_cdb_tools_html(mits_cdb_tools_text('confirm_delete')); ?></code></label><input class="mits-input" type="text" name="confirm_text" autocomplete="off"></div><button class="mits-button mits-button--danger" type="submit" name="run_mode" value="live" data-confirm="<?php echo mits_cdb_tools_html(mits_cdb_tools_text('customers_confirm')); ?>" onclick="return confirm(this.getAttribute('data-confirm')); "><?php echo mits_cdb_tools_text('customers_delete'); ?></button></div>
        </form>
      </div>
    </details>

    <details class="mits-card"<?php echo $activeTool === 'orders' ? ' open' : ''; ?>>
      <summary><h2><?php echo mits_cdb_tools_text('orders_title'); ?></h2><p class="mits-card__sub"><?php echo mits_cdb_tools_text('orders_sub'); ?></p></summary>
      <div class="mits-card__body">
        <?php echo xtc_draw_form('mits_orders', 'mits_cron_database_tools.php', '', 'post'); ?><?php echo mits_cdb_tools_hidden('orders'); ?>
          <?php $os = $activeTool==='orders'?(string)mits_cdb_tools_post('order_scope','filtered'):'filtered'; ?>
          <div class="mits-field"><label><?php echo mits_cdb_tools_text('common_scope'); ?></label><select class="mits-select" name="order_scope"><option value="filtered"<?php echo $os==='filtered'?' selected':''; ?>><?php echo mits_cdb_tools_text('orders_scope_filtered'); ?></option><option value="all"<?php echo $os==='all'?' selected':''; ?>><?php echo mits_cdb_tools_text('orders_scope_all'); ?></option></select></div>
          <div class="mits-row"><div class="mits-field"><label><?php echo mits_cdb_tools_text('orders_id_from'); ?></label><input class="mits-input" type="number" min="1" name="order_id_from" value="<?php echo (int)($activeTool==='orders'?mits_cdb_tools_int('order_id_from',0):0); ?>"></div><div class="mits-field"><label><?php echo mits_cdb_tools_text('orders_id_to'); ?></label><input class="mits-input" type="number" min="1" name="order_id_to" value="<?php echo (int)($activeTool==='orders'?mits_cdb_tools_int('order_id_to',0):0); ?>"></div></div>
          <div class="mits-row"><div class="mits-field"><label><?php echo mits_cdb_tools_text('orders_before_date'); ?></label><input class="mits-input" type="date" name="order_date_before" value="<?php echo mits_cdb_tools_html($activeTool==='orders'?mits_cdb_tools_post('order_date_before',''):''); ?>"></div><div class="mits-field"><label><?php echo mits_cdb_tools_text('orders_customer_id'); ?></label><input class="mits-input" type="number" min="1" name="order_customer_id" value="<?php echo (int)($activeTool==='orders'?mits_cdb_tools_int('order_customer_id',0):0); ?>"></div></div>
          <div class="mits-field"><label><?php echo mits_cdb_tools_text('orders_status'); ?></label><select class="mits-select" name="order_status"><option value="0"><?php echo mits_cdb_tools_text('orders_all_status'); ?></option><?php $selStatus=$activeTool==='orders'?mits_cdb_tools_int('order_status',0):0; foreach($orderStatuses as $sid=>$sname){ ?><option value="<?php echo (int)$sid; ?>"<?php echo $selStatus===$sid?' selected':''; ?>><?php echo mits_cdb_tools_html($sname); ?> (#<?php echo (int)$sid; ?>)</option><?php } ?></select></div>
          <label class="mits-check"><input type="checkbox" name="reset_ai" value="1"<?php echo ($activeTool==='orders' && (string)mits_cdb_tools_post('reset_ai','')==='1')?' checked':''; ?>> <?php echo mits_cdb_tools_text('orders_reset_ai'); ?></label>
          <div class="mits-tools__actions"><button class="mits-button" type="submit" name="run_mode" value="preview"><?php echo mits_cdb_tools_text('common_preview'); ?></button></div>
          <div class="mits-live"><label class="mits-check"><input type="checkbox" name="backup_confirmed" value="1"> <?php echo mits_cdb_tools_text('common_backup_confirmed'); ?></label><div class="mits-field"><label><?php echo mits_cdb_tools_text('common_confirm_exact'); ?> <code><?php echo mits_cdb_tools_html(mits_cdb_tools_text('confirm_delete')); ?></code></label><input class="mits-input" type="text" name="confirm_text" autocomplete="off"></div><button class="mits-button mits-button--danger" type="submit" name="run_mode" value="live" data-confirm="<?php echo mits_cdb_tools_html(mits_cdb_tools_text('orders_confirm')); ?>" onclick="return confirm(this.getAttribute('data-confirm')); "><?php echo mits_cdb_tools_text('orders_delete'); ?></button></div>
        </form>
      </div>
    </details>

    <details class="mits-card"<?php echo $activeTool === 'reviews' ? ' open' : ''; ?>>
      <summary><h2><?php echo mits_cdb_tools_text('reviews_title'); ?></h2><p class="mits-card__sub"><?php echo mits_cdb_tools_text('reviews_sub'); ?></p></summary>
      <div class="mits-card__body">
        <?php echo xtc_draw_form('mits_reviews', 'mits_cron_database_tools.php', '', 'post'); ?><?php echo mits_cdb_tools_hidden('reviews'); ?>
          <?php $rm=$activeTool==='reviews'?(string)mits_cdb_tools_post('review_mode','all'):'all'; ?>
          <div class="mits-field"><label><?php echo mits_cdb_tools_text('common_selection'); ?></label><select class="mits-select" name="review_mode"><option value="all"<?php echo $rm==='all'?' selected':''; ?>><?php echo mits_cdb_tools_text('reviews_mode_all'); ?></option><option value="guests"<?php echo $rm==='guests'?' selected':''; ?>><?php echo mits_cdb_tools_text('reviews_mode_guests'); ?></option><option value="products"<?php echo $rm==='products'?' selected':''; ?>><?php echo mits_cdb_tools_text('reviews_mode_products'); ?></option></select></div>
          <div class="mits-field"><label><?php echo mits_cdb_tools_text('reviews_product_ids'); ?></label><input class="mits-input" type="text" name="review_product_ids" value="<?php echo mits_cdb_tools_html($activeTool==='reviews'?mits_cdb_tools_post('review_product_ids',''):''); ?>" placeholder="<?php echo mits_cdb_tools_html(mits_cdb_tools_text('placeholder_product_ids')); ?>"></div>
          <label class="mits-check"><input type="checkbox" name="reset_ai" value="1"<?php echo ($activeTool==='reviews' && (string)mits_cdb_tools_post('reset_ai','')==='1')?' checked':''; ?>> <?php echo mits_cdb_tools_text('common_reset_ai'); ?></label>
          <div class="mits-tools__actions"><button class="mits-button" type="submit" name="run_mode" value="preview"><?php echo mits_cdb_tools_text('common_preview'); ?></button></div>
          <div class="mits-live"><label class="mits-check"><input type="checkbox" name="backup_confirmed" value="1"> <?php echo mits_cdb_tools_text('common_backup_confirmed'); ?></label><div class="mits-field"><label><?php echo mits_cdb_tools_text('common_confirm_exact'); ?> <code><?php echo mits_cdb_tools_html(mits_cdb_tools_text('confirm_delete')); ?></code></label><input class="mits-input" type="text" name="confirm_text" autocomplete="off"></div><button class="mits-button mits-button--danger" type="submit" name="run_mode" value="live" data-confirm="<?php echo mits_cdb_tools_html(mits_cdb_tools_text('reviews_confirm')); ?>" onclick="return confirm(this.getAttribute('data-confirm')); "><?php echo mits_cdb_tools_text('reviews_delete'); ?></button></div>
        </form>
      </div>
    </details>

    <details class="mits-card"<?php echo $activeTool === 'products' ? ' open' : ''; ?>>
      <summary><h2><?php echo mits_cdb_tools_text('products_title'); ?></h2><p class="mits-card__sub"><?php echo mits_cdb_tools_text('products_sub'); ?></p></summary>
      <div class="mits-card__body">
        <div class="mits-note"><?php echo mits_cdb_tools_text('products_note'); ?></div>
        <?php echo xtc_draw_form('mits_products', 'mits_cron_database_tools.php', '', 'post'); ?><?php echo mits_cdb_tools_hidden('products'); ?>
          <?php $ps=$activeTool==='products'?(string)mits_cdb_tools_post('product_scope','filtered'):'filtered'; ?>
          <div class="mits-field"><label><?php echo mits_cdb_tools_text('common_scope'); ?></label><select class="mits-select" name="product_scope"><option value="filtered"<?php echo $ps==='filtered'?' selected':''; ?>><?php echo mits_cdb_tools_text('products_scope_filtered'); ?></option><option value="all"<?php echo $ps==='all'?' selected':''; ?>><?php echo mits_cdb_tools_text('products_scope_all'); ?></option></select></div>
          <div class="mits-row"><div class="mits-field"><label><?php echo mits_cdb_tools_text('common_status'); ?></label><?php $pstat=$activeTool==='products'?(string)mits_cdb_tools_post('product_status',''):''; ?><select class="mits-select" name="product_status"><option value=""><?php echo mits_cdb_tools_text('common_all'); ?></option><option value="0"<?php echo $pstat==='0'?' selected':''; ?>><?php echo mits_cdb_tools_text('products_status_inactive'); ?></option><option value="1"<?php echo $pstat==='1'?' selected':''; ?>><?php echo mits_cdb_tools_text('products_status_active'); ?></option></select></div><div class="mits-field"><label><?php echo mits_cdb_tools_text('products_ids'); ?></label><input class="mits-input" type="text" name="product_ids" value="<?php echo mits_cdb_tools_html($activeTool==='products'?mits_cdb_tools_post('product_ids',''):''); ?>"></div></div>
          <div class="mits-row"><div class="mits-field"><label><?php echo mits_cdb_tools_text('products_category_ids'); ?></label><input class="mits-input" type="text" name="product_category_ids" value="<?php echo mits_cdb_tools_html($activeTool==='products'?mits_cdb_tools_post('product_category_ids',''):''); ?>"></div><div class="mits-field"><label><?php echo mits_cdb_tools_text('products_before_date'); ?></label><input class="mits-input" type="date" name="product_date_before" value="<?php echo mits_cdb_tools_html($activeTool==='products'?mits_cdb_tools_post('product_date_before',''):''); ?>"></div></div>
          <label class="mits-check"><input type="checkbox" name="product_without_category" value="1"<?php echo ($activeTool==='products' && (string)mits_cdb_tools_post('product_without_category','')==='1')?' checked':''; ?>> <?php echo mits_cdb_tools_text('products_without_category'); ?></label>
          <label class="mits-check"><input type="checkbox" name="product_masterdata" value="1"<?php echo ($activeTool==='products' && (string)mits_cdb_tools_post('product_masterdata','')==='1')?' checked':''; ?>> <?php echo mits_cdb_tools_text('products_clear_masterdata'); ?></label>
          <label class="mits-check"><input type="checkbox" name="reset_ai" value="1"<?php echo ($activeTool==='products' && (string)mits_cdb_tools_post('reset_ai','')==='1')?' checked':''; ?>> <?php echo mits_cdb_tools_text('products_reset_ai'); ?></label>
          <div class="mits-tools__actions"><button class="mits-button" type="submit" name="run_mode" value="preview"><?php echo mits_cdb_tools_text('common_preview'); ?></button></div>
          <div class="mits-live"><label class="mits-check"><input type="checkbox" name="backup_confirmed" value="1"> <?php echo mits_cdb_tools_text('common_backup_confirmed'); ?></label><div class="mits-field"><label><?php echo mits_cdb_tools_text('common_confirm_exact'); ?> <code><?php echo mits_cdb_tools_html(mits_cdb_tools_text('confirm_delete')); ?></code></label><input class="mits-input" type="text" name="confirm_text" autocomplete="off"></div><button class="mits-button mits-button--danger" type="submit" name="run_mode" value="live" data-confirm="<?php echo mits_cdb_tools_html(mits_cdb_tools_text('products_confirm')); ?>" onclick="return confirm(this.getAttribute('data-confirm')); "><?php echo mits_cdb_tools_text('products_delete'); ?></button></div>
        </form>
      </div>
    </details>

    <details class="mits-card"<?php echo $activeTool === 'categories' ? ' open' : ''; ?>>
      <summary><h2><?php echo mits_cdb_tools_text('categories_title'); ?></h2><p class="mits-card__sub"><?php echo mits_cdb_tools_text('categories_sub'); ?></p></summary>
      <div class="mits-card__body">
        <div class="mits-note"><?php echo mits_cdb_tools_text('categories_note'); ?></div>
        <?php echo xtc_draw_form('mits_categories', 'mits_cron_database_tools.php', '', 'post'); ?><?php echo mits_cdb_tools_hidden('categories'); ?>
          <?php $cs=$activeTool==='categories'?(string)mits_cdb_tools_post('category_scope','ids'):'ids'; ?>
          <div class="mits-field"><label><?php echo mits_cdb_tools_text('common_scope'); ?></label><select class="mits-select" name="category_scope"><option value="ids"<?php echo $cs==='ids'?' selected':''; ?>><?php echo mits_cdb_tools_text('categories_scope_ids'); ?></option><option value="all"<?php echo $cs==='all'?' selected':''; ?>><?php echo mits_cdb_tools_text('categories_scope_all'); ?></option></select></div>
          <div class="mits-field"><label><?php echo mits_cdb_tools_text('categories_ids'); ?></label><input class="mits-input" type="text" name="category_ids" value="<?php echo mits_cdb_tools_html($activeTool==='categories'?mits_cdb_tools_post('category_ids',''):''); ?>" placeholder="<?php echo mits_cdb_tools_html(mits_cdb_tools_text('placeholder_category_ids')); ?>"></div>
          <label class="mits-check"><input type="checkbox" name="category_children" value="1"<?php echo ($activeTool==='categories' && (string)mits_cdb_tools_post('category_children','')==='1')?' checked':''; ?>> <?php echo mits_cdb_tools_text('categories_children'); ?></label>
          <label class="mits-check"><input type="checkbox" name="category_delete_orphans" value="1"<?php echo ($activeTool==='categories' && (string)mits_cdb_tools_post('category_delete_orphans','')==='1')?' checked':''; ?>> <?php echo mits_cdb_tools_text('categories_delete_orphans'); ?></label>
          <label class="mits-check"><input type="checkbox" name="reset_ai" value="1"<?php echo ($activeTool==='categories' && (string)mits_cdb_tools_post('reset_ai','')==='1')?' checked':''; ?>> <?php echo mits_cdb_tools_text('categories_reset_ai'); ?></label>
          <div class="mits-tools__actions"><button class="mits-button" type="submit" name="run_mode" value="preview"><?php echo mits_cdb_tools_text('common_preview'); ?></button></div>
          <div class="mits-live"><label class="mits-check"><input type="checkbox" name="backup_confirmed" value="1"> <?php echo mits_cdb_tools_text('common_backup_confirmed'); ?></label><div class="mits-field"><label><?php echo mits_cdb_tools_text('common_confirm_exact'); ?> <code><?php echo mits_cdb_tools_html(mits_cdb_tools_text('confirm_delete')); ?></code></label><input class="mits-input" type="text" name="confirm_text" autocomplete="off"></div><button class="mits-button mits-button--danger" type="submit" name="run_mode" value="live" data-confirm="<?php echo mits_cdb_tools_html(mits_cdb_tools_text('categories_confirm')); ?>" onclick="return confirm(this.getAttribute('data-confirm')); "><?php echo mits_cdb_tools_text('categories_delete'); ?></button></div>
        </form>
      </div>
    </details>

    <details class="mits-card"<?php echo $activeTool === 'options' ? ' open' : ''; ?>>
      <summary><h2><?php echo mits_cdb_tools_text('options_title'); ?></h2><p class="mits-card__sub"><?php echo mits_cdb_tools_text('options_sub'); ?></p></summary>
      <div class="mits-card__body">
        <div class="mits-note"><?php echo mits_cdb_tools_text('options_note'); ?></div>
        <?php echo xtc_draw_form('mits_options', 'mits_cron_database_tools.php', '', 'post'); ?><?php echo mits_cdb_tools_hidden('options'); ?>
          <?php $ops=$activeTool==='options'?(string)mits_cdb_tools_post('options_scope','unused'):'unused'; ?>
          <div class="mits-field"><label><?php echo mits_cdb_tools_text('common_scope'); ?></label><select class="mits-select" name="options_scope"><option value="unused"<?php echo $ops==='unused'?' selected':''; ?>><?php echo mits_cdb_tools_text('options_scope_unused'); ?></option><option value="all"<?php echo $ops==='all'?' selected':''; ?>><?php echo mits_cdb_tools_text('options_scope_all'); ?></option></select></div>
          <label class="mits-check"><input type="checkbox" name="reset_ai" value="1"<?php echo ($activeTool==='options' && (string)mits_cdb_tools_post('reset_ai','')==='1')?' checked':''; ?>> <?php echo mits_cdb_tools_text('options_reset_ai'); ?></label>
          <div class="mits-tools__actions"><button class="mits-button" type="submit" name="run_mode" value="preview"><?php echo mits_cdb_tools_text('common_preview'); ?></button></div>
          <div class="mits-live"><label class="mits-check"><input type="checkbox" name="backup_confirmed" value="1"> <?php echo mits_cdb_tools_text('common_backup_confirmed'); ?></label><div class="mits-field"><label><?php echo mits_cdb_tools_text('common_confirm_exact'); ?> <code><?php echo mits_cdb_tools_html(mits_cdb_tools_text('confirm_delete')); ?></code></label><input class="mits-input" type="text" name="confirm_text" autocomplete="off"></div><button class="mits-button mits-button--danger" type="submit" name="run_mode" value="live" data-confirm="<?php echo mits_cdb_tools_html(mits_cdb_tools_text('options_confirm')); ?>" onclick="return confirm(this.getAttribute('data-confirm')); "><?php echo mits_cdb_tools_text('options_delete'); ?></button></div>
        </form>
      </div>
    </details>

    <details class="mits-card"<?php echo $activeTool === 'tags' ? ' open' : ''; ?>>
      <summary><h2><?php echo mits_cdb_tools_text('tags_title'); ?></h2><p class="mits-card__sub"><?php echo mits_cdb_tools_text('tags_sub'); ?></p></summary>
      <div class="mits-card__body">
        <div class="mits-note"><?php echo mits_cdb_tools_text('tags_note'); ?></div>
        <?php echo xtc_draw_form('mits_tags', 'mits_cron_database_tools.php', '', 'post'); ?><?php echo mits_cdb_tools_hidden('tags'); ?>
          <?php $tgs=$activeTool==='tags'?(string)mits_cdb_tools_post('tags_scope','unused'):'unused'; ?>
          <div class="mits-field"><label><?php echo mits_cdb_tools_text('common_scope'); ?></label><select class="mits-select" name="tags_scope"><option value="unused"<?php echo $tgs==='unused'?' selected':''; ?>><?php echo mits_cdb_tools_text('tags_scope_unused'); ?></option><option value="all"<?php echo $tgs==='all'?' selected':''; ?>><?php echo mits_cdb_tools_text('tags_scope_all'); ?></option></select></div>
          <label class="mits-check"><input type="checkbox" name="tags_delete_images" value="1"<?php echo ($activeTool==='tags' && (string)mits_cdb_tools_post('tags_delete_images','')==='1')?' checked':''; ?>> <?php echo mits_cdb_tools_text('tags_delete_images'); ?></label>
          <label class="mits-check"><input type="checkbox" name="reset_ai" value="1"<?php echo ($activeTool==='tags' && (string)mits_cdb_tools_post('reset_ai','')==='1')?' checked':''; ?>> <?php echo mits_cdb_tools_text('tags_reset_ai'); ?></label>
          <div class="mits-tools__actions"><button class="mits-button" type="submit" name="run_mode" value="preview"><?php echo mits_cdb_tools_text('common_preview'); ?></button></div>
          <div class="mits-live"><label class="mits-check"><input type="checkbox" name="backup_confirmed" value="1"> <?php echo mits_cdb_tools_text('common_backup_confirmed'); ?></label><div class="mits-field"><label><?php echo mits_cdb_tools_text('common_confirm_exact'); ?> <code><?php echo mits_cdb_tools_html(mits_cdb_tools_text('confirm_delete')); ?></code></label><input class="mits-input" type="text" name="confirm_text" autocomplete="off"></div><button class="mits-button mits-button--danger" type="submit" name="run_mode" value="live" data-confirm="<?php echo mits_cdb_tools_html(mits_cdb_tools_text('tags_confirm')); ?>" onclick="return confirm(this.getAttribute('data-confirm')); "><?php echo mits_cdb_tools_text('tags_delete'); ?></button></div>
        </form>
      </div>
    </details>

    <details class="mits-card"<?php echo $activeTool === 'manufacturers' ? ' open' : ''; ?>>
      <summary><h2><?php echo mits_cdb_tools_text('manufacturers_title'); ?></h2><p class="mits-card__sub"><?php echo mits_cdb_tools_text('manufacturers_sub'); ?></p></summary>
      <div class="mits-card__body">
        <div class="mits-note"><?php echo mits_cdb_tools_text('manufacturers_note'); ?></div>
        <?php echo xtc_draw_form('mits_manufacturers', 'mits_cron_database_tools.php', '', 'post'); ?><?php echo mits_cdb_tools_hidden('manufacturers'); ?>
          <?php $mfs=$activeTool==='manufacturers'?(string)mits_cdb_tools_post('manufacturer_scope','unused'):'unused'; ?>
          <div class="mits-field"><label><?php echo mits_cdb_tools_text('common_scope'); ?></label><select class="mits-select" name="manufacturer_scope"><option value="unused"<?php echo $mfs==='unused'?' selected':''; ?>><?php echo mits_cdb_tools_text('manufacturers_scope_unused'); ?></option><option value="all"<?php echo $mfs==='all'?' selected':''; ?>><?php echo mits_cdb_tools_text('manufacturers_scope_all'); ?></option></select></div>
          <label class="mits-check"><input type="checkbox" name="manufacturer_delete_images" value="1"<?php echo ($activeTool==='manufacturers' && (string)mits_cdb_tools_post('manufacturer_delete_images','')==='1')?' checked':''; ?>> <?php echo mits_cdb_tools_text('manufacturers_delete_images'); ?></label>
          <label class="mits-check"><input type="checkbox" name="reset_ai" value="1"<?php echo ($activeTool==='manufacturers' && (string)mits_cdb_tools_post('reset_ai','')==='1')?' checked':''; ?>> <?php echo mits_cdb_tools_text('manufacturers_reset_ai'); ?></label>
          <div class="mits-tools__actions"><button class="mits-button" type="submit" name="run_mode" value="preview"><?php echo mits_cdb_tools_text('common_preview'); ?></button></div>
          <div class="mits-live"><label class="mits-check"><input type="checkbox" name="backup_confirmed" value="1"> <?php echo mits_cdb_tools_text('common_backup_confirmed'); ?></label><div class="mits-field"><label><?php echo mits_cdb_tools_text('common_confirm_exact'); ?> <code><?php echo mits_cdb_tools_html(mits_cdb_tools_text('confirm_delete')); ?></code></label><input class="mits-input" type="text" name="confirm_text" autocomplete="off"></div><button class="mits-button mits-button--danger" type="submit" name="run_mode" value="live" data-confirm="<?php echo mits_cdb_tools_html(mits_cdb_tools_text('manufacturers_confirm')); ?>" onclick="return confirm(this.getAttribute('data-confirm')); "><?php echo mits_cdb_tools_text('manufacturers_delete'); ?></button></div>
        </form>
      </div>
    </details>

    <details class="mits-card mits-expert"<?php echo $activeTool === 'replace_content' ? ' open' : ''; ?>>
      <summary><h2><?php echo mits_cdb_tools_text('replace_title'); ?></h2><p class="mits-card__sub"><?php echo mits_cdb_tools_text('replace_sub'); ?></p></summary>
      <div class="mits-card__body">
        <div class="mits-note"><?php echo mits_cdb_tools_text('replace_note'); ?></div>
        <?php echo xtc_draw_form('mits_replace_content', 'mits_cron_database_tools.php', '', 'post'); ?><?php echo mits_cdb_tools_hidden('replace_content'); ?>
          <div class="mits-row"><div class="mits-field"><label><?php echo mits_cdb_tools_text('replace_search'); ?></label><input class="mits-input" type="text" name="replace_search" required value="<?php echo mits_cdb_tools_html($activeTool==='replace_content'?mits_cdb_tools_post('replace_search',''):''); ?>" placeholder="<?php echo mits_cdb_tools_html(mits_cdb_tools_text('placeholder_search_domain')); ?>"></div><div class="mits-field"><label><?php echo mits_cdb_tools_text('replace_with'); ?></label><input class="mits-input" type="text" name="replace_with" value="<?php echo mits_cdb_tools_html($activeTool==='replace_content'?mits_cdb_tools_post('replace_with',''):''); ?>" placeholder="<?php echo mits_cdb_tools_html(mits_cdb_tools_text('placeholder_replace_domain')); ?>"></div></div>
          <div class="mits-field"><label><?php echo mits_cdb_tools_text('common_language'); ?></label><select class="mits-select" name="replace_language_id"><option value="0"><?php echo mits_cdb_tools_text('common_all_languages'); ?></option><?php $rLang=$activeTool==='replace_content'?mits_cdb_tools_int('replace_language_id',0):0; foreach($toolLanguages as $lid=>$lname){ ?><option value="<?php echo (int)$lid; ?>"<?php echo $rLang===$lid?' selected':''; ?>><?php echo mits_cdb_tools_html($lname); ?></option><?php } ?></select></div>
          <?php $replaceDefault = $activeTool !== 'replace_content'; ?>
          <label class="mits-check"><input type="checkbox" name="replace_categories" value="1"<?php echo ($replaceDefault || (string)mits_cdb_tools_post('replace_categories','')==='1')?' checked':''; ?>> <?php echo mits_cdb_tools_text('replace_categories'); ?></label>
          <label class="mits-check"><input type="checkbox" name="replace_products" value="1"<?php echo ($replaceDefault || (string)mits_cdb_tools_post('replace_products','')==='1')?' checked':''; ?>> <?php echo mits_cdb_tools_text('replace_products'); ?></label>
          <label class="mits-check"><input type="checkbox" name="replace_content" value="1"<?php echo ($replaceDefault || (string)mits_cdb_tools_post('replace_content','')==='1')?' checked':''; ?>> <?php echo mits_cdb_tools_text('replace_content'); ?></label>
          <?php if ($faqToolsAvailable) { ?><label class="mits-check"><input type="checkbox" name="replace_faq" value="1"<?php echo ($activeTool==='replace_content' && (string)mits_cdb_tools_post('replace_faq','')==='1')?' checked':''; ?>> <?php echo mits_cdb_tools_text('replace_faq'); ?></label><?php } ?>
          <?php if ($imageSliderToolsAvailable) { ?><label class="mits-check"><input type="checkbox" name="replace_imageslider" value="1"<?php echo ($activeTool==='replace_content' && (string)mits_cdb_tools_post('replace_imageslider','')==='1')?' checked':''; ?>> <?php echo mits_cdb_tools_text('replace_imageslider'); ?></label><?php } ?>
          <div class="mits-tools__actions"><button class="mits-button" type="submit" name="run_mode" value="preview"><?php echo mits_cdb_tools_text('common_preview'); ?></button></div>
          <div class="mits-live"><label class="mits-check"><input type="checkbox" name="backup_confirmed" value="1"> <?php echo mits_cdb_tools_text('common_backup_confirmed'); ?></label><div class="mits-field"><label><?php echo mits_cdb_tools_text('common_confirm_exact'); ?> <code><?php echo mits_cdb_tools_html(mits_cdb_tools_text('confirm_replace')); ?></code></label><input class="mits-input" type="text" name="confirm_text" autocomplete="off"></div><button class="mits-button mits-button--danger" type="submit" name="run_mode" value="live" data-confirm="<?php echo mits_cdb_tools_html(mits_cdb_tools_text('replace_confirm')); ?>" onclick="return confirm(this.getAttribute('data-confirm')); "><?php echo mits_cdb_tools_text('replace_button'); ?></button></div>
        </form>
      </div>
    </details>

    <details class="mits-card"<?php echo $activeTool === 'newsletter' ? ' open' : ''; ?>>
      <summary><h2><?php echo mits_cdb_tools_text('newsletter_title'); ?></h2><p class="mits-card__sub"><?php echo mits_cdb_tools_text('newsletter_sub'); ?></p></summary>
      <div class="mits-card__body">
        <?php echo xtc_draw_form('mits_newsletter', 'mits_cron_database_tools.php', '', 'post'); ?><?php echo mits_cdb_tools_hidden('newsletter'); ?>
          <label class="mits-check"><input type="checkbox" name="newsletter_history" value="1"<?php echo ($activeTool==='newsletter' && (string)mits_cdb_tools_post('newsletter_history','')==='1')?' checked':''; ?>> <?php echo mits_cdb_tools_text('newsletter_history'); ?></label>
          <div class="mits-tools__actions"><button class="mits-button" type="submit" name="run_mode" value="preview"><?php echo mits_cdb_tools_text('common_preview'); ?></button></div>
          <div class="mits-live"><label class="mits-check"><input type="checkbox" name="backup_confirmed" value="1"> <?php echo mits_cdb_tools_text('common_backup_confirmed'); ?></label><div class="mits-field"><label><?php echo mits_cdb_tools_text('common_confirm_exact'); ?> <code><?php echo mits_cdb_tools_html(mits_cdb_tools_text('confirm_delete')); ?></code></label><input class="mits-input" type="text" name="confirm_text" autocomplete="off"></div><button class="mits-button mits-button--danger" type="submit" name="run_mode" value="live" data-confirm="<?php echo mits_cdb_tools_html(mits_cdb_tools_text('newsletter_confirm')); ?>" onclick="return confirm(this.getAttribute('data-confirm')); "><?php echo mits_cdb_tools_text('newsletter_delete'); ?></button></div>
        </form>
      </div>
    </details>

    <details class="mits-card mits-expert"<?php echo $activeTool === 'swap_accounts' ? ' open' : ''; ?>>
      <summary><h2><?php echo mits_cdb_tools_text('swap_title'); ?></h2><p class="mits-card__sub"><?php echo mits_cdb_tools_text('swap_sub'); ?></p></summary>
      <div class="mits-card__body">
        <div class="mits-note mits-note--danger"><strong><?php echo mits_cdb_tools_text('swap_warning_title'); ?></strong> <?php echo mits_cdb_tools_text('swap_warning'); ?></div>
        <?php echo xtc_draw_form('mits_swap', 'mits_cron_database_tools.php', '', 'post'); ?><?php echo mits_cdb_tools_hidden('swap_accounts'); ?>
          <div class="mits-row"><div class="mits-field"><label><?php echo mits_cdb_tools_text('swap_id_a'); ?></label><input class="mits-input" type="number" min="1" name="swap_id_a" value="<?php echo (int)($activeTool==='swap_accounts'?mits_cdb_tools_int('swap_id_a',1):1); ?>"></div><div class="mits-field"><label><?php echo mits_cdb_tools_text('swap_id_b'); ?></label><input class="mits-input" type="number" min="1" name="swap_id_b" value="<?php echo (int)($activeTool==='swap_accounts'?mits_cdb_tools_int('swap_id_b',2):2); ?>"></div></div>
          <div class="mits-tools__actions"><button class="mits-button" type="submit" name="run_mode" value="preview"><?php echo mits_cdb_tools_text('swap_preview'); ?></button></div>
          <div class="mits-live"><label class="mits-check"><input type="checkbox" name="backup_confirmed" value="1"> <?php echo mits_cdb_tools_text('common_full_backup_confirmed'); ?></label><div class="mits-field"><label><?php echo mits_cdb_tools_text('common_confirm_exact'); ?> <code><?php echo mits_cdb_tools_html(mits_cdb_tools_text('confirm_swap')); ?></code></label><input class="mits-input" type="text" name="confirm_text" autocomplete="off"></div><button class="mits-button mits-button--danger" type="submit" name="run_mode" value="live" data-confirm="<?php echo mits_cdb_tools_html(mits_cdb_tools_text('swap_confirm')); ?>" onclick="return confirm(this.getAttribute('data-confirm')); "><?php echo mits_cdb_tools_text('swap_button'); ?></button></div>
        </form>
      </div>
    </details>
  </div>
</div>

<!-- footer //-->
<?php require(DIR_WS_INCLUDES . 'footer.php'); ?>
<!-- footer_eof //-->

</body>
</html>
<?php require(DIR_WS_INCLUDES . 'application_bottom.php'); ?>
