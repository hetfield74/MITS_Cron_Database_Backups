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
    $this->name = 'MODULE_' . strtoupper($this->code);
    $this->version = '1.3';
    $this->title = constant($this->name . '_TEXT_TITLE') . ' - v' . $this->version;
    $this->description = constant($this->name . '_TEXT_DESCRIPTION');
    $this->sort_order = defined($this->name . '_SORT_ORDER') ? constant($this->name . '_SORT_ORDER') : 0;
    $this->enabled = (defined($this->name . '_STATUS') && constant($this->name . '_STATUS') == 'true') ? true : false;
  }

  function process($file) {
    if (defined(constant($this->name . '_VERSION'))) {
      xtc_db_query("UPDATE " . TABLE_CONFIGURATION . " SET configuration_value = '" . $this->version . "' WHERE configuration_key = '" . $this->name . "_VERSION'");
    } else {
      xtc_db_query("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_key, configuration_value, configuration_group_id, sort_order, set_function, date_added) VALUES ('" . $this->name . "_VERSION', '" . $this->version . "', 6, 17, NULL, now())");
    }
  }

  function display() {
    return array(
      'text' => '<br>' . xtc_button(BUTTON_SAVE) . '&nbsp;' .
        xtc_button_link(BUTTON_CANCEL, xtc_href_link(FILENAME_MODULE_EXPORT, 'set=' . $_GET['set'] . '&module=' . $this->code))
    );
  }

  function check() {
    if (!isset($this->_check)) {
      $check_query = xtc_db_query("SELECT configuration_value FROM " . TABLE_CONFIGURATION . " WHERE configuration_key = '" . $this->name . "_STATUS'");
      $this->_check = xtc_db_num_rows($check_query);
    }
    return $this->_check;
  }

  function install() {
    $backup_mail_address = defined('STORE_OWNER_EMAIL_ADDRESS') ? STORE_OWNER_EMAIL_ADDRESS : '';
    xtc_db_query("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_key, configuration_value, configuration_group_id, sort_order, set_function, date_added) VALUES ('" . $this->name . "_STATUS', 'true', 6, 1, 'xtc_cfg_select_option(array(\'true\', \'false\'), ', now())");
    xtc_db_query("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_key, configuration_value, configuration_group_id, sort_order, set_function, date_added) VALUES ('" . $this->name . "_HASH', '" . md5(time() . xtc_rand(0, 99999)) . "', 6, 2, NULL, now());");
    xtc_db_query("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_key, configuration_value, configuration_group_id, sort_order, set_function, date_added) VALUES ('" . $this->name . "_GZIP', 'true', 6, 3, 'xtc_cfg_select_option(array(\'true\', \'false\'), ', now())");
    xtc_db_query("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_key, configuration_value, configuration_group_id, sort_order, set_function, date_added) VALUES ('" . $this->name . "_COMPLETE_INSERT', 'true', 6, 4, 'xtc_cfg_select_option(array(\'true\', \'false\'), ', now())");
    xtc_db_query("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_key, configuration_value, configuration_group_id, sort_order, set_function, date_added) VALUES ('" . $this->name . "_EXTENDED_INSERT', 'true', 6, 5, 'xtc_cfg_select_option(array(\'true\', \'false\'), ', now())");
    xtc_db_query("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_key, configuration_value, configuration_group_id, sort_order, set_function, date_added) VALUES ('" . $this->name . "_SENDMAIL', 'false', '6', 6, 'xtc_cfg_select_option(array(\'true\', \'false\'), ', now())");
    xtc_db_query("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_key, configuration_value, configuration_group_id, sort_order, set_function, date_added) VALUES ('" . $this->name . "_MAILADDRESS', '" . $backup_mail_address . "', 6, 7, NULL, now());");
    xtc_db_query("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_key, configuration_value, configuration_group_id, sort_order, set_function, date_added) VALUES ('" . $this->name . "_SENDFTP', 'false', 6, 8, 'xtc_cfg_select_option(array(\'true\', \'false\'), ', now())");
    xtc_db_query("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_key, configuration_value, configuration_group_id, sort_order, set_function, date_added) VALUES ('" . $this->name . "_FTP_HOST', '', 6, 9, NULL, now());");
    xtc_db_query("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_key, configuration_value, configuration_group_id, sort_order, set_function, date_added) VALUES ('" . $this->name . "_FTP_USER', '', 6, 10, NULL, now());");
    xtc_db_query("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_key, configuration_value, configuration_group_id, sort_order, set_function, date_added) VALUES ('" . $this->name . "_FTP_PASS', '', 6, 11, NULL, now());");
    xtc_db_query("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_key, configuration_value, configuration_group_id, sort_order, set_function, date_added) VALUES ('" . $this->name . "_FTP_PORT', '21', 6, 12, NULL, now());");
    xtc_db_query("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_key, configuration_value, configuration_group_id, sort_order, set_function, date_added) VALUES ('" . $this->name . "_FTP_PATH', '/', 6, 13, NULL, now());");
    xtc_db_query("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_key, configuration_value, configuration_group_id, sort_order, set_function, date_added) VALUES ('" . $this->name . "_DELETEOLDBACKUPS', 'true', 6, 14, 'xtc_cfg_select_option(array(\'true\', \'false\'), ', now())");
    xtc_db_query("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_key, configuration_value, configuration_group_id, sort_order, set_function, date_added) VALUES ('" . $this->name . "_DELETEOLDBACKUPS_DAYS', '180', 6, 15, NULL, now());");
    xtc_db_query("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_key, configuration_value, configuration_group_id, sort_order, set_function, date_added) VALUES ('" . $this->name . "_DELETELOGS', 'true', 6, 16, 'xtc_cfg_select_option(array(\'true\', \'false\'), ', now())");
    xtc_db_query("INSERT INTO " . TABLE_CONFIGURATION . " (configuration_key, configuration_value, configuration_group_id, sort_order, set_function, date_added) VALUES ('" . $this->name . "_VERSION', '" . $this->version . "', 6, 17, NULL, now())");
  }

  function remove() {
    xtc_db_query("DELETE FROM " . TABLE_CONFIGURATION . " WHERE configuration_key LIKE '" . $this->name . "_%'");
  }

  function keys() {
    return array(
      $this->name . '_STATUS',
      $this->name . '_HASH',
      $this->name . '_GZIP',
      $this->name . '_COMPLETE_INSERT',
      $this->name . '_EXTENDED_INSERT',
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

}