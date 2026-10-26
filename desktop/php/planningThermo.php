<?php
if (!isConnect('admin')) {
  throw new Exception('{{401 - Accès non autorisé}}');
}
$plugin = plugin::byId('planningThermo');
sendVarToJS('eqType', $plugin->getId());
$eqLogics = eqLogic::byType($plugin->getId());

$thermostats = array();
try {
  $thermostats = eqLogic::byType('thermostat');
} catch (Exception $e) {
}
?>

<div class="row row-overflow">
  <!-- Page d'accueil du plugin -->
  <div class="col-xs-12 eqLogicThumbnailDisplay">
    <legend><i class="fas fa-cog"></i> {{Gestion}}</legend>
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
    </div>
    <legend><i class="fas fa-calendar-alt"></i> {{Mes plannings}}</legend>
    <?php
    if (count($eqLogics) == 0) {
      echo '<br><div class="text-center" style="font-size:1.2em;font-weight:bold;">{{Aucun planning, cliquez sur "Ajouter" pour commencer}}</div>';
    } else {
      echo '<div class="input-group" style="margin:5px;">';
      echo '<input class="form-control roundedLeft" placeholder="{{Rechercher}}" id="in_searchEqlogic">';
      echo '<div class="input-group-btn"><a id="bt_resetSearch" class="btn" style="width:30px"><i class="fas fa-times"></i></a></div>';
      echo '</div>';
      echo '<div class="eqLogicThumbnailContainer">';
      foreach ($eqLogics as $eqLogic) {
        $opacity = ($eqLogic->getIsEnable()) ? '' : 'disableCard';
        echo '<div class="eqLogicDisplayCard cursor ' . $opacity . '" data-eqLogic_id="' . $eqLogic->getId() . '">';
        echo '<img src="' . $plugin->getPathImgIcon() . '">';
        echo '<br>';
        echo '<span class="name">' . $eqLogic->getHumanName(true, true) . '</span>';
        echo '</div>';
      }
      echo '</div>';
    }
    ?>
  </div>

  <!-- Page de l'équipement -->
  <div class="col-xs-12 eqLogic" style="display: none;">
    <div class="input-group pull-right" style="display:inline-flex;">
      <span class="input-group-btn">
        <a class="btn btn-sm btn-default eqLogicAction roundedLeft" data-action="configure"><i class="fas fa-cogs"></i><span class="hidden-xs"> {{Configuration avancée}}</span></a>
        <a class="btn btn-sm btn-default eqLogicAction" data-action="copy"><i class="fas fa-copy"></i><span class="hidden-xs"> {{Dupliquer}}</span></a>
        <a class="btn btn-sm btn-success eqLogicAction" data-action="save"><i class="fas fa-check-circle"></i> {{Sauvegarder}}</a>
        <a class="btn btn-sm btn-danger eqLogicAction roundedRight" data-action="remove"><i class="fas fa-minus-circle"></i> {{Supprimer}}</a>
      </span>
    </div>
    <ul class="nav nav-tabs" role="tablist">
      <li role="presentation"><a href="#" class="eqLogicAction" aria-controls="home" role="tab" data-toggle="tab" data-action="returnToThumbnailDisplay"><i class="fas fa-arrow-circle-left"></i></a></li>
      <li role="presentation" class="active"><a href="#eqlogictab" aria-controls="home" role="tab" data-toggle="tab"><i class="fas fa-tachometer-alt"></i> {{Équipement}}</a></li>
      <li role="presentation"><a href="#commandtab" aria-controls="profile" role="tab" data-toggle="tab"><i class="fas fa-list"></i> {{Commandes}}</a></li>
    </ul>
    <div class="tab-content">
      <!-- Onglet équipement -->
      <div role="tabpanel" class="tab-pane active" id="eqlogictab">
        <form class="form-horizontal">
          <fieldset>
            <div class="col-lg-6">
              <legend><i class="fas fa-wrench"></i> {{Paramètres généraux}}</legend>
              <div class="form-group">
                <label class="col-sm-4 control-label">{{Nom du planning}}</label>
                <div class="col-sm-6">
                  <input type="text" class="eqLogicAttr form-control" data-l1key="id" style="display:none;">
                  <input type="text" class="eqLogicAttr form-control" data-l1key="name" placeholder="{{Planning SDB}}">
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-4 control-label">{{Objet parent}}</label>
                <div class="col-sm-6">
                  <select class="eqLogicAttr form-control" data-l1key="object_id">
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
                <label class="col-sm-4 control-label">{{Catégorie}}</label>
                <div class="col-sm-6">
                  <?php
                  foreach (jeedom::getConfiguration('eqLogic:category') as $key => $value) {
                    echo '<label class="checkbox-inline">';
                    echo '<input type="checkbox" class="eqLogicAttr" data-l1key="category" data-l2key="' . $key . '">' . $value['name'];
                    echo '</label>';
                  }
                  ?>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-4 control-label">{{Options}}</label>
                <div class="col-sm-6">
                  <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isEnable" checked>{{Activer}}</label>
                  <label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isVisible" checked>{{Visible}}</label>
                </div>
              </div>

              <legend><i class="fas fa-thermometer-half"></i> {{Thermostat}}</legend>
              <div class="form-group">
                <label class="col-sm-4 control-label">{{Thermostat}}
                  <sup><i class="fas fa-question-circle tooltips" title="{{Équipement du plugin Thermostat. Le bouton Importer remplit les modes, la température et le verrouillage.}}"></i></sup>
                </label>
                <div class="col-sm-5">
                  <select class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="thermostat">
                    <option value="">{{Aucun}}</option>
                    <?php
                    foreach ($thermostats as $thermostat) {
                      echo '<option value="' . $thermostat->getId() . '">' . $thermostat->getHumanName() . '</option>';
                    }
                    ?>
                  </select>
                </div>
                <div class="col-sm-3">
                  <a class="btn btn-default btn-sm" id="bt_planningThermoImport"><i class="fas fa-file-import"></i> {{Importer}}</a>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-4 control-label">{{Température affichée}}</label>
                <div class="col-sm-6">
                  <div class="input-group">
                    <input class="eqLogicAttr form-control roundedLeft" data-l1key="configuration" data-l2key="temperature" placeholder="{{Optionnel}}">
                    <span class="input-group-btn">
                      <a class="btn btn-default planningThermo_selCmd roundedRight" data-type="info"><i class="fas fa-list-alt"></i></a>
                    </span>
                  </div>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-4 control-label">{{Info de blocage}}
                  <sup><i class="fas fa-question-circle tooltips" title="{{Si cette info est vraie (ex. thermostat verrouillé), le planning n'envoie aucun ordre. Le mode du créneau est réappliqué dès le déblocage.}}"></i></sup>
                </label>
                <div class="col-sm-6">
                  <div class="input-group">
                    <input class="eqLogicAttr form-control roundedLeft" data-l1key="configuration" data-l2key="blockCmd" placeholder="{{Optionnel}}">
                    <span class="input-group-btn">
                      <a class="btn btn-default planningThermo_selCmd roundedRight" data-type="info"><i class="fas fa-list-alt"></i></a>
                    </span>
                  </div>
                </div>
              </div>
              <div class="form-group">
                <label class="col-sm-4 control-label">{{Largeur du widget (px)}}</label>
                <div class="col-sm-3">
                  <input type="number" min="300" max="1000" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="width" placeholder="360">
                </div>
              </div>
            </div>

            <div class="col-lg-6">
              <legend><i class="fas fa-palette"></i> {{Modes}}
                <a class="btn btn-success btn-xs pull-right" id="bt_planningThermoAddMode"><i class="fas fa-plus-circle"></i> {{Ajouter un mode}}</a>
              </legend>
              <div class="alert alert-info">
                {{Chaque mode a une lettre (utilisée dans le planning), un libellé, une couleur et la commande action du thermostat à exécuter. L'ordre du tableau est celui de la palette du widget ; le premier mode sert de mode par défaut.}}
              </div>
              <table class="table table-condensed" id="table_planningThermoModes">
                <thead>
                  <tr>
                    <th style="width:60px">{{Lettre}}</th>
                    <th>{{Libellé}}</th>
                    <th style="width:60px">{{Couleur}}</th>
                    <th>{{Commande}}</th>
                    <th style="width:120px"></th>
                  </tr>
                </thead>
                <tbody></tbody>
              </table>
            </div>
          </fieldset>
        </form>
      </div>

      <!-- Onglet commandes -->
      <div role="tabpanel" class="tab-pane" id="commandtab">
        <br>
        <div class="table-responsive">
          <table id="table_cmd" class="table table-bordered table-condensed">
            <thead>
              <tr>
                <th class="hidden-xs" style="width:80px">{{ID}}</th>
                <th style="min-width:200px">{{Nom}}</th>
                <th style="width:130px">{{Type}}</th>
                <th style="min-width:200px">{{Options}}</th>
                <th>{{État}}</th>
                <th style="width:130px">{{Actions}}</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<?php include_file('desktop', 'planningThermo', 'js', 'planningThermo'); ?>
<?php include_file('core', 'plugin.template', 'js'); ?>
