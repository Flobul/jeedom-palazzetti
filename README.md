# Palazzetti pour Jeedom

[![Licence](https://img.shields.io/badge/Licence-GPL--3.0-blue?style=flat-square)](https://github.com/Flobul/jeedom-palazzetti/blob/HEAD/LICENSE)
[![Langage](https://img.shields.io/badge/Langage-PHP-informational?style=flat-square)](https://github.com/Flobul/jeedom-palazzetti)
[![Dernière mise à jour](https://img.shields.io/badge/Derniere_mise_a_jour-30/08/2026-yellow?style=flat-square)](https://github.com/Flobul/jeedom-palazzetti/commits)
[![Tickets ouverts](https://img.shields.io/badge/Tickets_ouverts-0-brightgreen?style=flat-square)](https://github.com/Flobul/jeedom-palazzetti/issues)
[![Demandes de fusion ouvertes](https://img.shields.io/badge/Demandes_de_fusion_ouvertes-0-brightgreen?style=flat-square)](https://github.com/Flobul/jeedom-palazzetti/pulls)

<!-- market-badges:start -->
[![Note](https://img.shields.io/badge/Note-5%2F5-brightgreen?style=flat-square)](https://market.jeedom.com/index.php?v=d&p=market_display&id=3104)
[![Prix](https://img.shields.io/badge/Prix-Gratuit-brightgreen?style=flat-square)](https://market.jeedom.com/index.php?v=d&p=market_display&id=3104)
[![Achats](https://img.shields.io/badge/Achats-23-blueviolet?style=flat-square)](https://market.jeedom.com/index.php?v=d&p=market_display&id=3104)
[![Téléchargements](https://img.shields.io/badge/T%C3%A9l%C3%A9chargements-2%20645-informational?style=flat-square)](https://market.jeedom.com/index.php?v=d&p=market_display&id=3104)
[![Commentaires](https://img.shields.io/badge/Commentaires-0-lightgrey?style=flat-square)](https://market.jeedom.com/index.php?v=d&p=market_display&id=3104)
<!-- market-badges:end -->

Plugin Jeedom pour superviser et piloter les poêles Palazzetti.

## Version 2

- Les cartes d'équipement donnent au survol un aperçu de la connexion, de l'état du poêle, des températures, de la puissance et de la ventilation.
- La modale **Historique de chauffe** affiche jusqu'à 31 jours de données Jeedom : chronogramme des cycles, température ambiante et consigne, aires de puissance et de ventilation limitées aux périodes allumées, puis comparaison quotidienne de la durée et des pellets consommés.
- Le plugin ne crée aucune base de mesures supplémentaire. Il utilise les historiques des commandes `IStatus`, `ITemp`, `IConsigne`, `IPower`, `IFan`, `ITemp2`, `ITemp3`, `IQuantite` et `IHeuresChauffe`, avec un plafonnement des points envoyés au navigateur.

Lors du passage en `2.0.0`, l'historisation de l'état `IStatus` est activée sans lissage afin de conserver les transitions exactes qui délimitent les cycles. Les compteurs `IQuantite` et `IHeuresChauffe` sont également conservés sans lissage pour calculer des écarts journaliers fiables. Les périodes horaires complètes ne sont donc disponibles qu'à partir de cette mise à jour, tandis que les barres quotidiennes peuvent réutiliser les historiques antérieurs des compteurs.

## Fiabilité et sécurité

- Chaque rafraîchissement configuré relit `GET TIME`, `GET STDT`, `GET CHRD`, `GET CNTR` et `GET ALLS`. Si `GET TIME` échoue, le cycle s'arrête immédiatement pour éviter une série de timeouts.
- La santé distingue une passerelle réseau hors ligne d'un poêle indisponible derrière une passerelle joignable.
- La découverte automatique actualise uniquement les équipements reconnus et ignore les appareils inconnus : elle ne crée donc aucun doublon. La découverte manuelle affiche les informations détectées et permet de créer, actualiser ou remplacer chaque appareil séparément sans fermer la fenêtre.
- Les commandes et adresses sont validées côté serveur. Les écritures PARM/HPAR sont désactivées par défaut, limitées à `0…255` faute de schéma fiable par modèle, et journalisées lorsqu'elles sont activées en mode expert.

## Sécurité

Consultez la [politique de sécurité](SECURITY.md) avant de transmettre un rapport ou des journaux. Ne publiez jamais de secret, de jeton ou de mot de passe dans une issue publique.

## Licence

Ce projet est distribué sous licence **GPL-3.0**. Consultez le fichier [LICENSE](LICENSE) pour le texte complet.

---

<p align="center">
  <a href="https://github.com/sponsors/Flobul">
    <img src="https://img.shields.io/badge/Sponsoriser%20ce%20projet-%E2%9D%A4-EA4AAA?style=for-the-badge&logo=githubsponsors" alt="Sponsoriser ce projet sur GitHub">
  </a>
</p>
