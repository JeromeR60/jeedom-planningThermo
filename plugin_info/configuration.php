<?php
require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';
include_file('core', 'authentification', 'php');
if (!isConnect()) {
  include_file('desktop', '404', 'php');
  die();
}
?>
<form class="form-horizontal">
  <fieldset>
    <div class="form-group">
      <div class="col-sm-12">
        <span>{{Aucune configuration globale : tout se règle dans chaque équipement (Plugins → Confort → Planning Thermostat).}}</span>
      </div>
    </div>
  </fieldset>
</form>
