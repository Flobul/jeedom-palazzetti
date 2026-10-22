# Archive WordPress non normalisée

> Archive historique conservée littéralement depuis WordPress. Les titres, versions dupliquées, textes barrés, dates invalides et images ne constituent pas des entrées de Release structurées.

## Non publié

## 2.1.0 (16/10/2026) (beta)

- Refonte de la structure du code afin d'améliorer sa lisibilité et sa maintenabilité

## 2.0.2 (30/08/2026) (beta)

- Correction de la fenêtre de découverte vide lors de sa réouverture.
- Correction du verrou de découverte qui pouvait rester actif après la fin de la recherche.

## 2.0.1 (30/08/2026) (beta)

- Découverte manuelle traitée appareil par appareil grâce à des actions placées directement dans chaque ligne du tableau.
- La fenêtre reste ouverte pour créer ou actualiser plusieurs équipements successivement, puis recharge la page à sa fermeture si nécessaire.
- Suppression des actions globales ambiguës et ciblage sécurisé de chaque passerelle par son adresse MAC ou, à défaut, son adresse IP.

## 2.0.0 (29/08/2026) (beta)

- Passage de la beta en stable.

- Ajout d'un historique de chauffe sur 1 à 31 jours : chronogramme des cycles, températures, consigne, puissance, ventilation et comparaison quotidienne de la durée et des pellets consommés.
- Accès direct à l'historique du bon équipement depuis les widgets dashboard et mobile.
- Ajout d'une infobulle sur les cartes d'équipement avec les principales informations du poêle et de sa connexion.
- Refonte de la découverte manuelle avec aperçu des appareils et choix entre mise à jour, remplacement de l'équipement ou annulation.
- La découverte automatique actualise uniquement les équipements connus afin d'éviter la création de doublons.
- Rétablissement de la collecte périodique complète (GET TIME, GET STDT, GET CHRD, GET CNTR et GET ALLS) et maintien du secours EXT ADRD.
- Page Santé enrichie et distinction entre une passerelle hors ligne et un poêle indisponible derrière une passerelle joignable.
- Redécouverte automatique temporisée après plusieurs échecs réseau, notamment en cas de changement d'adresse DHCP.
- Validation renforcée des commandes, des réponses, des équipements AJAX et des adresses réseau locales.
- Écritures PARM/HPAR désactivées par défaut, limitées à 0…255 et journalisées lorsqu'elles sont activées.
- Passage des widgets en JavaScript natif, sécurisation des valeurs injectées et suppression des rafraîchissements complets inutiles.
- Corrections des durées, des compteurs de surchauffes, des métadonnées et de l'affichage de la page Santé.
- Activation de l'historisation sans lissage des commandes nécessaires au calcul des cycles et des consommations quotidiennes.
- Découverte automatique désactivée par défaut après migration ; elle doit être réactivée explicitement si elle est souhaitée.
- Ajout de tests automatisés, d'un workflow de qualité et du PHPDoc sur le code PHP.

## 1.23 (27/08/2026) (beta)

- Ajout de la santé et de la découverte automatique
- Corrige l’envoi de 'undefined' lors du chargement complet des paramètres PARM et HPAR
- Ajoute une page Santé avec l’état du cron, des sockets UDP et des équipements
- Affiche la dernière communication, les erreurs et les tentatives de redécouverte
- Ajoute un bouton de découverte UDP des Connection Box et WPalaControl
- Crée ou actualise les équipements découverts par MAC, numéro de série ou IP
- Ajoute la configuration de sous-réseaux CIDR pour la découverte inter-réseaux
- Ajoute une découverte automatique avec intervalle configurable
- Déclenche une redécouverte après trois requêtes consécutives en échec
- Met automatiquement à jour l’adresse IP des équipements utilisant DHCP
- Ajoute une temporisation pour éviter les redécouvertes répétées
- Corrige l’affichage des icônes Jeedom dans la page Santé

## 1.22 (21/07/2026) (beta)

- Amélioration de la gestion des erreurs de requêtes.

## 1.21 (19/07/2026) (beta)

- Passage en JS Vanilla.

## 1.20 (13/02/2024) (beta)

- Suppression du bouton des commentaires statiques sur la page paramètres (WPalaControl)
- Correction de la sauvegarde des commentaires.

## 1.11 (03/02/2024) (beta)

- Correction de l'envoi de modification des paramètres (SET+PARM) (WPalaControl)
- Ajout des unités, commentaire constructeur, et amélioration d'affichage sur petits appareils pour la page Paramètres. (WPalaControl)
- Ajout d'un champ "Dernière communication de l'appareil".
- Correction de l'ouverture des modales Paramètres et Paramètres cachés. (WPalaControl)
- Correction du cron (resté à 5 minutes)

## 1.10 (29/12/2023) (beta)

- Amélioration du code.
- Amélioration détection WPalaControl (pour menus paramètres et paramètres cachés du poêle).
- Possibilité de créer une commande action et info liée avec une requête entrée manuellement :

![](https://flobul-domotique.fr/wp-content/uploads/2023/12/Capture-decran-2023-12-28-a-12.42.23-1024x84.png)

## 1.04 (09/12/2023) (beta)

- Ajout d'une option pour inverser OFF et AUTO sur la vitesse de ventilation.

## 1.03 (21/02/2023)

- Refonte complète en stable : la beta devient la stable.

## 1.02 (11/02/2023) (beta)

- Refonte complète en beta.

## 1.2 (03/01/2022)

- Retours en arrière de la dernière mise à jour suite à des dysfonctionnements majeurs

## ~2.0 (28/21/2021) Fait par @Flobul~

- ~Ajout la possibilité de choisir un cron (cron 15 était trop long), cron15 par défaut à la première configuration.~
- ~Case à cocher pour PalaControl.~
- ~Gestion des commandes par fichier de configuration json.~
- ~Méthode getInformations lancée qu'en cas d'équipement actif.~
- ~Passage de l'équipement en inactif en cas de 3 erreurs de récupération des informations.~
- ~Ajout des champs min/max/unité sur la page équipement.~
- ~Ajout d'une case pour activer ou non le template de l'équipement (dashboard).~

## 1.2 (26/01/2020 à 14h40)

- Ajout d'une gestion des erreur sur les requette
- Mise a jours des template

## 1.1 (05/10/2020 à 13h25)

- Correction du widget mobile
- Mise a jour de font (Merci @JAG)
- Update Github : https://github.com/DomotechDiscord/Jeedom_Palazzetti

## *** (02/08/2020)

- Update Lien docs
- Update Repository

## 1.0 (14/07/2020 à 23h42)

- Mise en place des modification de @pm2036 pour la prise en compte du nouveaux firmware.
- Application des modifications de @JAG pour la prise en compte de PHP 7.3.
- Mise a jour des liens de documentation
- Mise a jour des visuels
- Refonte du widget By Domotech.
- Changement de repository GitHub : https://github.com/DomotechDiscord/Jeedom_Palazzetti

## 0.1 (01/06/2020 à 17h00)

- Deplacement de l'ancien repo au nouveaux
