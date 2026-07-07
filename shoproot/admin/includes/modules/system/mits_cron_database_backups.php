<?php
/**
 * --------------------------------------------------------------
 * File: mits_cron_database_backups.php
 * Created by PhpStorm
 * Date: 29.01.2019
 * Time: 16:33
 *
 * Author: Hetfield
 * Copyright: (c) 2019 - MerZ IT-SerVice
 * Web: https://www.merz-it-service.de
 * Contact: info@merz-it-service.de
 *
 * Released under the GNU General Public License
 * --------------------------------------------------------------
 */

defined('_VALID_XTC') or die('Direct Access to this location is not allowed.');

require_once DIR_FS_INC . 'xtc_rand.inc.php';

class mits_cron_database_backups
{
    public string $code;
    public string $name;
    public string $version;
    public $sort_order;
    public string $title;
    public string $description;
    public $do_update;
    public bool $enabled;
    private $_check;
    private string $default_columns;

    public function __construct()
    {
        $this->code = 'mits_cron_database_backups';
        $this->name = 'MODULE_' . strtoupper($this->code);
        $this->version = '1.6.6';
        $this->sort_order = defined($this->name . '_SORT_ORDER') ? constant($this->name . '_SORT_ORDER') : 0;
        $this->enabled = defined($this->name . '_STATUS') && (constant($this->name . '_STATUS') == 'true');
        $this->default_columns = 'configuration_key, configuration_value, configuration_group_id, sort_order, set_function';

        if (defined($this->name . '_VERSION') && $this->version != constant($this->name . '_VERSION')) {
            $this->do_update = defined($this->name . '_UPDATE_AVAILABLE_TITLE') ? constant($this->name . '_UPDATE_AVAILABLE_TITLE') : '';
        } else {
            $this->do_update = '';
        }

        $title = defined($this->name . '_TITLE') ? constant($this->name . '_TITLE') : (defined($this->name . '_TEXT_TITLE') ? constant($this->name . '_TEXT_TITLE') : $this->code);
        $this->title = $title . ' - v' . $this->version . $this->do_update;
        $this->description = '';

        if ($this->do_update != '') {
            $update_text = defined($this->name . '_UPDATE_MODUL') ? constant($this->name . '_UPDATE_MODUL') : 'Modul aktualisieren';
            $this->description .= '<a class="button btnbox but_green" style="text-align:center;" onclick="this.blur();" href="' . xtc_href_link(FILENAME_MODULE_EXPORT, 'set=' . $_GET['set'] . '&module=' . $this->code . '&action=update') . '">' . $update_text . '</a><br>';
        }

        if (defined($this->name . '_DESCRIPTION')) {
            $this->description .= constant($this->name . '_DESCRIPTION') . '<hr style="margin:10px 0">';
        } elseif (defined($this->name . '_TEXT_DESCRIPTION')) {
            $this->description .= constant($this->name . '_TEXT_DESCRIPTION') . '<hr style="margin:10px 0">';
        }

        if (!$this->enabled) {
            $delete_text = defined($this->name . '_DELETE_MODUL') ? constant($this->name . '_DELETE_MODUL') : 'Modul komplett vom Server entfernen';
            $delete_confirm = defined($this->name . '_CONFIRM_DELETE_MODUL') ? constant($this->name . '_CONFIRM_DELETE_MODUL') : 'Soll das Modul inklusive Dateien wirklich vom Server entfernt werden?';
            $this->description .= '<div style="text-align:center;margin:30px 0"><a class="button but_red" style="text-align:center;" onclick="return confirmLink(\'' . $delete_confirm . '\', \'\' ,this);" href="' . xtc_href_link(FILENAME_MODULE_EXPORT, 'set=system&module=' . $this->code . '&action=custom') . '">' . $delete_text . '</a></div><br>';
        }

        if (defined($this->name . '_STATUS')) {
            $this->installScheduledTask();
            $this->installAdminRestoreAccess();
        }
    }

