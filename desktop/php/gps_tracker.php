<?php
if (!isConnect('admin')) {
    throw new Exception('{{401 - Accès non autorisé}}');
}
$plugin = plugin::byId('gps_tracker');
sendVarToJS('eqType', $plugin->getId());
$eqLogics = eqLogic::byType($plugin->getId());
?>

<div class="row row-overflow">
   <div class="col-xs-12 eqLogicThumbnailDisplay">
        <legend><i class="fas fa-cog"></i>  {{Gestion}}</legend>
        <div class="eqLogicThumbnailContainer">
            <div class="cursor eqLogicAction logoPrimary" data-action="add">
                <i class="fas fa-plus-circle"></i>
                <br>
                <span>{{Ajouter}}</span>
            </div>
            <div class="cursor eqLogicAction logoSecondary" data-action="gotoPluginConf">
                <i class="fas fa-wrench"></i>
                <br>
                <span>{{Configuration}}</span>
            </div>
            <div class="cursor eqLogicAction logoSecondary" onclick="window.open('https:\/\/github.com/lelas33/plugin_gps_tracker/blob/master/README.md', '_blank')">
                <i class="fas fa-book"></i>
                <br>
                <span>{{Documentation}}</span>
            </div>
        </div>
        <legend><i class="fas fa-table"></i> {{Mes traceurs GPS}}</legend>
        <input class="form-control" placeholder="{{Rechercher}}" id="in_searchEqlogic" />
        <div class="eqLogicThumbnailContainer">
            <?php
            foreach ($eqLogics as $eqLogic) {
                $opacity = ($eqLogic->getIsEnable()) ? '' : 'disableCard';
                echo '<div class="eqLogicDisplayCard cursor '.$opacity.'" data-eqLogic_id="' . $eqLogic->getId() . '">';
                echo '<img src="' . $plugin->getPathImgIcon() . '"/>';
                echo '<br>';
                echo '<span class="name">' . $eqLogic->getHumanName(true, true) . '</span>';
                echo '</div>';
            }
            ?>
        </div>
    </div>
    <div class="col-xs-12 eqLogic" style="display: none;">
	    <div class="input-group pull-right" style="display:inline-flex">
            <span class="input-group-btn">
                <a class="btn btn-default btn-sm eqLogicAction roundedLeft" data-action="configure"><i class="fa fa-cogs"></i> {{Configuration avancée}}</a><a class="btn btn-default btn-sm eqLogicAction" data-action="copy"><i class="fas fa-copy"></i> {{Dupliquer}}</a><a class="btn btn-sm btn-success eqLogicAction" data-action="save"><i class="fas fa-check-circle"></i> {{Sauvegarder}}</a><a class="btn btn-danger btn-sm eqLogicAction roundedRight" data-action="remove"><i class="fas fa-minus-circle"></i> {{Supprimer}}</a>
            </span>
        </div>
		<ul class="nav nav-tabs" role="tablist">
			<li role="presentation"><a href="#" class="eqLogicAction" aria-controls="home" role="tab" data-toggle="tab" data-action="returnToThumbnailDisplay"><i class="fa fa-arrow-circle-left"></i></a></li>
			<li role="presentation" class="active"><a href="#eqlogictab" aria-controls="home" role="tab" data-toggle="tab"><i class="fa fa-tachometer"></i> {{Equipement}}</a></li>
			<li role="presentation"><a href="#commandtab" aria-controls="profile" role="tab" data-toggle="tab"><i class="fa fa-list-alt"></i> {{Commandes}}</a></li>
		</ul>
		<div class="tab-content" style="height:calc(100% - 50px);overflow:auto;overflow-x: hidden;">
			<div role="tabpanel" class="tab-pane active" id="eqlogictab">
        <form class="form-horizontal">
            <fieldset>
                <legend>
                   <i class="fa fa-arrow-circle-left eqLogicAction cursor" data-action="returnToThumbnailDisplay"></i> {{Général}}
                   <i class='fa fa-cogs eqLogicAction pull-right cursor expertModeVisible' data-action='configure'></i>
               </legend>
               <div class="form-group">
                    <label class="col-lg-2 control-label">{{Nom de l'équipement}}</label>
                    <div class="col-lg-3">
                        <input type="text" class="eqLogicAttr form-control" data-l1key="id" style="display : none;" />
                        <input type="text" class="eqLogicAttr form-control" data-l1key="name" placeholder="{{Nom de l'objet suivi}}"/>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-lg-2 control-label">{{Type de traceur GPS}}</label>
                    <div class="col-lg-3">
                      <select id="tracker_select" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="type_tracker">
                          <option value="TKS">{{Traceur TKSTAR TK905}}</option>
                          <option value="JCN">{{Application JeedomConnect}}</option>
                          <option value="JMT">{{Application JeeMate}}</option>
                          <option value="TRC">{{GPS Traccar}}</option>
                          <option value="GEN">{{GPS générique}}</option>
                      </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-lg-2 control-label" >{{Objet parent}}</label>
                    <div class="col-lg-3">
                        <select class="form-control eqLogicAttr" data-l1key="object_id">
                            <option value="">{{Aucun}}</option>
                            <?php
                            $options = '';
                            foreach ((jeeObject::buildTree(null, false)) as $object) {
                              $options .= '<option value="' . $object->getId() . '">' . str_repeat('&nbsp;&nbsp;', $object->getConfiguration('parentNumber')) . $object->getName() . '</option>';
                            }
                            echo $options;
                            ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-lg-2 control-label">{{Catégorie}}</label>
                    <div class="col-lg-8">
                        <?php
                        foreach (jeedom::getConfiguration('eqLogic:category') as $key => $value) {
                            echo '<label class="checkbox-inline">'."\n";
                            echo '<input type="checkbox" class="eqLogicAttr" data-l1key="category" data-l2key="' . $key . '" />' . $value['name'];
                            echo '</label>'."\n";
                        }
                        ?>

                    </div>
                </div>
                <div class="form-group">
                    <label class="col-md-2 control-label" >{{Activer}}</label>
                    <div class="col-md-1">
                        <input type="checkbox" class="eqLogicAttr" data-l1key="isEnable" checked/>
                    </div>
                    <label class="col-lg-2 control-label" >{{Visible}}</label>
                    <div class="col-lg-1">
                        <input type="checkbox" class="eqLogicAttr" data-l1key="isVisible" checked/>
                    </div>
                </div>
                <div class="form-group">
                    <label class="col-lg-2 control-label"></label>
                    <label class="col-lg-3"><br>{{Image de l'objet suivi}}</label>
                </div>
                <div class="form-group">
                    <label class="col-lg-2 control-label"></label>
                    <div class="col-lg-2">
                       <input type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="img_default" checked/> {{ Image par défaut}}
                    </div>
                </div>
                <div class="form-group load_image">
                    <label class="col-lg-2 control-label"></label>
                    <div class="col-lg-3">
                      <span class="input-group-btn">
                        <br><input type="file" accept="image/png" id="load_image_input" style="display:none;" >
                        <a class="btn btn-primary" id="load_image_conf"><i class="fa fa-cloud-upload-alt"></i> {{Fichier Image}}</a>
                      </span>
                    </div>
                    <div class="col-lg-3">
                      <img class="pull-left" id="object_img" src="" style="max-height:250px;max-width:350px;height:auto;width:auto;"/>
                    </div>
                </div>
                <div id="param_tks">
                  <div class="form-group">
                      <label class="col-lg-2 control-label"></label>
                      <label class="col-lg-4"><br>{{Informations complémentaires pour le traceur TKSTAR}}</label>
                  </div>
                  <div class="form-group">
                      <label class="col-lg-2 control-label">{{Numéro IMEI / ID}}</label>
                      <div class="col-lg-3">
                          <input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="tkstar_imei" placeholder="{{numéro IMEI du traceur}}"/>
                      </div>
                  </div>
                  <div class="form-group">
                      <label class="col-lg-2 control-label">{{Login compte Tkstar}}</label>
                      <div class="col-lg-3">
                          <input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="tkstar_account" />
                      </div>
                  </div>
                  <div class="form-group">
                      <label class="col-lg-2 control-label">{{Password compte Tkstar}}</label>
                      <div class="col-lg-3">
                          <input type="password" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="tkstar_password" />
                      </div>
                  </div>
                </div>
                <div id="param_jcn" style="display: none;">
                  <div class="form-group">
                      <label class="col-lg-2 control-label"></label>
                      <label class="col-lg-4"><br>{{Information complémentaire pour le traceur Appli.JeedomConnect}}</label>
                  </div>
                  <div class="form-group">
                      <label class="col-lg-2 control-label">{{Position}}</label>
                      <div class="col-lg-4">
                        <div class="input-group">
                          <input type="text" class="eqLogicAttr form-control roundedLeft" data-l1key="configuration" data-l2key="cmd_jc_position"/>
                          <span class="input-group-btn">
                            <a class="btn btn-default listCmdInfoOther roundedRight"><i class="fas fa-list-alt"></i></a>
                          </span>
                        </div>
                      </div>
                  </div>
                </div>
                <div id="param_jmt" style="display: none;">
                  <div class="form-group">
                      <label class="col-lg-2 control-label"></label>
                      <label class="col-lg-4"><br>{{Informations complémentaires pour le traceur Appli.JeeMate}}</label>
                  </div>
                  <div class="form-group">
                      <label class="col-lg-2 control-label">{{Position}}</label>
                      <div class="col-lg-4">
                        <div class="input-group">
                          <input type="text" class="eqLogicAttr form-control roundedLeft" data-l1key="configuration" data-l2key="cmd_jm_position"/>
                          <span class="input-group-btn">
                            <a class="btn btn-default listCmdInfoOther roundedRight"><i class="fas fa-list-alt"></i></a>
                          </span>
                        </div>
                      </div>
                  </div>
                  <div class="form-group">
                      <label class="col-lg-2 control-label">{{Activité}}</label>
                      <div class="col-lg-4">
                        <div class="input-group">
                          <input type="text" class="eqLogicAttr form-control roundedLeft" data-l1key="configuration" data-l2key="cmd_jm_activite"/>
                          <span class="input-group-btn">
                            <a class="btn btn-default listCmdInfoOther roundedRight"><i class="fas fa-list-alt"></i></a>
                          </span>
                        </div>
                      </div>
                  </div>
                  <div class="form-group">
                      <label class="col-lg-2 control-label">{{Batterie}}</label>
                      <div class="col-lg-4">
                        <div class="input-group">
                          <input type="text" class="eqLogicAttr form-control roundedLeft" data-l1key="configuration" data-l2key="cmd_jm_batterie"/>
                          <span class="input-group-btn">
                            <a class="btn btn-default listCmdInfoNumeric roundedRight"><i class="fas fa-list-alt"></i></a>
                          </span>
                        </div>
                      </div>
                  </div>
                </div>
                <div id="param_trc">
                  <div class="form-group">
                      <label class="col-lg-2 control-label"></label>
                      <label class="col-lg-4"><br>{{Informations complémentaires pour un traceur dans une base Traccar}}</label>
                  </div>
                  <div class="form-group">
                      <label class="col-lg-2 control-label">{{URL du serveur Traccar}}</label>
                      <div class="col-lg-3">
                          <input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="trc_url" placeholder="{{http://xx.xx.xx.xx:8082}}"/>
                      </div>
                  </div>
                  <div class="form-group">
                      <label class="col-lg-2 control-label">{{Login compte Traccar}}</label>
                      <div class="col-lg-3">
                          <input type="text" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="trc_account" />
                      </div>
                  </div>
                  <div class="form-group">
                      <label class="col-lg-2 control-label">{{Password compte Traccar}}</label>
                      <div class="col-lg-3">
                          <input type="password" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="trc_password" />
                      </div>
                  </div>
                  <div class="form-group">
                      <label class="col-lg-2 control-label">{{Nom du traceur Traccar}}</label>
                      <div class="col-lg-3">
                         <input type="text" id="trc_name_input" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="trc_name" placeholder="{{Nom du traceur dans la base Traccar}}" />
                      </div>
                      <div class="col-lg-3 load_traccar_devices">
                        <a class="btn btn-success" id="load_traccar_devices_conf">{{liste "devices"}}</a>
                      </div>
                  </div>
                  <div class="form-group">
                      <label class="col-lg-2 control-label">{{ID unique du traceur Traccar}}</label>
                      <div class="col-lg-3">
                         <input type="text" id="trc_idunic_input" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="trc_idunic" placeholder="{{Identifiant unique du traceur dans la base Traccar}}" />
                      </div>
                  </div>
                  <div class="form-group">
                      <label class="col-lg-2 control-label">{{ID du traceur Traccar}}</label>
                      <div class="col-lg-3">
                         <input type="text" id="trc_id_input" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="trc_id" placeholder="{{Identifiant du traceur dans la base Traccar}}" />
                      </div>
                  </div>
                </div>
                <div id="param_gen" style="display: none;">
                  <div class="form-group">
                      <label class="col-lg-2 control-label"></label>
                      <label class="col-lg-4"><br>{{Informations complémentaires pour le traceur générique}}</label>
                  </div>
                  <div class="form-group">
                      <label class="col-lg-2 control-label"></label>
                      <label class="col-lg-4">{{Cas où les infos GPS sont dans un champ unique, séparés par des virgules}}</label>
                      <label class="col-lg-1">{{}}</label>
                      <label class="col-lg-4">{{Cas où les infos GPS sont dans des champs séparés}}</label>
                  </div>
                  <div class="form-group">
                      <label class="col-sm-2 control-label">{{Position}}
                        <sup><i class="fas fa-question-circle tooltips" title="{{Informations issues du GPS, sous la forme: param1, param2, param3, param4}}"></i></sup>
                      </label>
                      <div class="col-lg-4">
                        <div class="input-group">
                          <input type="text" class="eqLogicAttr form-control roundedLeft" data-l1key="configuration" data-l2key="cmd_gen_position" placeholder="{{param1, param2, param3, param4}}"/>
                          <span class="input-group-btn">
                            <a class="btn btn-default listCmdInfoOther roundedRight"><i class="fas fa-list-alt"></i></a>
                          </span>
                        </div>
                      </div>
                  </div>
                  <div class="form-group">
                      <label class="col-lg-2 control-label">{{Valeur de param1}}
                        <sup><i class="fas fa-question-circle tooltips" title="{{Signification du paramètre: param1}}"></i></sup>
                      </label>
                      <div class="col-lg-4">
                        <select id="tracker_select" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="gen_param1">
                            <option value="LAT" selected="selected">{{Latitude}}</option>
                            <option value="LON">{{Longitude}}</option>
                            <option value="ALT">{{Altitude}}</option>
                            <option value="SPD">{{Vitesse}}</option>
                            <option value="NONE">{{Inutilisé}}</option>
                        </select>
                      </div>
                      <label class="col-lg-1 control-label">{{Latitude}}</label>
                      <div class="col-lg-4">
                        <div class="input-group">
                          <input type="text" class="eqLogicAttr form-control roundedLeft" data-l1key="configuration" data-l2key="cmd_gen_info_lat" placeholder="{{Lien vers info Latitude}}"/>
                          <span class="input-group-btn">
                            <a class="btn btn-default listCmdInfoNumeric roundedRight"><i class="fas fa-list-alt"></i></a>
                          </span>
                        </div>
                      </div>
                  </div>
                  <div class="form-group">
                      <label class="col-lg-2 control-label">{{Valeur de param2}}
                        <sup><i class="fas fa-question-circle tooltips" title="{{Signification du paramètre: param2}}"></i></sup>
                      </label>
                      <div class="col-lg-4">
                        <select id="tracker_select" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="gen_param2">
                            <option value="LAT">{{Latitude}}</option>
                            <option value="LON" selected="selected">{{Longitude}}</option>
                            <option value="ALT">{{Altitude}}</option>
                            <option value="SPD">{{Vitesse}}</option>
                            <option value="NONE">{{Inutilisé}}</option>
                        </select>
                      </div>
                      <label class="col-lg-1 control-label">{{Longitude}}</label>
                      <div class="col-lg-4">
                        <div class="input-group">
                          <input type="text" class="eqLogicAttr form-control roundedLeft" data-l1key="configuration" data-l2key="cmd_gen_info_lon" placeholder="{{Lien vers info Longitude}}"/>
                          <span class="input-group-btn">
                            <a class="btn btn-default listCmdInfoNumeric roundedRight"><i class="fas fa-list-alt"></i></a>
                          </span>
                        </div>
                      </div>
                  </div>
                  <div class="form-group">
                      <label class="col-lg-2 control-label">{{Valeur de param3}}
                        <sup><i class="fas fa-question-circle tooltips" title="{{Signification du paramètre: param3}}"></i></sup>
                      </label>
                      <div class="col-lg-4">
                        <select id="tracker_select" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="gen_param3">
                            <option value="LAT">{{Latitude}}</option>
                            <option value="LON">{{Longitude}}</option>
                            <option value="ALT">{{Altitude}}</option>
                            <option value="SPD">{{Vitesse}}</option>
                            <option value="NONE" selected="selected">{{Inutilisé}}</option>
                        </select>
                      </div>
                      <label class="col-lg-1 control-label">{{Altitude}}</label>
                      <div class="col-lg-4">
                        <div class="input-group">
                          <input type="text" class="eqLogicAttr form-control roundedLeft" data-l1key="configuration" data-l2key="cmd_gen_info_alt" placeholder="{{Lien vers info Altidute}}"/>
                          <span class="input-group-btn">
                            <a class="btn btn-default listCmdInfoNumeric roundedRight"><i class="fas fa-list-alt"></i></a>
                          </span>
                        </div>
                      </div>
                  </div>
                  <div class="form-group">
                      <label class="col-lg-2 control-label">{{Valeur de param4}}
                        <sup><i class="fas fa-question-circle tooltips" title="{{Signification du paramètre: param4}}"></i></sup>
                      </label>
                      <div class="col-lg-4">
                        <select id="tracker_select" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="gen_param4">
                            <option value="LAT">{{Latitude}}</option>
                            <option value="LON">{{Longitude}}</option>
                            <option value="ALT">{{Altitude}}</option>
                            <option value="SPD">{{Vitesse}}</option>
                            <option value="NONE" selected="selected">{{Inutilisé}}</option>
                        </select>
                      </div>
                      <label class="col-lg-1 control-label">{{Vitesse}}</label>
                      <div class="col-lg-4">
                        <div class="input-group">
                          <input type="text" class="eqLogicAttr form-control roundedLeft" data-l1key="configuration" data-l2key="cmd_gen_info_spd" placeholder="{{Lien vers info Vitesse}}"/>
                          <span class="input-group-btn">
                            <a class="btn btn-default listCmdInfoNumeric roundedRight"><i class="fas fa-list-alt"></i></a>
                          </span>
                        </div>
                      </div>
                  </div>
                </div>
            </fieldset>
        </form>
			</div>
			<div role="tabpanel" class="tab-pane" id="commandtab">
        <table id="table_cmd" class="table table-bordered table-condensed">
            <thead>
                <tr>
                    <th style="width: 50px;">#</th>
                    <th style="width: 230px;">{{Nom}}</th>
                    <th style="width: 110px;">{{Sous-Type}}</th>
                    <th style="width: 100px;">{{Paramètres}}</th>
                    <th style="width: 200px;"></th>
                </tr>
            </thead>
            <tbody>

            </tbody>
        </table>
		   </div>
		</div>
    </div>
</div>

<?php include_file('desktop', 'gps_tracker', 'js', 'gps_tracker'); ?>
<?php include_file('core', 'plugin.template', 'js'); ?>
