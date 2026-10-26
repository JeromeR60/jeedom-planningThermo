<?php
/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

require_once __DIR__ . '/../../../../core/php/core.inc.php';

class planningThermo extends eqLogic {

  const SLOTS = 48;   // créneaux de 30 min par jour
  const LEN = 336;    // 7 jours x 48 créneaux

  public static $_widgetPossibility = array('custom' => true);

  /* ------------------------------------------------------------------ */
  /*  Cron : application du planning chaque minute                       */
  /* ------------------------------------------------------------------ */

  public static function cron() {
    foreach (eqLogic::byType(__CLASS__, true) as $eqLogic) {
      try {
        $eqLogic->applySchedule(false);
      } catch (Exception $e) {
        log::add(__CLASS__, 'error', $eqLogic->getHumanName() . ' : ' . $e->getMessage());
      }
    }
  }

  /* ------------------------------------------------------------------ */
  /*  Modes                                                              */
  /* ------------------------------------------------------------------ */

  public static function defaultModes() {
    return array(
      array('k' => 'C', 'label' => 'Confort', 'color' => '#F0997B', 'cmd' => ''),
      array('k' => 'E', 'label' => 'Eco', 'color' => '#5DCAA5', 'cmd' => ''),
      array('k' => 'N', 'label' => 'Nuit', 'color' => '#AFA9EC', 'cmd' => ''),
    );
  }

  public function getModes() {
    $modes = $this->getConfiguration('modes');
    if (!is_array($modes) || count($modes) == 0) {
      return self::defaultModes();
    }
    return array_values($modes);
  }

  public function getModeKeys() {
    $keys = array();
    foreach ($this->getModes() as $mode) {
      $keys[] = $mode['k'];
    }
    return $keys;
  }

  public function getModeByKey($_key) {
    foreach ($this->getModes() as $mode) {
      if ($mode['k'] === $_key) {
        return $mode;
      }
    }
    return null;
  }

  /* ------------------------------------------------------------------ */
  /*  Planning                                                           */
  /* ------------------------------------------------------------------ */

  public function defaultPlan() {
    $keys = $this->getModeKeys();
    $c = $keys[0];
    $e = isset($keys[1]) ? $keys[1] : $c;
    $n = isset($keys[2]) ? $keys[2] : $e;
    $plan = '';
    for ($d = 0; $d < 7; $d++) {
      for ($i = 0; $i < self::SLOTS; $i++) {
        if ($d < 5) {
          $plan .= $i < 12 ? $n : ($i < 16 ? $c : ($i < 34 ? $e : ($i < 46 ? $c : $n)));
        } else {
          $plan .= $i < 16 ? $n : ($i < 46 ? $c : $n);
        }
      }
    }
    return $plan;
  }

  /** Planning de 336 lettres, lettres inconnues remplacées par le premier mode */
  public function getPlan() {
    $plan = (string)$this->getConfiguration('planning', '');
    if (strlen($plan) != self::LEN) {
      return $this->defaultPlan();
    }
    $keys = $this->getModeKeys();
    $out = '';
    for ($i = 0; $i < self::LEN; $i++) {
      $out .= in_array($plan[$i], $keys, true) ? $plan[$i] : $keys[0];
    }
    return $out;
  }

  /** Dérogation active ou null */
  public function getDerog() {
    $derog = $this->getConfiguration('derog');
    if (is_array($derog) && isset($derog['k'], $derog['end'])
      && (int)$derog['end'] > time() && $this->getModeByKey($derog['k']) !== null) {
      return array('k' => $derog['k'], 'end' => (int)$derog['end']);
    }
    return null;
  }

  /** Format partagé avec le widget : <336 lettres>[|<lettre>|<timestamp fin>] */
  public function serializePlanning() {
    $str = $this->getPlan();
    $derog = $this->getDerog();
    if ($derog !== null) {
      $str .= '|' . $derog['k'] . '|' . $derog['end'];
    }
    return $str;
  }

  public static function slotIndex($_ts = null) {
    if ($_ts === null) $_ts = time();
    return ((int)date('N', $_ts) - 1) * self::SLOTS + (int)date('G', $_ts) * 2 + ((int)date('i', $_ts) >= 30 ? 1 : 0);
  }

