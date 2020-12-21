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

require_once(DIR_FS_INC . 'xtc_rand.inc.php');

class mits_cron_database_backups {
  var $code, $title, $description, $enabled;

  function __construct() {
    $this->code = 'mits_cron_database_backups';
    $this->title = MODULE_MITS_CRON_DATABASE_BACKUPS_TITLE;
    $this->description = MODULE_MITS_CRON_DATABASE_BACKUPS_DESCRIPTION;
    $this->sort_order = MODULE_MITS_CRON_DATABASE_BACKUPS_SORT_ORDER;
    $this->enabled = ((MODULE_MITS_CRON_DATABASE_BACKUPS_STATUS == 'true') ? true : false);
  }

  function process($file) {
    //do nothing
  }

  function display() {
    return array(
      'text' => '<br>' . xtc_button(BUTTON_SAVE) . '&nbsp;' .
        xtc_button_link(BUTTON_CANCEL, xtc_href_link(FILENAME_MODULE_EXPORT, 'set=' . $_GET['set'] . '&module=' . $this->code))
    );
  }

  function check() {
    if (!isset($this->_check)) {
      $check_query = xtc_db_query("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = 'MODULE_" . strtoupper($this->code) . "_STATUS'");
      $this->_check = xtc_db_num_rows($check_query);
    }
    return $this->_check;
  }

  function install() {
    xtc_db_query("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_key, configuration_value, configuration_group_id, sort_order, set_function, date_added) VALUES ('MODULE_MITS_CRON_DATABASE_BACKUPS_STATUS', 'true',  6, 1, 'xtc_cfg_select_option(array(\'true\', \'false\'), ', now())");
    xtc_db_query("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_key, configuration_value, configuration_group_id, sort_order, set_function, date_added) VALUES ('MODULE_MITS_CRON_DATABASE_BACKUPS_HASH', '" . md5(time() . xtc_rand(0, 99999)) . "',  6, 2, NULL, now());");
    xtc_db_query("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_key, configuration_value, configuration_group_id, sort_order, set_function, date_added) VALUES ('MODULE_MITS_CRON_DATABASE_BACKUPS_GZIP', 'true',  6, 3, 'xtc_cfg_select_option(array(\'true\', \'false\'), ', now())");
    xtc_db_query("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_key, configuration_value, configuration_group_id, sort_order, set_function, date_added) VALUES ('MODULE_MITS_CRON_DATABASE_BACKUPS_COMPLETE_INSERT', 'true', 6, 4, 'xtc_cfg_select_option(array(\'true\', \'false\'), ', now())");
    xtc_db_query("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_key, configuration_value, configuration_group_id, sort_order, set_function, date_added) VALUES ('MODULE_MITS_CRON_DATABASE_BACKUPS_EXTENDED_INSERT', 'true', 6, 5, 'xtc_cfg_select_option(array(\'true\', \'false\'), ', now())");
    xtc_db_query("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_key, configuration_value, configuration_group_id, sort_order, set_function, date_added) VALUES ('MODULE_MITS_CRON_DATABASE_BACKUPS_SENDMAIL', 'false',  '6', 6, 'xtc_cfg_select_option(array(\'true\', \'false\'), ', now())");
    xtc_db_query("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_key, configuration_value, configuration_group_id, sort_order, set_function, date_added) VALUES ('MODULE_MITS_CRON_DATABASE_BACKUPS_MAILADDRESS', '" . STORE_OWNER_EMAIL_ADDRESS . "',  6, 7, NULL, now());");
    xtc_db_query("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_key, configuration_value, configuration_group_id, sort_order, set_function, date_added) VALUES ('MODULE_MITS_CRON_DATABASE_BACKUPS_SENDFTP', 'false',  6, 8, 'xtc_cfg_select_option(array(\'true\', \'false\'), ', now())");
    xtc_db_query("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_key, configuration_value, configuration_group_id, sort_order, set_function, date_added) VALUES ('MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_HOST', '',  6, 9, NULL, now());");
    xtc_db_query("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_key, configuration_value, configuration_group_id, sort_order, set_function, date_added) VALUES ('MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_USER', '',  6, 10, NULL, now());");
    xtc_db_query("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_key, configuration_value, configuration_group_id, sort_order, set_function, date_added) VALUES ('MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_PASS', '',  6, 11, NULL, now());");
    xtc_db_query("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_key, configuration_value, configuration_group_id, sort_order, set_function, date_added) VALUES ('MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_PORT', '21', 6, 12, NULL, now());");
    xtc_db_query("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_key, configuration_value, configuration_group_id, sort_order, set_function, date_added) VALUES ('MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_PATH', '/', 6, 13, NULL, now());");
    xtc_db_query("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_key, configuration_value, configuration_group_id, sort_order, set_function, date_added) VALUES ('MODULE_MITS_CRON_DATABASE_BACKUPS_DELETEOLDBACKUPS', 'true', 6, 14, 'xtc_cfg_select_option(array(\'true\', \'false\'), ', now())");
    xtc_db_query("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_key, configuration_value, configuration_group_id, sort_order, set_function, date_added) VALUES ('MODULE_MITS_CRON_DATABASE_BACKUPS_DELETEOLDBACKUPS_DAYS', '180',  6, 15, NULL, now());");
    xtc_db_query("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_key, configuration_value, configuration_group_id, sort_order, set_function, date_added) VALUES ('MODULE_MITS_CRON_DATABASE_BACKUPS_DELETELOGS', 'true', 6, 16, 'xtc_cfg_select_option(array(\'true\', \'false\'), ', now())");
  }

  function remove() {
    xtc_db_query("DELETE FROM " . TABLE_CONFIGURATION . " WHERE configuration_key LIKE 'MODULE_MITS_CRON_DATABASE_BACKUPS_%'");
  }

  function keys() {
    return array(
      'MODULE_MITS_CRON_DATABASE_BACKUPS_STATUS',
      'MODULE_MITS_CRON_DATABASE_BACKUPS_HASH',
      'MODULE_MITS_CRON_DATABASE_BACKUPS_GZIP',
      'MODULE_MITS_CRON_DATABASE_BACKUPS_COMPLETE_INSERT',
      'MODULE_MITS_CRON_DATABASE_BACKUPS_EXTENDED_INSERT',
      'MODULE_MITS_CRON_DATABASE_BACKUPS_SENDMAIL',
      'MODULE_MITS_CRON_DATABASE_BACKUPS_MAILADDRESS',
      'MODULE_MITS_CRON_DATABASE_BACKUPS_SENDFTP',
      'MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_HOST',
      'MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_USER',
      'MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_PASS',
      'MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_PORT',
      'MODULE_MITS_CRON_DATABASE_BACKUPS_FTP_PATH',
      'MODULE_MITS_CRON_DATABASE_BACKUPS_DELETEOLDBACKUPS',
      'MODULE_MITS_CRON_DATABASE_BACKUPS_DELETEOLDBACKUPS_DAYS',
      'MODULE_MITS_CRON_DATABASE_BACKUPS_DELETELOGS',
    );
  }

}