    /**
     * @return void
     */
    public function process(): void
    {
    }

    /**
     * @return string[]
     */
    public function display(): array
    {
        return array(
          'text' => '<br>' . xtc_button(BUTTON_SAVE) . '&nbsp;' .
            xtc_button_link(BUTTON_CANCEL, xtc_href_link(FILENAME_MODULE_EXPORT, 'set=' . $_GET['set'] . '&module=' . $this->code))
        );
    }

    public function check()
    {
        if (!isset($this->_check)) {
            if (defined($this->name . '_STATUS')) {
                $this->_check = true;
            } else {
                $check_query = xtc_db_query("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = '" . $this->name . "_STATUS'");
                $this->_check = xtc_db_num_rows($check_query);
            }
        }
        return $this->_check;
    }

    /**
     * @return void
     */
    public function install(): void
    {
        $this->dbChanges();
				xtc_db_query("INSERT INTO " . TABLE_CONFIGURATION . " (" . $this->default_columns . ", date_added) VALUES ('" . $this->name . "_HASH', '" . md5(time() . xtc_rand(0, 99999)) . "', 6, 2, NULL, now())");
    }

    /**
     * @return void
     */
    public function update(): void
    {
        global $messageStack;

        $this->dbChanges();
        $this->removeOldFiles();

        if (defined($this->name . '_UPDATE_FINISHED')) {
            $messageStack->add_session(constant($this->name . '_UPDATE_FINISHED'), 'success');
        }
    }

