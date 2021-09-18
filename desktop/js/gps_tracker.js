// Gestion des listes de commandes action ou commandes info
$(".listCmdActionOther").on('click', function () {
  var el = $(this);
  jeedom.cmd.getSelectModal({cmd: {type: 'action',subType : 'other'}}, function (result) {
    el.closest('.input-group').find('input').value(result.human);
  });
});
$(".listCmdInfoNumeric").on('click', function () {
  var el = $(this);
  jeedom.cmd.getSelectModal({cmd: {type: 'info',subType : 'numeric'}}, function (result) {
    el.closest('.input-group').find('input').value(result.human);
  });
});
$(".listCmdInfoOther").on('click', function () {
  var el = $(this);
  jeedom.cmd.getSelectModal({cmd: {type: 'info',subType : 'string'}}, function (result) {
    el.closest('.input-group').find('input').value(result.human);
  });
});

// Chargement image de l'objet suivi
$('.load_image').off('click', '#load_image_conf').on('click', '#load_image_conf', function() {
  // alert("Click load_image_conf");
  $("#load_image_input").click();
});

$('.load_image').off('change', '#load_image_input').on('change', '#load_image_input', function() {
  var files = document.getElementById('load_image_input').files;
  if (files.length <= 0) {
      return false;
  }
  console.log(files[0]);
  const formData = new FormData();
  formData.append("file", files[0]);

  $.ajax({
    type: "POST",
    url: 'plugins/gps_tracker/core/ajax/gps_tracker_upload.ajax.php',
    data: formData,
    success: function (data) {
       console.log(data);
    },
    cache: false,
    contentType: false,
    processData: false
  });


});

// Lorsque le document est chargé
$('.eqLogicAttr[data-l1key=id]').change(function() {
  // recuperation de l'ID du eqlogic en cours
  var eq_id = $('.eqLogicAttr[data-l1key=id]').value();
  if (eq_id == "") {
    return;
  }
  // alert ("eq_id ="+eq_id);

  // Interrogation du serveur pour avoir le nom et chemin du fichier image de l'objet suivi
  $.ajax({
      type: 'POST',
      url: 'plugins/gps_tracker/core/ajax/gps_tracker.ajax.php',
      data: {
          action: 'getImagePath',
          eq_id: eq_id,  // Id de l'objet eqlogic
      },
      dataType: 'json',
      error: function (request, status, error) {
          alert("loadData:Error"+status+"/"+error);
          handleAjaxError(request, status, error);
      },
      success: function (data) {
          console.log("[loadData] Objet gps_tracker récupéré : " + eq_id);
          if (data.state != 'ok') {
              $('#div_alert').showAlert({message: data.result, level: 'danger'});
              return;
          }
          // alert("Retour:data nb="+data.result);
          $("#object_img").attr("src", data.result);
          
      }
  });

});



