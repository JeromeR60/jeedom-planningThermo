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

    // Ordre : Confort, Eco, Nuit, puis les autres
    $rank = array('confort' => 0, 'comfort' => 0, 'eco' => 1, 'nuit' => 2, 'night' => 2);
    usort($modeCmds, function ($a, $b) use ($rank) {
      $ra = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $a->getName()));
      $rb = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $b->getName()));
      return (isset($rank[$ra]) ? $rank[$ra] : 9) - (isset($rank[$rb]) ? $rank[$rb] : 9);
    });

    $modes = array();
    $used = array();
    $usedColors = array();
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
      $color = isset($colors[$low]) ? $colors[$low] : '';
      if ($color === '' || in_array($color, $usedColors, true)) {
        $color = '#B4B2A9';
        foreach ($palette as $p) {
          if (!in_array($p, $usedColors, true) && !in_array($p, array_values($colors), true)) {
            $color = $p;
            break;
          }
        }
      }
      $usedColors[] = $color;
      $modes[] = array(
        'k' => $key,
        'label' => $name,
        'color' => $color,
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