    /**
     * @return void
     */
    private function dbChanges(): void
    {
        $backup_mail_address = defined('STORE_OWNER_EMAIL_ADDRESS') ? STORE_OWNER_EMAIL_ADDRESS : '';

        if (!defined($this->name . '_STATUS')) {
            xtc_db_query("INSERT INTO " . TABLE_CONFIGURATION . " (" . $this->default_columns . ", date_added) VALUES ('" . $this->name . "_STATUS', 'true', 6, 1, 'xtc_cfg_select_option(array(\'true\', \'false\'), ', now())");
        }

        if (!defined($this->name . '_VERSION')) {
            xtc_db_query("INSERT INTO " . TABLE_CONFIGURATION . " (" . $this->default_columns . ", date_added) VALUES ('" . $this->name . "_VERSION', '" . $this->version . "', 6, 99, NULL, now())");
        } else {
            xtc_db_query("UPDATE " . TABLE_CONFIGURATION . " SET configuration_value = '" . $this->version . "' WHERE configuration_key = '" . $this->name . "_VERSION'");
        }

        if (!defined($this->name . '_HASH')) {
            xtc_db_query("INSERT INTO " . TABLE_CONFIGURATION . " (" . $this->default_columns . ", date_added) VALUES ('" . $this->name . "_HASH', '" . md5(time() . xtc_rand(0, 99999)) . "', 6, 2, NULL, now())");
        }

        if (!defined($this->name . '_GZIP')) {
            xtc_db_query("INSERT INTO " . TABLE_CONFIGURATION . " (" . $this->default_columns . ", date_added) VALUES ('" . $this->name . "_GZIP', 'true', 6, 3, 'xtc_cfg_select_option(array(\'true\', \'false\'), ', now())");
        }

        if (!defined($this->name . '_COMPLETE_INSERT')) {
            xtc_db_query("INSERT INTO " . TABLE_CONFIGURATION . " (" . $this->default_columns . ", date_added) VALUES ('" . $this->name . "_COMPLETE_INSERT', 'true', 6, 4, 'xtc_cfg_select_option(array(\'true\', \'false\'), ', now())");
        }

        if (!defined($this->name . '_EXTENDED_INSERT')) {
            xtc_db_query("INSERT INTO " . TABLE_CONFIGURATION . " (" . $this->default_columns . ", date_added) VALUES ('" . $this->name . "_EXTENDED_INSERT', 'true', 6, 5, 'xtc_cfg_select_option(array(\'true\', \'false\'), ', now())");
        }

        if (!defined($this->name . '_SQL_COMMENTS')) {
            xtc_db_query("INSERT INTO " . TABLE_CONFIGURATION . " (" . $this->default_columns . ", date_added) VALUES ('" . $this->name . "_SQL_COMMENTS', 'true', 6, 6, 'xtc_cfg_select_option(array(\'true\', \'false\'), ', now())");
        }

        if (!defined($this->name . '_BACKUP_MODE')) {
            xtc_db_query("INSERT INTO " . TABLE_CONFIGURATION . " (" . $this->default_columns . ", date_added) VALUES ('" . $this->name . "_BACKUP_MODE', 'single', 6, 7, 'xtc_cfg_select_option(array(\'single\', \'tables\'), ', now())");
        } else {
            xtc_db_query("UPDATE " . TABLE_CONFIGURATION . " SET set_function = 'xtc_cfg_select_option(array(\'single\', \'tables\'), ' WHERE configuration_key = '" . $this->name . "_BACKUP_MODE'");
            xtc_db_query("UPDATE " . TABLE_CONFIGURATION . " SET configuration_value = 'tables' WHERE configuration_key = '" . $this->name . "_BACKUP_MODE' AND configuration_value = 'tables_zip'");
        }

        if (!defined($this->name . '_WRITE_LOG')) {
            xtc_db_query("INSERT INTO " . TABLE_CONFIGURATION . " (" . $this->default_columns . ", date_added) VALUES ('" . $this->name . "_WRITE_LOG', 'true', 6, 8, 'xtc_cfg_select_option(array(\'true\', \'false\'), ', now())");
        }

        if (!defined($this->name . '_SENDMAIL')) {
            xtc_db_query("INSERT INTO " . TABLE_CONFIGURATION . " (" . $this->default_columns . ", date_added) VALUES ('" . $this->name . "_SENDMAIL', 'false', 6, 6, 'xtc_cfg_select_option(array(\'true\', \'false\'), ', now())");
        }

        if (!defined($this->name . '_MAILADDRESS')) {
            xtc_db_query("INSERT INTO " . TABLE_CONFIGURATION . " (" . $this->default_columns . ", date_added) VALUES ('" . $this->name . "_MAILADDRESS', '" . xtc_db_input($backup_mail_address) . "', 6, 7, NULL, now())");
        }

        if (!defined($this->name . '_SENDFTP')) {
            xtc_db_query("INSERT INTO " . TABLE_CONFIGURATION . " (" . $this->default_columns . ", date_added) VALUES ('" . $this->name . "_SENDFTP', 'false', 6, 8, 'xtc_cfg_select_option(array(\'true\', \'false\'), ', now())");
        }

        if (!defined($this->name . '_FTP_HOST')) {
            xtc_db_query("INSERT INTO " . TABLE_CONFIGURATION . " (" . $this->default_columns . ", date_added) VALUES ('" . $this->name . "_FTP_HOST', '', 6, 9, NULL, now())");
        }

        if (!defined($this->name . '_FTP_USER')) {
            xtc_db_query("INSERT INTO " . TABLE_CONFIGURATION . " (" . $this->default_columns . ", date_added) VALUES ('" . $this->name . "_FTP_USER', '', 6, 10, NULL, now())");
        }

        if (!defined($this->name . '_FTP_PASS')) {
            xtc_db_query("INSERT INTO " . TABLE_CONFIGURATION . " (" . $this->default_columns . ", date_added) VALUES ('" . $this->name . "_FTP_PASS', '', 6, 11, NULL, now())");
        }

        if (!defined($this->name . '_FTP_PORT')) {
            xtc_db_query("INSERT INTO " . TABLE_CONFIGURATION . " (" . $this->default_columns . ", date_added) VALUES ('" . $this->name . "_FTP_PORT', '21', 6, 12, NULL, now())");
        }

        if (!defined($this->name . '_FTP_PATH')) {
            xtc_db_query("INSERT INTO " . TABLE_CONFIGURATION . " (" . $this->default_columns . ", date_added) VALUES ('" . $this->name . "_FTP_PATH', '/', 6, 13, NULL, now())");
        }

        if (!defined($this->name . '_DELETEOLDBACKUPS')) {
            xtc_db_query("INSERT INTO " . TABLE_CONFIGURATION . " (" . $this->default_columns . ", date_added) VALUES ('" . $this->name . "_DELETEOLDBACKUPS', 'true', 6, 14, 'xtc_cfg_select_option(array(\'true\', \'false\'), ', now())");
        }

        if (!defined($this->name . '_DELETEOLDBACKUPS_DAYS')) {
            xtc_db_query("INSERT INTO " . TABLE_CONFIGURATION . " (" . $this->default_columns . ", date_added) VALUES ('" . $this->name . "_DELETEOLDBACKUPS_DAYS', '180', 6, 15, NULL, now())");
        }

        if (!defined($this->name . '_DELETELOGS')) {
            xtc_db_query("INSERT INTO " . TABLE_CONFIGURATION . " (" . $this->default_columns . ", date_added) VALUES ('" . $this->name . "_DELETELOGS', 'true', 6, 16, 'xtc_cfg_select_option(array(\'true\', \'false\'), ', now())");
        }

        $this->ensureBackupDirectoryProtection();
        $this->installScheduledTask(1);
        $this->installAdminRestoreAccess();
    }

