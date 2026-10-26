# Plugin Planning Thermostat

Planification hebdomadaire des modes d'un thermostat Jeedom, avec un widget graphique qu'on modifie directement depuis le Dashboard ou le mobile.

- Grille de 7 jours × 48 créneaux de 30 min, qu'on « peint » au doigt ou à la souris
- Copie d'un jour sur la semaine (lun→ven ou 7 jours)
- Dérogations temporaires (+1 h, +2 h, jusqu'au prochain changement) et annulation
- Suspension et reprise (mode vacances)
- Blocage par une info (ex. thermostat verrouillé)
- Un équipement par thermostat

## Configuration

1. **Plugins → Énergie → Planning Thermostat → Ajouter**.
2. Choisissez l'**objet parent**, puis le **thermostat** (plugin Thermostat), et cliquez sur **Importer**. Les modes du thermostat, sa température et son info de verrouillage sont remplis automatiquement.
3. Dans le tableau **Modes**, supprimez ceux qui ne doivent pas apparaître dans le planning (ex. Hors-gel, Off). Ajustez si besoin les lettres, les libellés et les couleurs.
4. Sauvegardez. Le widget apparaît sur le Dashboard avec un planning par défaut, à modifier directement.

Sans le plugin Thermostat, chaque mode peut pointer vers n'importe quelle commande action (sélecteur à droite du champ).

### Champs

| Champ | Rôle |
|---|---|
| Thermostat | Équipement source pour l'import (facultatif) |
| Température affichée | Info affichée en haut à droite du widget |
| Info de blocage | Si elle est vraie, aucun ordre n'est envoyé ; le mode du créneau est réappliqué au déblocage |
| Largeur du widget | 360 px par défaut |
| Modes | Lettre (utilisée dans le planning), libellé, couleur, commande à exécuter |

## Fonctionnement

Chaque minute, le plugin calcule le mode cible : la dérogation active s'il y en a une, sinon le mode du créneau courant.

Il n'envoie un ordre au thermostat **que lorsque le mode cible change**. Un changement manuel sur le thermostat reste donc actif jusqu'au prochain créneau différent. La commande **Réappliquer** force l'envoi du mode cible.

## Commandes

| Commande | Type | Usage |
|---|---|---|
| Planning | info | Planning et dérogation (porte le widget) |
| Set planning | action message | Remplace le planning (format ci-dessous) |
| Mode actif | info | Libellé du mode cible |
| Fin du mode | info | Heure de fin du mode cible (`23:00`, `lun. 06:00`) |
| Dérogation active | info binaire | |
| Suspendu | info binaire | |
| Dérogation | action message | `C\|60` (60 min), `C\|next` ou `C` (jusqu'au prochain changement) |
| Annuler dérogation | action | |
| Suspendre / Reprendre | action | Ex. depuis le scénario du mode vacances |
| Réappliquer | action | Force l'envoi du mode cible |

## Mode vacances

Dans votre scénario de départ, appelez **Suspendre**, puis passez le thermostat en Hors-gel. Au retour, appelez **Reprendre** : le mode du créneau courant est réappliqué immédiatement.

Si votre scénario verrouille déjà le thermostat et que l'info de verrouillage est renseignée dans « Info de blocage », il n'y a rien à ajouter : le planning se met en pause tout seul et reprend au déverrouillage.

## Format de l'info Planning

```
<336 lettres>[|<lettre dérogation>|<timestamp fin>]
```

Index d'un créneau : `jour × 48 + heure × 2 + (minutes ≥ 30 ? 1 : 0)`, avec jour 0 = lundi.

## Migration depuis le widget perso et le scénario

1. Copiez la valeur de l'ancienne info `Planning` (les 336 premiers caractères).
2. Dans le nouvel équipement, onglet **Commandes**, cliquez sur **Tester** sur `Set planning` et collez la valeur. Les lettres doivent correspondre à celles des modes.
3. Désactivez l'ancien scénario `Planning thermostats` et supprimez le virtuel, ainsi que les lignes de la variable `planningThermo_1492` dans le scénario vacances.
