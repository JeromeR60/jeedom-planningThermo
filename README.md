# Planning Thermostat – plugin Jeedom

Planification hebdomadaire des modes d'un thermostat (créneaux de 30 min) avec un widget graphique éditable, des dérogations et une suspension.

Documentation : [docs/fr_FR/index.md](docs/fr_FR/index.md)

## Installation depuis GitHub

1. Dans Jeedom, allez dans **Réglages → Système → Configuration → Mises à jour/Market**, onglet **GitHub**, et activez la source GitHub. Pour un dépôt privé, ajoutez un token.
2. Allez dans **Plugins → Gestion des plugins → + (Ajouter un plugin)** et renseignez :
   - Type de source : **GitHub**
   - ID logique du plugin : `planningThermo`
   - Utilisateur ou organisation : votre compte
   - Nom du dépôt : le nom de ce dépôt
   - Branche : `main`
3. Activez le plugin.

Requiert Jeedom 4.4 ou plus.
