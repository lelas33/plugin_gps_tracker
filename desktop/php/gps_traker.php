<?php
if (!isConnect('admin')) {
    throw new Exception('{{401 - Accès non autorisé}}');
}
$plugin = plugin::byId('gps_traker');
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
            <div class="cursor eqLogicAction logoSecondary" onclick="window.open('https:\/\/github.com/lelas33/plugin_peugeotcars/blob/master/README.md', '_blank')">
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
                              <select class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="type_traker">
                                  <option value="TKS">{{Traceur TKSTAR TK905}}</option>
                                  <option value="JCN">{{Application JeedomConnect}}</option>
                              </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-lg-2 control-label" >{{Objet parent}}</label>
                            <div class="col-lg-3">
                                <select class="form-control eqLogicAttr" data-l1key="object_id">
                                    <option value="">{{Aucun}}</option>
                                    <?php
                                    foreach (jeeObject::all() as $object) {
                                        echo '<option value="' . $object->getId() . '">' . $object->getName() . '</option>'."\n";
                                    }
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
                        <div class="form-group load_image">
                            <label class="col-lg-2 control-label"></label>
                            <div class="col-lg-3">
                              <span class="input-group-btn">
                                <input type="file" accept="image/png" id="load_image_input" style="display:none;" >
                                <a class="btn btn-primary" id="load_image_conf"><i class="fa fa-cloud-upload-alt"></i> {{Fichier Image}}</a>
                              </span>
                            </div>
                            <div class="col-lg-3">
                              <img class="pull-left" id="object_img" src="" style="max-height:250px;max-width:350px;height:auto;width:auto;"/>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-lg-2 control-label"></label>
                            <label class="col-lg-3"><br>{{Informations complémentaires pour le traceur TKSTAR}}</label>
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
                        <div class="form-group">
                            <label class="col-lg-2 control-label"></label>
                            <label class="col-lg-3"><br>{{Informations complémentaires pour le traceur JeedomConnect}}</label>
                        </div>
                        <div class="form-group">
                            <label class="col-lg-2 control-label">{{Activité}}</label>
                            <div class="col-lg-3">
                              <div class="input-group">
                                <input type="text" class="eqLogicAttr form-control roundedLeft" data-l1key="configuration" data-l2key="cmd_jc_activite"/>
                                <span class="input-group-btn">
                                  <a class="btn btn-default listCmdInfoOther roundedRight"><i class="fas fa-list-alt"></i></a>
                                </span>
                              </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-lg-2 control-label">{{Position}}</label>
                            <div class="col-lg-3">
                              <div class="input-group">
                                <input type="text" class="eqLogicAttr form-control roundedLeft" data-l1key="configuration" data-l2key="cmd_jc_position"/>
                                <span class="input-group-btn">
                                  <a class="btn btn-default listCmdInfoOther roundedRight"><i class="fas fa-list-alt"></i></a>
                                </span>
                              </div>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="col-lg-2 control-label">{{Batterie}}</label>
                            <div class="col-lg-3">
                              <div class="input-group">
                                <input type="text" class="eqLogicAttr form-control roundedLeft" data-l1key="configuration" data-l2key="cmd_jc_batterie"/>
                                <span class="input-group-btn">
                                  <a class="btn btn-default listCmdInfoNumeric roundedRight"><i class="fas fa-list-alt"></i></a>
                                </span>
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

<?php include_file('desktop', 'gps_traker', 'js', 'gps_traker'); ?>
<?php include_file('core', 'plugin.template', 'js'); ?>
