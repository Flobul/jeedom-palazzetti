# Historique des versions

## Non publié

## Version 2.1.3 (27/10/2026)

- Détection des équipements Tasmota WPalaControl via la commande HTTP `Palazzetti` du driver Tasmota.
- Les boutons **Paramètres du poêle** et **Paramètres cachés du poêle** s'affichent maintenant pour les équipements Tasmota-WPalaControl.
- Correction de `buildCommandString()` : les commandes action utilisent strictement les conventions Jeedom (`slider`, `message`) et ignorent les métadonnées de contexte (`user_login`, `user_id`, `tags`, etc.) ; toute option métier inconnue est rejetée.
- `SET+LABL+` accepte un `message` vide et utilise le sous-type Jeedom `message` ; les paramètres spécifiques au planning restent limités aux commandes internes de la fenêtre de planning.
- Le fallback de détection WPalaControl accepte toute réponse contenant `palacontrol` (ex: `TasmoPalaControl`), pas seulement `WPalaControl`.

## Version 2.1.2 (27/10/2026)

- Correction : les commandes lancées depuis un scénario ou une interaction utilisent les options Jeedom attendues (`slider`, `message`) et ignorent les métadonnées de contexte (`user_login`, `user_id`, `tags`, etc.).
- La commande d'écriture du nom utilise désormais le sous-type Jeedom `message` ; les paramètres spécifiques au planning restent limités aux commandes internes de la fenêtre de planning.
- Correction : les commandes action utilisent désormais strictement les conventions Jeedom (`slider`, `message`, métadonnées de contexte ignorées) et rejettent toute option métier inconnue.
- Amélioration : la commande d'écriture du nom accepte un `message` vide et utilise le sous-type Jeedom `message` ; les paramètres spécifiques au planning restent limités aux commandes internes de la fenêtre de planning.

## Version 2.1.1 (20/10/2026)

- Correction : les commandes d'action simples (Allumage, Extinction, etc.) étaient bloquées par un rejet d'options inconnues, car Jeedom injecte systématiquement des métadonnées d'exécution (`user_login`, `user_id`) dans les options transmises.

## 2.0.1

- Découverte manuelle traitée appareil par appareil grâce à des actions placées directement dans chaque ligne du tableau.
- La fenêtre reste ouverte pour créer ou actualiser plusieurs équipements successivement, puis recharge la page à sa fermeture si nécessaire.
- Suppression des actions globales ambiguës et ciblage sécurisé de chaque passerelle par son adresse MAC ou, à défaut, son adresse IP.
- Correction de la fenêtre de découverte vide lors de sa réouverture.
- Correction du verrou de découverte qui pouvait rester actif après la fin de la recherche.

## 2.0.0

### Nouveautés

- Ajout d'un historique de chauffe sur 1 à 31 jours : chronogramme des cycles, températures, consigne, puissance, ventilation et comparaison quotidienne de la durée et des pellets consommés.
- Accès direct à l'historique du bon équipement depuis les widgets dashboard et mobile.
- Ajout d'une infobulle sur les cartes d'équipement avec les principales informations du poêle et de sa connexion.
- Refonte de la découverte manuelle avec aperçu et actions par appareil, sans fermer la fenêtre entre deux créations.
- La découverte automatique actualise uniquement les équipements connus afin d'éviter la création de doublons.

### Fiabilité et sécurité

- Rétablissement de la collecte périodique complète (`GET TIME`, `GET STDT`, `GET CHRD`, `GET CNTR` et `GET ALLS`) et maintien du secours `EXT ADRD`.
- Page Santé enrichie et distinction entre une passerelle hors ligne et un poêle indisponible derrière une passerelle joignable.
- Redécouverte automatique temporisée après plusieurs échecs réseau, notamment en cas de changement d'adresse DHCP.
- Validation renforcée des commandes, des réponses, des équipements AJAX et des adresses réseau locales.
- Écritures PARM/HPAR désactivées par défaut, limitées à `0…255` et journalisées lorsqu'elles sont activées.
- Passage des widgets en JavaScript natif, sécurisation des valeurs injectées et suppression des rafraîchissements complets inutiles.
- Corrections des durées, des compteurs de surchauffes, des métadonnées et de l'affichage de la page Santé.

### Migration

- Activation de l'historisation sans lissage des commandes nécessaires au calcul des cycles et des consommations quotidiennes.
- Découverte automatique désactivée par défaut après migration ; elle doit être réactivée explicitement si elle est souhaitée.
- Ajout d'un workflow de contrôle qualité et du PHPDoc sur le code PHP.