    /**
     * @return void
     */
    public function custom(): void
    {
        global $messageStack;

        $this->remove();
        $this->removeModulfiles();

        if (defined($this->name . '_DELETE_FINISHED')) {
            $messageStack->add_session(constant($this->name . '_DELETE_FINISHED'), 'success');
        }
    }

    /**
     * @return void
     */
    public function remove(): void
    {
        $this->removeScheduledTask();
        $this->removeAdminRestoreAccess();
        xtc_db_query("DELETE FROM " . TABLE_CONFIGURATION . " WHERE configuration_key in ('" . implode("', '", $this->keys()) . "')");
        xtc_db_query("DELETE FROM " . TABLE_CONFIGURATION . " WHERE configuration_key LIKE '" . $this->name . "_%'");
    }

    /**
     * @param int|null $status
     * @return void
     */
    private function installScheduledTask($status = null): void
    {
        if (!defined('TABLE_SCHEDULED_TASKS') || !$this->dbTableExists(TABLE_SCHEDULED_TASKS)) {
            return;
        }

        $task = $this->code;
        $check_query = xtc_db_query("SELECT tasks_id FROM " . TABLE_SCHEDULED_TASKS . " WHERE tasks = '" . $task . "' LIMIT 1");
        if (xtc_db_num_rows($check_query) > 0) {
            return;
        }

        $time_offset = 3 * 60 * 60;
        $time_next = strtotime(date('Y-m-d 03:00:00'));
        if ($time_next <= time()) {
            $time_next += 24 * 60 * 60;
        }

        if ($status === null) {
            $status = (defined($this->name . '_STATUS') && constant($this->name . '_STATUS') == 'true') ? 1 : 0;
        } else {
            $status = (int)$status;
        }

        xtc_db_query(
          "INSERT INTO " . TABLE_SCHEDULED_TASKS . "
            (tasks, time_next, time_regularity, time_unit, time_offset, status, edit)
           VALUES
            ('" . $task . "', '" . (int)$time_next . "', '1', 'd', '" . (int)$time_offset . "', '" . (int)$status . "', '1')"
        );
    }

