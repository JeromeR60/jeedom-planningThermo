<?php
/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

try {
  require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
  include_file('core', 'authentification', 'php');

  if (!isConnect('admin')) {
    throw new Exception(__('401 - Accès non autorisé', __FILE__));
  }

  ajax::init();

  if (init('action') == 'importModes') {
    $thermostat = eqLogic::byId(init('id'));
    if (!is_object($thermostat)) {
      throw new Exception(__('Thermostat introuvable', __FILE__) . ' : ' . init('id'));
    }

    $colors = array('confort' => '#F0997B', 'comfort' => '#F0997B', 'eco' => '#5DCAA5', 'nuit' => '#AFA9EC', 'night' => '#AFA9EC');
    $palette = array('#F0997B', '#5DCAA5', '#AFA9EC', '#EF9F27', '#85B7EB', '#ED93B1');
    $exclude = array('off', 'lock', 'unlock', 'thermostat', 'refresh');

    $modeCmds = array();
    foreach ($thermostat->getCmd('action') as $cmd) {
      if ($cmd->getLogicalId() == 'modeAction') {
        $modeCmds[] = $cmd;
      }
    }
    if (count($modeCmds) == 0) {
      // Repli : commandes action "autre" qui ne sont pas des commandes techniques
      foreach ($thermostat->getCmd('action') as $cmd) {
        if ($cmd->getSubType() == 'other' && !in_array(strtolower($cmd->getLogicalId()), $exclude)) {
          $modeCmds[] = $cmd;
        }
      }
    }

    $modes = array();
    $used = array();
    foreach ($modeCmds as $i => $cmd) {
      $name = $cmd->getName();
      $plain = strtoupper(iconv('UTF-8', 'ASCII//TRANSLIT', $name));
      $key = '';
      foreach (str_split(preg_replace('/[^A-Z0-9]/', '', $plain) . 'ABCDEFGHIJKLMNOPQRSTUVWXYZ') as $c) {
        if (!in_array($c, $used, true)) {
          $key = $c;
          break;
        }
      }
      $used[] = $key;
      $low = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $name));
      $modes[] = array(
        'k' => $key,
        'label' => $name,
        'color' => isset($colors[$low]) ? $colors[$low] : $palette[$i % count($palette)],
        'cmd' => '#' . $cmd->getHumanName() . '#',
      );
    }

    $result = array('modes' => $modes, 'temperature' => '', 'block' => '');
    foreach ($thermostat->getCmd('info') as $cmd) {
      if ($cmd->getLogicalId() == 'temperature') {
        $result['temperature'] = '#' . $cmd->getHumanName() . '#';
      }
      if ($cmd->getLogicalId() == 'lock_state') {
        $result['block'] = '#' . $cmd->getHumanName() . '#';
      }
    }
    ajax::success($result);
  }

  throw new Exception(__('Aucune méthode correspondante à', __FILE__) . ' : ' . init('action'));
} catch (Exception $e) {
  ajax::error(displayException($e), $e->getCode());
}
