<?php
/**
 * --------------------------------------------------------------
 * File: mits_cron_database_backups.php
 * Created by PhpStorm
 * Date: 23.05.2018
 * Time: 16:38
 *
 * Author: Hetfield
 * Copyright: (c) 2018 - MerZ IT-SerVice
 * Web: https://www.merz-it-service.de
 * Contact: info@merz-it-service.de
 *
 * Released under the GNU General Public License
 * --------------------------------------------------------------
 */

$modulname = strtoupper('mits_cron_database_backups');
$module_key = 'MODULE_' . $modulname;
$mits_default_hash = '3p7R9VAZcbtUCptYH212u4n7jtVBg4Wy';
$mits_hash = defined($module_key . '_HASH') ? constant($module_key . '_HASH') : $mits_default_hash;

if (defined($module_key . '_STATUS') && constant($module_key . '_STATUS') == 'true') {
  $mits_db_backup_cronjoburl = '<hr /><h3>URL CronJob:</h3><textarea style="width: 100%;height:auto;">' . xtc_catalog_href_link('callback/mits_cron_database_backups/mits_cron_database_backups.php', 'pw=' . $mits_hash, 'SSL') . '</textarea><p>Utilice esta URL en sus cron jobs.</p><p>El par&aacute;metro <strong style="color:#900">pw</strong> debe sustituirse por el valor HASH configurado. Si cambia el valor HASH, tambi&eacute;n debe actualizar la URL en el cron job.</p>';
  $mits_db_backup_button = '<hr /><div style="text-align:center;padding:10px;"><a href="'.xtc_catalog_href_link('callback/mits_cron_database_backups/mits_cron_database_backups.php', '', 'SSL').'?pw='.$mits_hash.'" class="button" onclick="this.blur();"><strong>Iniciar copia de seguridad de la base de datos</strong></a></div>';
  $mits_db_restore_button = '<div style="text-align:center;padding:10px;"><a href="'.xtc_href_link('mits_cron_database_restore.php', '', 'NONSSL').'" class="button" onclick="this.blur();"><strong>Abrir herramientas de base de datos</strong></a></div><hr />';
} else {
  $mits_db_backup_cronjoburl = '';
  $mits_db_backup_button = '';
  $mits_db_restore_button = '';
}

$mits_disabled_functions = array_map('trim', explode(',', (string)ini_get('disable_functions')));
$mits_exec_enabled = function_exists('exec') && !in_array('exec', $mits_disabled_functions) && strtolower((string)ini_get('safe_mode')) != 1;
$mits_curl_enabled = function_exists('curl_init');
$mits_no_curl = (!$mits_curl_enabled) ? '<div style="padding:6px;background:#ff0;font-size:14px;border:1px solid #900;color:#900;"><strong>La extensi&oacute;n PHP cURL no est&aacute; activa. La tarea programada modified de este m&oacute;dulo requiere cURL para que la llamada de copia de seguridad pueda ejecutarse de forma aislada mediante la URL de callback.</strong></div>' : '';
$mits_no_exec = (!$mits_exec_enabled) ? '<div style="padding:6px;background:#ff0;font-size:14px;border:1px solid #900;color:#900;"><strong>Su servidor no proporciona los permisos necesarios. La funci&oacute;n <i>exec()</i> est&aacute; desactivada. Contacte con su proveedor para activarla o cambie a un proveedor con exec activo, por ejemplo <a href="https://all-inkl.com/?partner=293050">all-inkl.com</a>.</strong></div>' : '';