  public static function slotStart($_ts = null) {
    if ($_ts === null) $_ts = time();
    return mktime((int)date('G', $_ts), (int)date('i', $_ts) >= 30 ? 30 : 0, 0, (int)date('n', $_ts), (int)date('j', $_ts), (int)date('Y', $_ts));
  }

  /** Nombre de créneaux jusqu'au prochain changement de lettre (0 si aucun) */
  public static function nextChange($_plan, $_idx) {
    $current = $_plan[$_idx];
    for ($n = 1; $n < self::LEN; $n++) {
      if ($_plan[($_idx + $n) % self::LEN] !== $current) {
        return $n;
      }
    }
    return 0;
  }

  public static function formatEnd($_end) {
    if ($_end <= 0) return '';
    $days = array('lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.', 'dim.');
    $txt = date('H:i', $_end);
    if (date('Y-m-d', $_end) != date('Y-m-d')) {
      $txt = $days[(int)date('N', $_end) - 1] . ' ' . $txt;
    }
    return $txt;
  }

  /** Mode cible courant : array(k, end, derog) */
  public function computeState() {
    $derog = $this->getDerog();
    if ($derog !== null) {
      return array('k' => $derog['k'], 'end' => $derog['end'], 'derog' => true);
    }
    $plan = $this->getPlan();
    $idx = self::slotIndex();
    $delta = self::nextChange($plan, $idx);
    return array(
      'k' => $plan[$idx],
      'end' => $delta > 0 ? self::slotStart() + $delta * 1800 : 0,
      'derog' => false,
    );
  }

  /* ------------------------------------------------------------------ */
  /*  Application                                                        */
  /* ------------------------------------------------------------------ */

  public function refreshInfos() {
    $state = $this->computeState();
    $mode = $this->getModeByKey($state['k']);
    $this->checkAndUpdateCmd('planning', $this->serializePlanning());
    $this->checkAndUpdateCmd('mode', $mode !== null ? $mode['label'] : $state['k']);
    $this->checkAndUpdateCmd('until', self::formatEnd($state['end']));
    $this->checkAndUpdateCmd('derog', $state['derog'] ? 1 : 0);
    $this->checkAndUpdateCmd('suspended', $this->getConfiguration('suspended', 0) ? 1 : 0);
    return $state;
  }

  /**
   * Applique le mode cible au thermostat.
   * Sans $_force, n'envoie un ordre que si le mode cible a changé depuis le dernier envoi :
   * un changement manuel sur le thermostat reste actif jusqu'au prochain créneau différent.
   */
  public function applySchedule($_force = false) {
    if ($this->getIsEnable() != 1) return;
    $state = $this->refreshInfos();

    if ($this->getConfiguration('suspended', 0)) {
      return;
    }

    $blockCmd = self::cmdFromConfig($this->getConfiguration('blockCmd', ''));
    if (is_object($blockCmd) && $blockCmd->execCmd()) {
      // Bloqué (ex. thermostat verrouillé) : on oublie le dernier mode pour réappliquer au déblocage
      $this->setCache('lastMode', '');
      return;
    }

    if (!$_force && $this->getCache('lastMode') === $state['k']) {
      return;
    }

    $mode = $this->getModeByKey($state['k']);
    $cmd = $mode !== null ? self::cmdFromConfig($mode['cmd']) : null;
    if (!is_object($cmd)) {
      log::add(__CLASS__, 'warning', $this->getHumanName() . ' Aucune commande valide pour le mode ' . $state['k']);
      return;
    }
    $cmd->execCmd();
    $this->setCache('lastMode', $state['k']);
    log::add(__CLASS__, 'info', $this->getHumanName() . ' Mode ' . $mode['label'] . ' appliqué ('
      . ($state['derog'] ? 'dérogation jusqu\'à ' . self::formatEnd($state['end']) : 'planning') . ')');
  }

  public static function cmdFromConfig($_value) {
    $id = trim(str_replace('#', '', (string)$_value));
    if ($id === '' || !is_numeric($id)) return null;
    $cmd = cmd::byId($id);
    return is_object($cmd) ? $cmd : null;
  }

  /* ------------------------------------------------------------------ */
  /*  Actions                                                            */
  /* ------------------------------------------------------------------ */