    /**
     * @return void
     */
    private function removeScheduledTask(): void
    {
        if (!defined('TABLE_SCHEDULED_TASKS') || !$this->dbTableExists(TABLE_SCHEDULED_TASKS)) {
            return;
        }

        $task = $this->code;
        $tasks_query = xtc_db_query("SELECT tasks_id FROM " . TABLE_SCHEDULED_TASKS . " WHERE tasks = '" . $task . "'");
        while ($tasks = xtc_db_fetch_array($tasks_query)) {
            if (defined('TABLE_SCHEDULED_TASKS_LOG') && $this->dbTableExists(TABLE_SCHEDULED_TASKS_LOG)) {
                xtc_db_query("DELETE FROM " . TABLE_SCHEDULED_TASKS_LOG . " WHERE tasks_id = '" . (int)$tasks['tasks_id'] . "'");
            }
        }

        xtc_db_query("DELETE FROM " . TABLE_SCHEDULED_TASKS . " WHERE tasks = '" . $task . "'");
    }

    /**
     * @return void
     */
    private function installAdminRestoreAccess(): void
    {
        if (!defined('TABLE_ADMIN_ACCESS') || !$this->dbTableExists(TABLE_ADMIN_ACCESS)) {
            return;
        }

        $column = 'mits_cron_database_restore';
        if (!$this->dbColumnExists(TABLE_ADMIN_ACCESS, $column)) {
            xtc_db_query("ALTER TABLE " . TABLE_ADMIN_ACCESS . " ADD `" . $column . "` INT(1) NOT NULL DEFAULT '0'");
            xtc_db_query("UPDATE " . TABLE_ADMIN_ACCESS . " SET `" . $column . "` = 1 WHERE customers_id != 'groups'");
        }
    }

    /**
     * @return void
     */
    private function removeAdminRestoreAccess(): void
    {
        if (!defined('TABLE_ADMIN_ACCESS') || !$this->dbTableExists(TABLE_ADMIN_ACCESS)) {
            return;
        }

        $column = 'mits_cron_database_restore';
        if ($this->dbColumnExists(TABLE_ADMIN_ACCESS, $column)) {
            xtc_db_query("ALTER TABLE " . TABLE_ADMIN_ACCESS . " DROP `" . $column . "`");
        }
    }

    /**
     * @return string[]
     */
    public function keys(): array
    {
        return array(
          $this->name . '_STATUS',
          $this->name . '_HASH',
          $this->name . '_GZIP',
          $this->name . '_COMPLETE_INSERT',
          $this->name . '_EXTENDED_INSERT',
          $this->name . '_SQL_COMMENTS',
          $this->name . '_BACKUP_MODE',
          $this->name . '_WRITE_LOG',
          $this->name . '_SENDMAIL',
          $this->name . '_MAILADDRESS',
          $this->name . '_SENDFTP',
          $this->name . '_FTP_HOST',
          $this->name . '_FTP_USER',
          $this->name . '_FTP_PASS',
          $this->name . '_FTP_PORT',
          $this->name . '_FTP_PATH',
          $this->name . '_DELETEOLDBACKUPS',
          $this->name . '_DELETEOLDBACKUPS_DAYS',
          $this->name . '_DELETELOGS',
        );
    }

    /**
     * @return void
     */
    private function ensureBackupDirectoryProtection(): void
    {
        $dir = rtrim(DIR_FS_DOCUMENT_ROOT . 'export/mits_cron_database_backups', '/\\') . DIRECTORY_SEPARATOR;
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        if (!is_dir($dir) || !is_writable($dir)) {
            return;
        }

        $marker = 'MITS Cron Database Backups Protection';
        $htaccess_file = $dir . '.htaccess';
        $htaccess_block = "
# BEGIN " . $marker . "
"
          . "Options -Indexes
"
          . "<IfModule mod_authz_core.c>
"
          . "  Require all denied
"
          . "</IfModule>
"
          . "<IfModule !mod_authz_core.c>
"
          . "  Order deny,allow
"
          . "  Deny from all
"
          . "</IfModule>
"
          . "# END " . $marker . "
";

        $current_htaccess = is_file($htaccess_file) ? (string)@file_get_contents($htaccess_file) : '';
        if ($current_htaccess === '' || strpos($current_htaccess, $marker) === false) {
            @file_put_contents($htaccess_file, rtrim($current_htaccess) . $htaccess_block, LOCK_EX);
            @chmod($htaccess_file, 0644);
        }

        $index_file = $dir . 'index.html';
        if (!is_file($index_file)) {
            @file_put_contents($index_file, '', LOCK_EX);
            @chmod($index_file, 0644);
        }

        $web_config_file = $dir . 'web.config';
        if (!is_file($web_config_file)) {
            $web_config = '<?xml version="1.0" encoding="UTF-8"?>' . "
"
              . '<configuration>' . "
"
              . '  <system.webServer>' . "
"
              . '    <security>' . "
"
              . '      <authorization>' . "
"
              . '        <clear />' . "
"
              . '        <add accessType="Deny" users="*" />' . "
"
              . '      </authorization>' . "
"
              . '    </security>' . "
"
              . '  </system.webServer>' . "
"
              . '</configuration>' . "
";
            @file_put_contents($web_config_file, $web_config, LOCK_EX);
            @chmod($web_config_file, 0644);
        }
    }


