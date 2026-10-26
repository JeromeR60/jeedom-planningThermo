<?php
/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';

function planningThermo_install() {
}

function planningThermo_update() {
  // Recrée les commandes manquantes et remet à jour les paramètres du widget
  foreach (eqLogic::byType('planningThermo') as $eqLogic) {
    try {
      $eqLogic->save();
    } catch (Exception $e) {
      log::add('planningThermo', 'error', $eqLogic->getHumanName() . ' : ' . $e->getMessage());
    }
  }
}

function planningThermo_remove() {
}