  public function setPlanningString($_str) {
    $parts = explode('|', trim((string)$_str));
    $plan = $parts[0];
    if (strlen($plan) != self::LEN) {
      throw new Exception(__('Planning invalide : 336 caractères attendus', __FILE__) . ' (' . strlen($plan) . ')');
    }
    $keys = $this->getModeKeys();
    for ($i = 0; $i < self::LEN; $i++) {
      if (!in_array($plan[$i], $keys, true)) {
        throw new Exception(__('Planning invalide : lettre de mode inconnue', __FILE__) . ' ' . $plan[$i]);
      }
    }
    $derog = '';
    if (count($parts) >= 3 && $this->getModeByKey($parts[1]) !== null && (int)$parts[2] > time()) {
      $derog = array('k' => $parts[1], 'end' => (int)$parts[2]);
    }
    $this->setConfiguration('planning', $plan);
    $this->setConfiguration('derog', $derog);
    $this->save(true);
    $this->applySchedule(false);
  }

  /** $_duration : minutes (1 à 1440) ou 'next' (jusqu'au prochain changement du planning) */
  public function setDerogation($_key, $_duration = 'next') {
    if ($this->getModeByKey($_key) === null) {
      throw new Exception(__('Mode inconnu', __FILE__) . ' : ' . $_key);
    }
    if ($_duration === 'next' || $_duration === '') {
      $plan = $this->getPlan();
      $delta = self::nextChange($plan, self::slotIndex());
      if ($delta == 0) {
        throw new Exception(__('Aucun changement dans le planning', __FILE__));
      }
      $end = self::slotStart() + $delta * 1800;
    } else {
      $minutes = (int)$_duration;
      if ($minutes < 1 || $minutes > 1440) {
        throw new Exception(__('Durée invalide (1 à 1440 min)', __FILE__) . ' : ' . $_duration);
      }
      $end = time() + $minutes * 60;
    }
    $this->setConfiguration('derog', array('k' => $_key, 'end' => $end));
    $this->save(true);
    $this->applySchedule(false);
  }

  public function cancelDerogation() {
    $this->setConfiguration('derog', '');
    $this->save(true);
    $this->applySchedule(false);
  }

  public function suspend() {
    $this->setConfiguration('suspended', 1);
    $this->save(true);
    $this->refreshInfos();
    log::add(__CLASS__, 'info', $this->getHumanName() . ' Planning suspendu');
  }

  public function resume() {
    $this->setConfiguration('suspended', 0);
    $this->save(true);
    $this->setCache('lastMode', '');
    log::add(__CLASS__, 'info', $this->getHumanName() . ' Planning repris');
    $this->applySchedule(true);
  }

  /* ------------------------------------------------------------------ */
  /*  Cycle de vie                                                       */
  /* ------------------------------------------------------------------ */

  public function preSave() {
    $modes = $this->getConfiguration('modes');
    $clean = array();
    $used = array();
    if (is_array($modes)) {
      foreach ($modes as $mode) {
        if (!is_array($mode)) continue;
        $key = strtoupper(substr(trim(isset($mode['k']) ? $mode['k'] : ''), 0, 1));
        $label = trim(isset($mode['label']) ? $mode['label'] : '');
        if ($key === '' || !ctype_alnum($key) || $label === '') continue;
        if (in_array($key, $used, true)) {
          throw new Exception(__('Lettre de mode utilisée deux fois', __FILE__) . ' : ' . $key);
        }
        $color = isset($mode['color']) && preg_match('/^#[0-9a-fA-F]{6}$/', $mode['color']) ? strtoupper($mode['color']) : '#999999';
        $used[] = $key;
        $clean[] = array('k' => $key, 'label' => $label, 'color' => $color, 'cmd' => isset($mode['cmd']) ? trim($mode['cmd']) : '');
      }
    }
    if (count($clean) == 0) {
      $clean = self::defaultModes();
    }
    $this->setConfiguration('modes', $clean);
  }

