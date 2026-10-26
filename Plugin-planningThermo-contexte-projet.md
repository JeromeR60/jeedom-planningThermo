# Plugin Jeedom « Planning Thermostat » – contexte projet

## Résumé

Plugin Jeedom perso de planification hebdomadaire des modes d'un thermostat (plugin Thermostat officiel). Il remplace le plugin Agenda, jugé trop figé.

- **Dépôt** : https://github.com/JeromeR60/jeedom-planningThermo (public, branche `main`)
- **Version en production** : 0.1.3 (26/10/2026), validée en conditions réelles. Version 0.1.4 (corrections du widget) prête, à publier sur GitHub
- **Jeedom** : 4.6.2, sur un NUC sous Debian 12
- **ID du plugin** : `planningThermo`, catégorie `wellness` (Confort)
- **Installation** : source GitHub dans Jeedom (Plugins → Gestion → + ; utilisateur `JeromeR60`, dépôt `jeedom-planningThermo`, branche `main`)
- **Mise à jour** : on glisse le contenu du dossier `planningThermo` dans GitHub (Add file → Upload files), puis on clique sur Mettre à jour dans Jeedom. Pour une petite modification, on édite le fichier au crayon sur GitHub.

## Installation actuelle

- Équipement « Planning SDB » (objet Plannification) qui pilote le thermostat `[RDC][Th. Salle de bain]`
- Modes, dans l'ordre : C Confort (`#F0997B`), E Eco (`#5DCAA5`), N Nuit (`#AFA9EC`)
- Température : `[RDC][Th. Salle de bain][Température]`
- Info de blocage : `[RDC][Th. Salle de bain][Verrouillage]`
- Le Hors-gel (commande `Vacances`) ne fait pas partie du planning. Le scénario vacances fait `Vacances` + `lock` au départ et `unlock` au retour. Le plugin voit le verrouillage et réapplique le mode du créneau au déverrouillage (aucune autre action à prévoir).
- Le thermostat virtuel est synchronisé avec un thermostat physique Bosch (jeezigbee) par un scénario séparé.
- L'ancien système (virtuel `Planning SDB` avec infos 1492/1497, widget perso `cmd.info.string.planningThermo.html`, scénario `Planning thermostats`, variable `planningThermo_1492`) est désactivé et conservé pour un éventuel retour arrière.

## Arborescence

```
plugin_info/info.json, install.php, configuration.php, planningThermo_icon.png
core/class/planningThermo.class.php     logique (eqLogic + cmd)
core/ajax/planningThermo.ajax.php       importModes (import depuis un thermostat)
core/template/{dashboard,mobile}/cmd.info.string.planning.html   widget
desktop/php/planningThermo.php          page de l'équipement
desktop/js/planningThermo.js            table des modes, import, addCmdToTable
docs/fr_FR/index.md, README.md
```

## Fonctionnement

- **Planning** : 7 jours × 48 créneaux de 30 min, soit 336 lettres stockées en configuration (`planning`). Index d'un créneau : `jour×48 + heure×2 + (min≥30)`, avec jour 0 = lundi.
- **Dérogation** : en configuration `derog` = `{k, end}` (timestamp en secondes). Une dérogation expirée est ignorée.
- **Format de l'info `Planning`**, partagé avec le widget : `<336 lettres>[|<lettre>|<timestamp fin>]`
- **`cron()`** chaque minute : calcule le mode cible (dérogation active, sinon créneau courant). Un ordre n'est envoyé **que si le mode cible change** (cache `lastMode`). Un changement manuel sur le thermostat reste donc actif jusqu'au créneau suivant, et ce comportement est voulu.
- **Info de blocage** vraie : aucun ordre n'est envoyé et `lastMode` est vidé, pour réappliquer le mode au déblocage. Suspendu : aucun ordre.
- **`postSave`** : crée les commandes manquantes, recalcule les paramètres du widget (`set_id`, `susp_id`, `modes`, `temp_id`, `width`) et réapplique le mode.
- **Import des modes** : commandes du thermostat dont le logicalId est `modeAction` (repli : actions « other » non techniques), triées Confort/Eco/Nuit, avec des couleurs distinctes. Température : logicalId `temperature`. Verrouillage : `lock_state`. Validé sur le thermostat de l'utilisateur.

## Commandes de l'équipement

| logicalId | Type | Rôle |
|---|---|---|
| planning | info string | Planning + dérogation, porte le widget `planningThermo::planning` |
| setPlanning | action message | Remplace le planning (format ci-dessus) |
| mode / until | info string | Libellé du mode cible / heure de fin (`23:00`, `lun. 06:00`) |
| derog / suspended | info binary | |
| derogation | action message | `C\|60`, `C\|next` ou `C` |
| cancel / suspend / resume / apply | action other | Annuler / suspendre / reprendre / forcer l'envoi |

## Widget

Grille à peindre, choix du jour, palette des modes sur une ligne, boutons Lun→ven, 7 jours, +1 h, +2 h, Jusqu'au suivant, Annuler. Il affiche la température, le statut (« Eco jusqu'à 23:00 », « Dérogation… », « Planning suspendu ») et se met à jour en direct. Largeur de 360 px par défaut.

## Historique

- 0.1.0 : première version
- 0.1.1 : réordonnancement des modes (↑↓), import trié, couleurs distinctes
- 0.1.2 : catégorie Confort (`wellness`)
- 0.1.3 : liens documentation et changelog dans `info.json`
- 0.1.4 : widget – resynchronisation après un échec d'enregistrement (le widget ne restait plus figé), jour affiché dans le statut d'une dérogation qui se termine un autre jour, planning par défaut calculé comme côté PHP (1er/2e/3e mode au lieu des lettres C/E/N)

## Pistes d'évolution

- Le M5Dial (M5Stack, firmware « Bouton Salon » 2.8, MQTT via MQTT Manager) pourra créer des dérogations avec la commande `derogation` (`C|60`, `N|next`) et afficher `mode` et `until`.
- Ajouter d'autres thermostats : un équipement par thermostat.
- Idées : plannings types à appliquer d'un clic, affichage du statut suspendu dans le widget à affiner, publication sur le Market.
