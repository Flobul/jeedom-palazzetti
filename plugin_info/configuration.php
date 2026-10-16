<?php
/* This file is part of Jeedom. */
require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';
include_file('core', 'authentification', 'php');
if (!isConnect('admin')) {
    include_file('desktop', '404', 'php');
    die();
}
$plugin = plugin::byId('Palazzetti');
$update = $plugin->getUpdate();
?>
<?php include_file('desktop', 'plugin-banner', 'css', 'Palazzetti'); ?>

<form class="form-horizontal" id="configuration_plugin_palazzetti">
    <div class="Palazzetti-config-page">
        <header class="Palazzetti-config-hero">
            <div class="Palazzetti-config-identity">
                <div class="Palazzetti-config-logo"><img src="plugins/Palazzetti/plugin_info/palazzetti_icon.png" alt="Palazzetti"></div>
                <div><span class="Palazzetti-config-eyebrow">{{Configuration du plugin}}</span><h2>Palazzetti</h2><p>{{Connectez vos poêles Palazzetti à Jeedom et pilotez-les via Connection Box ou PalaControl.}}</p></div>
            </div>
            <div class="Palazzetti-config-meta">
                <span><i class="fas fa-code-branch"></i> {{Version}} <strong>v<?php echo htmlspecialchars((string) Palazzetti::$_pluginVersion, ENT_QUOTES, 'UTF-8'); ?></strong></span>
                <?php if (is_object($update)) { ?>
                    <span><i class="fas fa-calendar-alt"></i> <?php echo htmlspecialchars((string) $update->getLocalVersion(), ENT_QUOTES, 'UTF-8'); ?></span>
                    <span><i class="fas fa-stream"></i> <?php echo htmlspecialchars((string) $update->getConfiguration('version', 'stable'), ENT_QUOTES, 'UTF-8'); ?></span>
                    <span><i class="fas fa-cloud-download-alt"></i> <?php echo htmlspecialchars((string) $update->getSource(), ENT_QUOTES, 'UTF-8'); ?></span>
                <?php } ?>
            </div>
            <nav class="Palazzetti-config-links" aria-label="{{Ressources du plugin}}">
                <a class="btn btn-sm Palazzetti-config-link-documentation" target="_blank" rel="noopener noreferrer" href="<?php echo htmlspecialchars((string) $plugin->getDocumentation(), ENT_QUOTES, 'UTF-8'); ?>"><i class="fas fa-book"></i> {{Documentation}}</a>
                <a class="btn btn-sm Palazzetti-config-link-changelog" target="_blank" rel="noopener noreferrer" href="<?php echo htmlspecialchars((string) $plugin->getChangelog(), ENT_QUOTES, 'UTF-8'); ?>"><i class="fas fa-list"></i> {{Changelog}}</a>
                <a class="btn btn-sm Palazzetti-config-link-community" target="_blank" rel="noopener noreferrer" href="https://community.jeedom.com/tag/plugin-palazzetti"><i class="fas fa-comments"></i> {{Communauté}}</a>
            </nav>
        </header>

        <div class="Palazzetti-config-grid">
            <section class="Palazzetti-config-card">
                <header><span class="Palazzetti-config-card-icon"><i class="fas fa-clock"></i></span><div><h3>{{Rafraîchissement}}</h3><p>{{Fréquence de récupération des informations des poêles.}}</p></div></header>
                <div class="Palazzetti-config-card-body"><div class="form-group"><label class="col-sm-6 control-label">{{Intervalle de rafraîchissement}}</label><div class="col-sm-6"><select class="configKey form-control" data-l1key="autorefresh"><option value="* * * * *">{{Toutes les minutes}}</option><option value="*/2 * * * *">{{Toutes les 2 minutes}}</option><option value="*/3 * * * *">{{Toutes les 3 minutes}}</option><option value="*/5 * * * *">{{Toutes les 5 minutes}}</option><option value="*/10 * * * *">{{Toutes les 10 minutes}}</option><option value="*/15 * * * *">{{Toutes les 15 minutes}}</option><option value="*/30 * * * *">{{Toutes les 30 minutes}}</option><option value="*/45 * * * *">{{Toutes les 45 minutes}}</option><option value="">{{Jamais}}</option></select><span class="help-block">{{Cinq minutes par défaut.}}</span></div></div></div>
            </section>

            <section class="Palazzetti-config-card">
                <header><span class="Palazzetti-config-card-icon"><i class="fas fa-shield-alt"></i></span><div><h3>{{Sécurité}}</h3><p>{{Activation explicite des écritures de paramètres en mode expert.}}</p></div></header>
                <div class="Palazzetti-config-card-body"><div class="form-group"><label class="col-sm-8 control-label">{{Autoriser les modifications PARM et HPAR}}</label><div class="col-sm-4"><input type="checkbox" class="configKey" data-l1key="allow_parameter_writes"></div></div><div class="Palazzetti-config-notice"><i class="fas fa-exclamation-triangle"></i><span>{{Les écritures restent limitées de 0 à 255 et sont journalisées. Activez cette option uniquement si vous maîtrisez les paramètres de votre modèle.}}</span></div></div>
            </section>

            <section class="Palazzetti-config-card Palazzetti-config-card-wide">
                <header><span class="Palazzetti-config-card-icon"><i class="fas fa-network-wired"></i></span><div><h3>{{Découverte réseau}}</h3><p>{{Actualisation des équipements connus et réseaux IPv4 additionnels.}}</p></div></header>
                <div class="Palazzetti-config-card-body Palazzetti-discovery-grid">
                    <div class="form-group"><label class="col-sm-5 control-label">{{Intervalle de découverte automatique}}</label><div class="col-sm-7"><select class="configKey form-control" data-l1key="auto_discovery_interval"><option value="">{{Jamais}}</option><option value="*/5 * * * *">{{Toutes les 5 minutes}}</option><option value="*/10 * * * *">{{Toutes les 10 minutes}}</option><option value="*/15 * * * *">{{Toutes les 15 minutes}}</option><option value="*/30 * * * *">{{Toutes les 30 minutes}}</option><option value="0 * * * *">{{Toutes les heures}}</option><option value="0 */2 * * *">{{Toutes les 2 heures}}</option><option value="0 */6 * * *">{{Toutes les 6 heures}}</option><option value="0 3 * * *">{{Tous les jours à 3 h}}</option></select><span class="help-block">{{Les équipements sont actualisés en place ; aucun appareil inconnu n'est créé.}}</span></div></div>
                    <div class="form-group"><label class="col-sm-5 control-label">{{Sous-réseaux additionnels}}</label><div class="col-sm-7"><textarea class="configKey form-control" data-l1key="discovery_networks" rows="3" placeholder="192.168.1.0/24&#10;192.168.2.0/24"></textarea><span class="help-block">{{Plages IPv4 privées CIDR, une par ligne ou séparées par une virgule ou un point-virgule.}}</span></div></div>
                </div>
            </section>
        </div>
    </div>
</form>
<?php include_file('desktop', 'configuration', 'js', 'Palazzetti'); ?>
