<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

define("TMP_IMG_FILE", "/../../data/tmp/img.png");

// =====================================
// Gestion des commandes recues par AJAX
// =====================================
try {
    require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
    include_file('core', 'authentification', 'php');

    if (!isConnect('admin')) {
        throw new Exception(__('401 - Accès non autorisé', __FILE__));
    }

  // Copy images received
  log::add('gps_traker', 'info', 'Ajax:upload images');
  $dbg_msg = json_encode ($_FILES)."\n";
  // log::add('gps_traker', 'info', 'Ajax:upload images, files=>'.$dbg_msg);
  // $dbg_msg = json_encode ($_POST)."\n";
  // log::add('gps_traker', 'info', 'Ajax:upload images, post=>'.$dbg_msg);
  // $srv_path = $_POST["path"];
  if ($_FILES["file"]["error"] == UPLOAD_ERR_OK) {
      $name = $_FILES["file"]["name"];
      // log::add('gps_traker', 'info', 'Ajax:upload images : name='.$name);
      $temp_fn = dirname(__FILE__).TMP_IMG_FILE;
      // log::add('gps_traker', 'info', 'Ajax:upload images : TMP_IMG_DIR='.$temp_fn);
      $ret = move_uploaded_file( $_FILES["file"]["tmp_name"], $temp_fn);
      $ret_json = json_encode ($ret);
      ajax::success($ret_json);
  }

} catch (Exception $e) {
    ajax::error(displayExeption($e), $e->getCode());
}
?>