function addCmdToTable(_cmd) {
   if (!isset(_cmd)) {
        var _cmd = {configuration: {}};
    }
    if (!isset(_cmd.configuration)) {
        _cmd.configuration = {};
    }

    if (init(_cmd.type) == 'info') {
        var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '" >';
        if (init(_cmd.logicalId) == 'brut') {
          tr += '<input type="hiden" name="brutid" value="' + init(_cmd.id) + '">';
        }
        tr += '<td>';
        tr += '<span class="cmdAttr" data-l1key="id"></span>';
        tr += '</td>';
        tr += '<td>';
        tr += '<input class="cmdAttr form-control input-sm" data-l1key="name" style="width : 140px;" placeholder="{{Nom}}"></td>';
        tr += '<td class="expertModeVisible">';
        tr += '<input class="cmdAttr form-control type input-sm" data-l1key="type" value="action" disabled style="margin-bottom : 5px;" />';
        tr += '<span class="subType" subType="' + init(_cmd.subType) + '"></span>';
        tr += '</td>';
        tr += '<td>';
        tr += '<span><input type="checkbox" class="cmdAttr" data-l1key="isVisible" checked/> {{Afficher }}</span>';
        tr += '<span><input type="checkbox" class="cmdAttr" data-l1key="isHistorized"/> {{Historiser}}<br></span>';
        tr += '<input class="cmdAttr form-control tooltips input-sm" data-l1key="unite" style="margin-top: 10px;margin-right: 10px;width: 25%;display: inline-block;" placeholder="Unité" title="{{Unité de la donnée (Wh, A, kWh...) pour plus d\'informations aller voir le wiki}}">';
        if (init(_cmd.subType) == 'binary') {
          tr += '<span class="expertModeVisible"><input type="checkbox" class="cmdAttr" data-l1key="display" data-l2key="invertBinary" /> {{Inverser}}</span>';
        }
        if (init(_cmd.logicalId) == 'reel') {
          tr += '<span class="expertModeVisible"><input type="checkbox" class="cmdAttr" data-l1key="configuration" data-l2key="minValueReplace" value="1"/> {{Correction Min	 Auto}}<br>';
          tr += '<input type="checkbox" class="cmdAttr" data-l1key="configuration" data-l2key="maxValueReplace" value="1"/> {{Correction Max Auto}}<br></span>';
        }
        tr += '</td>';
        tr += '<td>';
        if (is_numeric(_cmd.id)) {
          tr += '<a class="btn btn-default btn-xs cmdAction expertModeVisible" data-action="configure"><i class="fa fa-cogs"></i></a> ';
          tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fa fa-rss"></i> {{Tester}}</a>';
        }
        tr += '<i class="fa fa-minus-circle pull-right cmdAction cursor" data-action="remove"></i>';
        tr += '</td>';
        table_cmd = '#table_cmd';
        if ( $(table_cmd+'_'+_cmd.eqType ).length ) {
          table_cmd+= '_'+_cmd.eqType;
        }
        $(table_cmd+' tbody').append(tr);
        $(table_cmd+' tbody tr:last').setValues(_cmd, '.cmdAttr');
    }
    if (init(_cmd.type) == 'action') {
        var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
        tr += '<td>';
        tr += '<span class="cmdAttr" data-l1key="id"></span>';
        tr += '</td>';
        tr += '<td>';
        tr += '<input class="cmdAttr form-control input-sm" data-l1key="name" style="width : 140px;" placeholder="{{Nom}}">';
        tr += '</td>';
        tr += '<td>';
        tr += '<input class="cmdAttr form-control type input-sm" data-l1key="type" value="action" disabled style="margin-bottom : 5px;" />';
        tr += '<span class="subType" subType="' + init(_cmd.subType) + '"></span>';
        tr += '<input class="cmdAttr" data-l1key="configuration" data-l2key="virtualAction" value="1" style="display:none;" >';
        tr += '</td>';
        tr += '<td>';
        tr += '<span><input type="checkbox" class="cmdAttr" data-l1key="isVisible" checked/> {{Afficher}}<br/></span>';
        tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="minValue" placeholder="{{Min}}" title="{{Min}}" style="width : 40%;display : none;">';
        tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="maxValue" placeholder="{{Max}}" title="{{Max}}" style="width : 40%;display : none;">';
        tr += '</td>';
        tr += '<td>';
        if (is_numeric(_cmd.id)) {
            tr += '<a class="btn btn-default btn-xs cmdAction expertModeVisible" data-action="configure"><i class="fa fa-cogs"></i></a> ';
            tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fa fa-rss"></i> {{Tester}}</a>';
        }
        tr += '<i class="fa fa-minus-circle pull-right cmdAction cursor" data-action="remove"></i>';
        tr += '</td>';
        tr += '</tr>';

        table_cmd = '#table_cmd';
        if ( $(table_cmd+'_'+_cmd.eqType ).length ) {
          table_cmd+= '_'+_cmd.eqType;
        }
        $(table_cmd+' tbody').append(tr);
        $(table_cmd+' tbody tr:last').setValues(_cmd, '.cmdAttr');
        var tr = $(table_cmd+' tbody tr:last');
        jeedom.eqLogic.builSelectCmd({
            id: $(".li_eqLogic.active").attr('data-eqLogic_id'),
            filter: {type: 'info'},
            error: function (error) {
              $('#div_alert').showAlert({message: error.message, level: 'danger'});
            },
            success: function (result) {
              tr.find('.cmdAttr[data-l1key=value]').append(result);
              tr.setValues(_cmd, '.cmdAttr');
            }
        });
    }
}

$("#table_cmd").sortable({axis: "y", cursor: "move", items: ".cmd", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});