  public function postSave() {
    $order = 0;
    $planning = $this->createCmd('planning', __('Planning', __FILE__), 'info', 'string', $order++, true);
    $setPlanning = $this->createCmd('setPlanning', __('Set planning', __FILE__), 'action', 'message', $order++, false);
    $this->createCmd('mode', __('Mode actif', __FILE__), 'info', 'string', $order++, false);
    $this->createCmd('until', __('Fin du mode', __FILE__), 'info', 'string', $order++, false);
    $this->createCmd('derog', __('Dérogation active', __FILE__), 'info', 'binary', $order++, false);
    $suspended = $this->createCmd('suspended', __('Suspendu', __FILE__), 'info', 'binary', $order++, false);
    $this->createCmd('derogation', __('Dérogation', __FILE__), 'action', 'message', $order++, false);
    $this->createCmd('cancel', __('Annuler dérogation', __FILE__), 'action', 'other', $order++, false);
    $this->createCmd('suspend', __('Suspendre', __FILE__), 'action', 'other', $order++, false);
    $this->createCmd('resume', __('Reprendre', __FILE__), 'action', 'other', $order++, false);
    $this->createCmd('apply', __('Réappliquer', __FILE__), 'action', 'other', $order++, false);

    // Paramètres du widget, recalculés à chaque sauvegarde
    $modesParam = array();
    foreach ($this->getModes() as $mode) {
      $modesParam[] = $mode['k'] . ':' . str_replace(array(':', ';'), '', $mode['label']) . ':' . ltrim($mode['color'], '#');
    }
    $tempCmd = self::cmdFromConfig($this->getConfiguration('temperature', ''));
    $params = array(
      'set_id' => $setPlanning->getId(),
      'susp_id' => $suspended->getId(),
      'modes' => implode(';', $modesParam),
      'temp_id' => is_object($tempCmd) ? $tempCmd->getId() : '',
      'width' => (int)$this->getConfiguration('width', 360) ?: 360,
    );
    if ($planning->getDisplay('parameters') != $params) {
      $planning->setDisplay('parameters', $params);
      $planning->save();
    }

    if ($this->getIsEnable() == 1) {
      $this->setCache('lastMode', '');
      $this->applySchedule(false);
    }
  }

  private function createCmd($_logicalId, $_name, $_type, $_subType, $_order, $_visible) {
    $cmd = $this->getCmd(null, $_logicalId);
    if (!is_object($cmd)) {
      $cmd = new planningThermoCmd();
      $cmd->setLogicalId($_logicalId);
      $cmd->setEqLogic_id($this->getId());
      $cmd->setName($_name);
      $cmd->setType($_type);
      $cmd->setSubType($_subType);
      $cmd->setOrder($_order);
      $cmd->setIsVisible($_visible ? 1 : 0);
      if ($_type == 'info') {
        $cmd->setIsHistorized(0);
      }
      if ($_logicalId == 'planning') {
        $cmd->setTemplate('dashboard', 'planningThermo::planning');
        $cmd->setTemplate('mobile', 'planningThermo::planning');
        $cmd->setDisplay('showNameOndashboard', 0);
        $cmd->setDisplay('showNameOnmobile', 0);
      }
      if ($_logicalId == 'setPlanning') {
        $cmd->setDisplay('title_disable', 1);
      }
      if ($_logicalId == 'derogation') {
        $cmd->setDisplay('title_disable', 1);
        $cmd->setDisplay('message_placeholder', __('Mode|durée (ex. C|60 ou C|next)', __FILE__));
      }
      $cmd->save();
    }
    return $cmd;
  }
}

class planningThermoCmd extends cmd {

  public static $_widgetPossibility = array('custom' => true);

  public function dontRemoveCmd() {
    return true;
  }

  public function execute($_options = array()) {
    $eqLogic = $this->getEqLogic();
    $message = isset($_options['message']) ? trim($_options['message']) : '';
    switch ($this->getLogicalId()) {
      case 'setPlanning':
        $eqLogic->setPlanningString($message);
        break;
      case 'derogation':
        // "C|60", "C|next" ou "C" (= jusqu'au prochain changement)
        $parts = explode('|', $message);
        $eqLogic->setDerogation(strtoupper(trim($parts[0])), isset($parts[1]) ? trim($parts[1]) : 'next');
        break;
      case 'cancel':
        $eqLogic->cancelDerogation();
        break;
      case 'suspend':
        $eqLogic->suspend();
        break;
      case 'resume':
        $eqLogic->resume();
        break;
      case 'apply':
        $eqLogic->setCache('lastMode', '');
        $eqLogic->applySchedule(true);
        break;
    }
  }
}