$lang_array = array(
  'MODULE_' . $modulname . '_TITLE'       => 'MITS Cron Database Backups <span style="white-space:nowrap;">&copy; by <span style="padding:2px;background:#ffe;color:#6a9;font-weight:bold;">Hetfield (<a href="https://www.merz-it-service.de/" target="_blank">MerZ IT-SerVice</a>)</span></span>',
  'MODULE_' . $modulname . '_DESCRIPTION' => '
   <div>
    <a href="https://www.merz-it-service.de/" target="_blank">
        <img src="' . DIR_WS_CATALOG . 'callback/mits_cron_database_backups/merz-it-service.png" border="0" alt="MerZ IT-SerVice" style="display:block;max-width:100%;height:auto;" />
    </a><br />
    <h3>Copia de seguridad de base de datos mediante CronJob</h3>
    ' . $mits_no_exec . '
    ' . $mits_no_curl . '
    <div>
      <p>Con este m&oacute;dulo puede crear copias de seguridad autom&aacute;ticas y peri&oacute;dicas de la base de datos de la tienda o iniciar una copia manualmente cuando sea necesario.</p>
      <ul>
        <li>Crear autom&aacute;ticamente copias de seguridad peri&oacute;dicas de la base de datos de la tienda</li>

        <li>Crear opcionalmente una carpeta de copia por tablas con un archivo SQL.GZ por tabla</li>
       <li>Restauraci&oacute;n r&aacute;pida de copias SQL/SQL.GZ existentes en el &aacute;rea de administraci&oacute;n mediante el cliente mysql</li>
        <li>Tarea programada modified opcional: llama a la URL de callback existente mediante cURL</li>
        <li>Enviar opcionalmente la copia de seguridad por correo electr&oacute;nico</li>
        <li>Subir opcionalmente la copia de seguridad por FTP a otro servidor de copias</li>
        <li>Eliminar autom&aacute;ticamente copias antiguas despu&eacute;s de x d&iacute;as</li>
        <li>Eliminar autom&aacute;ticamente archivos LOG antiguos de tipo mod_notice, mod_deprecated y mod_strict despu&eacute;s de x d&iacute;as</li>
      </ul>
      <div style="text-align:center;">
        <small>La versi&oacute;n m&aacute;s reciente del m&oacute;dulo est&aacute; siempre disponible en GitHub.</small><br />
        <a style="background:#6a9;color:#444" target="_blank" href="https://github.com/hetfield74/MITS_Cron_Database_Backups" class="button" onclick="this.blur();">MITS_Cron_Database_Backups on GitHub</a>
      </div>
      <p>Para preguntas, problemas o solicitudes sobre este m&oacute;dulo o sobre la modified eCommerce Shopsoftware, contacte con nosotros:</p>
      <div style="text-align:center;"><a style="background:#6a9;color:#444" target="_blank" href="https://www.merz-it-service.de/Kontakt.html" class="button" onclick="this.blur();">P&aacute;gina de contacto en MerZ-IT-SerVice.de</a></div>
    </div>
    ' . $mits_db_backup_cronjoburl . '
    ' . $mits_db_backup_button . '
    ' . $mits_db_restore_button . '
  </div>
',

  'MODULE_' . $modulname . '_STATUS_TITLE' => 'Status',
  'MODULE_' . $modulname . '_STATUS_DESC'  => 'Activar m&oacute;dulo',

  'MODULE_' . $modulname . '_HASH_TITLE' => 'Valor HASH',
  'MODULE_' . $modulname . '_HASH_DESC'  => 'El par&aacute;metro <strong>pw</strong> debe sustituirse por el valor introducido en HASH, como se muestra en este ejemplo de URL: pw=<strong style="color:#900">' . $mits_default_hash . '</strong>. Por motivos de seguridad, este valor deber&iacute;a cambiarse con frecuencia. El nuevo valor HASH debe utilizarse tambi&eacute;n en la URL de llamada del script.',

  'MODULE_' . $modulname . '_GZIP_TITLE' => 'Compresi&oacute;n GZIP',
  'MODULE_' . $modulname . '_GZIP_DESC'  => '&iquest;Debe utilizarse la compresi&oacute;n GZIP para la copia de seguridad de la base de datos?',

  'MODULE_' . $modulname . '_COMPLETE_INSERT_TITLE' => 'Option --complete-insert',
  'MODULE_' . $modulname . '_COMPLETE_INSERT_DESC'  => 'Complete inserts a&ntilde;ade los nombres de columnas al dump SQL. Este par&aacute;metro mejora la legibilidad y fiabilidad del dump. A&ntilde;adir los nombres de columnas aumenta el tama&ntilde;o del dump SQL, pero junto con Extended Insert suele ser insignificante.',

  'MODULE_' . $modulname . '_EXTENDED_INSERT_TITLE' => 'Option --extended-insert',
  'MODULE_' . $modulname . '_EXTENDED_INSERT_DESC'  => 'Extended Insert combina varias filas de datos en una sola consulta INSERT. Esto reduce significativamente el tama&ntilde;o de archivo en dumps SQL grandes, aumenta la velocidad de importaci&oacute;n y se recomienda generalmente.',

  'MODULE_' . $modulname . '_SQL_COMMENTS_TITLE' => 'Comentarios en el dump SQL',
  'MODULE_' . $modulname . '_SQL_COMMENTS_DESC'  => '¿A&ntilde;adir comentarios al dump SQL? Adem&aacute;s de los comentarios de mysqldump, se a&ntilde;ade un breve comentario de cabecera MITS. Los comentarios se ignoran durante la restauraci&oacute;n.',

  'MODULE_' . $modulname . '_BACKUP_MODE_TITLE' => 'Modo de copia',
  'MODULE_' . $modulname . '_BACKUP_MODE_DESC'  => '<strong>single</strong> crea un archivo SQL/SQL.GZ para toda la base de datos. <strong>tables</strong> crea una carpeta de copia propia con un archivo SQL.GZ por tabla.',

  'MODULE_' . $modulname . '_SENDMAIL_TITLE' => 'Enviar copia de seguridad por correo electr&oacute;nico',
  'MODULE_' . $modulname . '_SENDMAIL_DESC'  => '&iquest;Debe enviarse la copia de seguridad de la base de datos por correo electr&oacute;nico?',

  'MODULE_' . $modulname . '_MAILADDRESS_TITLE' => 'Direcci&oacute;n de correo para la copia',
  'MODULE_' . $modulname . '_MAILADDRESS_DESC'  => 'Introduzca aqu&iacute; la direcci&oacute;n de correo a la que debe enviarse la copia de seguridad. Tenga en cuenta posibles l&iacute;mites para adjuntos grandes.',

  'MODULE_' . $modulname . '_SENDFTP_TITLE' => 'Enviar copia de seguridad por FTP',
  'MODULE_' . $modulname . '_SENDFTP_DESC'  => '&iquest;Debe subirse la copia de seguridad por FTP a otro servidor?',

  'MODULE_' . $modulname . '_FTP_HOST_TITLE' => 'Servidor FTP',
  'MODULE_' . $modulname . '_FTP_HOST_DESC'  => 'Nombre del servidor FTP.',

  'MODULE_' . $modulname . '_FTP_USER_TITLE' => 'Usuario FTP',
  'MODULE_' . $modulname . '_FTP_USER_DESC'  => 'Introduzca aqu&iacute; el usuario FTP.',

  'MODULE_' . $modulname . '_FTP_PASS_TITLE' => 'Contrase&ntilde;a FTP',
  'MODULE_' . $modulname . '_FTP_PASS_DESC'  => 'Introduzca aqu&iacute; la contrase&ntilde;a FTP.',

  'MODULE_' . $modulname . '_FTP_PORT_TITLE' => 'Puerto FTP',
  'MODULE_' . $modulname . '_FTP_PORT_DESC'  => 'Introduzca aqu&iacute; el puerto FTP, por ejemplo 21.',

  'MODULE_' . $modulname . '_FTP_PATH_TITLE' => 'Ruta del servidor FTP',
  'MODULE_' . $modulname . '_FTP_PATH_DESC'  => 'Introduzca aqu&iacute; la ruta completa del servidor FTP donde debe guardarse la copia de seguridad.',

  'MODULE_' . $modulname . '_DELETEOLDBACKUPS_TITLE' => 'Activar eliminaci&oacute;n autom&aacute;tica',
  'MODULE_' . $modulname . '_DELETEOLDBACKUPS_DESC'  => '&iquest;Activar la eliminaci&oacute;n autom&aacute;tica de copias antiguas? Si est&aacute; en <strong>s&iacute;</strong>, todos los archivos con extensi&oacute;n <i>.sql</i> y <i>.sql.gz</i> m&aacute;s antiguos que el periodo configurado se eliminar&aacute;n autom&aacute;ticamente de la carpeta <i>' . (defined('DIR_ADMIN') ? DIR_ADMIN : 'admin/') . 'backups</i>.',

  'MODULE_' . $modulname . '_DELETEOLDBACKUPS_DAYS_TITLE' => 'Periodo de eliminaci&oacute;n autom&aacute;tica',
  'MODULE_' . $modulname . '_DELETEOLDBACKUPS_DAYS_DESC'  => '&iquest;Despu&eacute;s de cu&aacute;ntos d&iacute;as deben eliminarse las copias antiguas? Introduzca solo d&iacute;as en cifras. Solo relevante si <i>Activar eliminaci&oacute;n autom&aacute;tica</i> est&aacute; en <strong>s&iacute;</strong>.',

  'MODULE_' . $modulname . '_DELETELOGS_TITLE' => 'Eliminar archivos LOG antiguos',
  'MODULE_' . $modulname . '_DELETELOGS_DESC'  => '&iquest;Deben eliminarse autom&aacute;ticamente tambi&eacute;n los archivos LOG antiguos de tipo mod_notice, mod_strict y mod_deprecated? Solo relevante si <i>Activar eliminaci&oacute;n autom&aacute;tica</i> est&aacute; en <strong>s&iacute;</strong>. El periodo es id&eacute;ntico al valor indicado en <i>Periodo de eliminaci&oacute;n autom&aacute;tica</i>.',

  'MODULE_' . $modulname . '_UPDATE_AVAILABLE_TITLE' => ' <span style="font-weight:bold;color:#900;background:#ff6;padding:2px;border:1px solid #900;">Actualice el m&oacute;dulo.</span>',
  'MODULE_' . $modulname . '_UPDATE_AVAILABLE_DESC'  => '',
  'MODULE_' . $modulname . '_UPDATE_FINISHED'        => 'MITS Cron Database Backups se ha actualizado.',
  'MODULE_' . $modulname . '_UPDATE_ERROR'           => 'Error',
  'MODULE_' . $modulname . '_UPDATE_MODUL'           => 'Actualizar m&oacute;dulo',
  'MODULE_' . $modulname . '_DELETE_MODUL'           => 'Eliminar completamente MITS Cron Database Backups del servidor',
  'MODULE_' . $modulname . '_CONFIRM_DELETE_MODUL'   => '&iquest;Desea eliminar realmente MITS Cron Database Backups del servidor, incluidos los archivos?',
  'MODULE_' . $modulname . '_DELETE_FINISHED'        => 'MITS Cron Database Backups se ha eliminado del servidor.',
);

foreach ($lang_array as $key => $val) {
    defined($key) || define($key, $val);
}
