/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

function planningThermoAlert(message, level) {
  if (typeof jeedomUtils !== 'undefined' && typeof jeedomUtils.showAlert === 'function') {
    jeedomUtils.showAlert({message: message, level: level || 'danger'})
  } else if ($.fn.showAlert) {
    $('#div_alert').showAlert({message: message, level: level || 'danger'})
  } else {
    alert(message)
  }
}

/* ---------- Table des modes ---------- */

function planningThermoAddMode(mode) {
  mode = mode || {k: '', label: '', color: '#999999', cmd: ''}
  var tr = '<tr class="planningThermoMode">'
  tr += '<td><input class="form-control input-sm" data-key="k" maxlength="1" style="text-transform:uppercase;text-align:center"></td>'
  tr += '<td><input class="form-control input-sm" data-key="label"></td>'
  tr += '<td><input type="color" data-key="color" style="width:40px;height:28px;padding:0;border:none;background:none;cursor:pointer"></td>'
  tr += '<td><div class="input-group">'
  tr += '<input class="form-control input-sm roundedLeft" data-key="cmd" placeholder="{{Commande action du thermostat}}">'
  tr += '<span class="input-group-btn"><a class="btn btn-default btn-sm planningThermo_selCmd roundedRight" data-type="action"><i class="fas fa-list-alt"></i></a></span>'
  tr += '</div></td>'
  tr += '<td style="white-space:nowrap">'
  tr += '<a class="btn btn-default btn-sm planningThermo_upMode" title="{{Monter}}"><i class="fas fa-arrow-up"></i></a> '
  tr += '<a class="btn btn-default btn-sm planningThermo_downMode" title="{{Descendre}}"><i class="fas fa-arrow-down"></i></a> '
  tr += '<a class="btn btn-danger btn-sm planningThermo_removeMode"><i class="fas fa-minus-circle"></i></a>'
  tr += '</td>'
  tr += '</tr>'
  var $tr = $(tr)
  $tr.find('[data-key=k]').val(mode.k || '')
  $tr.find('[data-key=label]').val(mode.label || '')
  $tr.find('[data-key=color]').val(/^#[0-9a-fA-F]{6}$/.test(mode.color || '') ? mode.color : '#999999')
  $tr.find('[data-key=cmd]').val(mode.cmd || '')
  $('#table_planningThermoModes tbody').append($tr)
}

function printEqLogic(_eqLogic) {
  $('#table_planningThermoModes tbody').empty()
  var modes = (_eqLogic.configuration && Array.isArray(_eqLogic.configuration.modes)) ? _eqLogic.configuration.modes : []
  if (modes.length == 0) {
    modes = [
      {k: 'C', label: 'Confort', color: '#F0997B', cmd: ''},
      {k: 'E', label: 'Eco', color: '#5DCAA5', cmd: ''},
      {k: 'N', label: 'Nuit', color: '#AFA9EC', cmd: ''}
    ]
  }
  modes.forEach(function (mode) { planningThermoAddMode(mode) })
}

function saveEqLogic(_eqLogic) {
  if (!_eqLogic.configuration) _eqLogic.configuration = {}
  var modes = []
  $('#table_planningThermoModes tbody tr.planningThermoMode').each(function () {
    modes.push({
      k: ($(this).find('[data-key=k]').val() || '').toUpperCase(),
      label: $(this).find('[data-key=label]').val(),
      color: $(this).find('[data-key=color]').val(),
      cmd: $(this).find('[data-key=cmd]').val()
    })
  })
  _eqLogic.configuration.modes = modes
  return _eqLogic
}

$(document).off('click.planningThermo')

$(document).on('click.planningThermo', '#bt_planningThermoAddMode', function () {
  planningThermoAddMode()
})

$(document).on('click.planningThermo', '.planningThermo_upMode', function () {
  var tr = $(this).closest('tr')
  tr.prev('tr').before(tr)
})

$(document).on('click.planningThermo', '.planningThermo_downMode', function () {
  var tr = $(this).closest('tr')
  tr.next('tr').after(tr)
})

$(document).on('click.planningThermo', '.planningThermo_removeMode', function () {
  $(this).closest('tr').remove()
})

$(document).on('click.planningThermo', '.planningThermo_selCmd', function () {
  var input = $(this).closest('.input-group').find('input')
  jeedom.cmd.getSelectModal({cmd: {type: $(this).data('type')}}, function (result) {
    input.val(result.human)
  })
})

$(document).on('click.planningThermo', '#bt_planningThermoImport', function () {
  var id = $('.eqLogicAttr[data-l1key=configuration][data-l2key=thermostat]').val()
  if (!id) {
    planningThermoAlert('{{Choisissez d\'abord un thermostat}}', 'warning')
    return
  }
  $.ajax({
    type: 'POST',
    url: 'plugins/planningThermo/core/ajax/planningThermo.ajax.php',
    data: {action: 'importModes', id: id},
    dataType: 'json',
    error: function (request) {
      planningThermoAlert(request.responseText)
    },
    success: function (data) {
      if (data.state != 'ok') {
        planningThermoAlert(data.result)
        return
      }
      if (data.result.modes.length == 0) {
        planningThermoAlert('{{Aucun mode trouvé sur ce thermostat}}', 'warning')
        return
      }
      $('#table_planningThermoModes tbody').empty()
      data.result.modes.forEach(function (mode) { planningThermoAddMode(mode) })
      if (data.result.temperature) {
        $('.eqLogicAttr[data-l1key=configuration][data-l2key=temperature]').val(data.result.temperature)
      }
      if (data.result.block) {
        $('.eqLogicAttr[data-l1key=configuration][data-l2key=blockCmd]').val(data.result.block)
      }
      planningThermoAlert('{{Modes importés : supprimez ceux à exclure du planning (ex. Hors-gel, Off), puis sauvegardez.}}', 'success')
    }
  })
})

/* ---------- Table des commandes ---------- */

function addCmdToTable(_cmd) {
  if (!isset(_cmd)) {
    var _cmd = {configuration: {}}
  }
  if (!isset(_cmd.configuration)) {
    _cmd.configuration = {}
  }
  var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">'
  tr += '<td class="hidden-xs"><span class="cmdAttr" data-l1key="id"></span></td>'
  tr += '<td>'
  tr += '<input class="cmdAttr form-control input-sm" data-l1key="name" placeholder="{{Nom de la commande}}">'
  tr += '</td>'
  tr += '<td>'
  tr += '<input class="cmdAttr" data-l1key="type" style="display:none">'
  tr += '<input class="cmdAttr" data-l1key="subType" style="display:none">'
  tr += '<span>' + init(_cmd.type) + ' / ' + init(_cmd.subType) + '</span>'
  tr += '</td>'
  tr += '<td>'
  tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="isVisible" checked>{{Afficher}}</label> '
  if (init(_cmd.type) == 'info' && init(_cmd.subType) != 'string') {
    tr += '<label class="checkbox-inline"><input type="checkbox" class="cmdAttr" data-l1key="isHistorized">{{Historiser}}</label>'
  }
  tr += '</td>'
  tr += '<td><span class="cmdAttr" data-l1key="htmlstate"></span></td>'
  tr += '<td>'
  if (is_numeric(_cmd.id)) {
    tr += '<a class="btn btn-default btn-xs cmdAction" data-action="configure"><i class="fas fa-cogs"></i></a> '
    tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fas fa-rss"></i> {{Tester}}</a>'
  }
  tr += '</td>'
  tr += '</tr>'
  $('#table_cmd tbody').append(tr)
  var $tr = $('#table_cmd tbody tr').last()
  $tr.setValues(_cmd, '.cmdAttr')
}