    /**
     * @param string $table
     * @param string $column
     * @return bool
     */
    private function dbColumnExists(string $table, string $column): bool
    {
        $res = xtc_db_query("SHOW COLUMNS FROM {$table} LIKE '{$column}'");
        return xtc_db_num_rows($res) > 0;
    }

    /**
     * @param string $table
     * @return bool
     */
    private function dbTableExists(string $table): bool
    {
        $table = str_replace(array('\\', '_', '%'), array('\\\\', '\\_', '\\%'), $table);
        $check_query = xtc_db_query("SHOW TABLES LIKE '" . $table . "'");
        return xtc_db_num_rows($check_query) > 0;
    }

    /**
     * @return void
     */
    protected function removeOldFiles(): void
    {
        $old_files_array = array();

        foreach ($old_files_array as $delete_file) {
            if (is_file($delete_file)) {
                unlink($delete_file);
            }
        }
    }

    /**
     * @param string $dir
     * @return bool
     */
    protected function deleteDirectory($dir): bool
    {
        if (!file_exists($dir)) {
            return true;
        }

        if (!is_dir($dir)) {
            return unlink($dir);
        }

        $files = array_diff(scandir($dir), array('.', '..'));

        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }

        return rmdir($dir);
    }

    /**
     * @return void
     */
    protected function removeModulfiles(): void
    {
        $admin_dir = defined('DIR_ADMIN') ? DIR_ADMIN : 'admin/';
        $remove_files_array = array(
          DIR_FS_DOCUMENT_ROOT . $admin_dir . 'includes/modules/system/' . $this->code . '.php',
          DIR_FS_DOCUMENT_ROOT . $admin_dir . 'includes/extra/menu/mits_cron_database_restore.php',
          DIR_FS_DOCUMENT_ROOT . $admin_dir . 'mits_cron_database_restore.php',
          DIR_FS_DOCUMENT_ROOT . 'api/scheduled_tasks/modules/' . $this->code . '.php',
        );

        $languages = xtc_get_languages();
        if (count($languages) > 1) {
            foreach ($languages as $language) {
                $remove_files_array[] = DIR_FS_DOCUMENT_ROOT . 'lang/' . $language['directory'] . '/admin/mits_cron_database_restore.php';
                $remove_files_array[] = DIR_FS_DOCUMENT_ROOT . 'lang/' . $language['directory'] . '/extra/admin/' . $this->code . '.php';
                $remove_files_array[] = DIR_FS_DOCUMENT_ROOT . 'lang/' . $language['directory'] . '/extra/admin/mits_cron_database_restore.php';
                $remove_files_array[] = DIR_FS_DOCUMENT_ROOT . 'lang/' . $language['directory'] . '/modules/system/' . $this->code . '.php';
            }
        }

        foreach ($remove_files_array as $delete_file) {
            if (is_file($delete_file)) {
                unlink($delete_file);
            }
        }

        $this->deleteDirectory(DIR_FS_DOCUMENT_ROOT . 'callback/' . $this->code);
    }
}
