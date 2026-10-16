# Historique des versions

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
