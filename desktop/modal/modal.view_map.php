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

if (!isConnect('admin')) {
  throw new Exception('{{401 - Accès non autorisé}}');
}
include_file('3rdparty', 'leaflet_v1.7.1/leaflet', 'js', 'gps_tracker');
include_file('3rdparty', 'leaflet_v1.7.1/leaflet', 'css', 'gps_tracker');
include_file('3rdparty', 'easy-button/easy-button', 'js', 'gps_tracker');
include_file('3rdparty', 'easy-button/easy-button', 'css', 'gps_tracker');

$plugin = plugin::byId('gps_tracker');
$eqLogics = eqLogic::byType($plugin->getId());
$eq_id = $_GET["eq_id"];

?>
  <div id="trips_map">
  </div>
  <input type="hidden" id="veh_eq_id"  value=<?php echo($eq_id); ?> />
<?php 
include_file('desktop', 'view_map', 'js', 'gps_tracker');
?>
