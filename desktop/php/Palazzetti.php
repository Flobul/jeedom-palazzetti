<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

if (!isConnect('admin')) {
	throw new Exception(__('401 - Accès non autorisé', __FILE__));
}
$plugin = plugin::byId('Palazzetti');
sendVarToJS('eqType', $plugin->getId());
$eqLogics = eqLogic::byType($plugin->getId());
$palazzettiEscape = function ($value) {
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};
$palazzettiCommandValue = function ($eqLogic, $logicalId) {
	$cmd = $eqLogic->getCmd('info', $logicalId);
	return is_object($cmd) ? $cmd->execCmd() : null;
};
?>
<link rel="stylesheet" href="core/php/getCSS.php?file=plugins/Palazzetti/desktop/css/Palazzetti.css">

<div class="row row-overflow">
	<div class="col-xs-12 eqLogicThumbnailDisplay">
		<div class="row">
			<div class="col-sm-10">
				<legend><i class="fas fa-cog"></i> {{Gestion}}</legend>
				<div class="eqLogicThumbnailContainer">
					<div class="cursor eqLogicAction logoPrimary" data-action="add">
						<i class="fas fa-plus-circle"></i>
						<br>
						<span>{{Ajouter}}</span>
					</div>
					<div class="cursor logoPrimary" id="bt_discoverPalazzetti">
						<i class="fas fa-broadcast-tower"></i>
						<br>
						<span>{{Découvrir}}</span>
					</div>
					<div class="cursor eqLogicAction logoSecondary" data-action="gotoPluginConf">
						<i class="fas fa-wrench"></i>
						<br>
						<span>{{Configuration}}</span>
					</div>
					<div class="cursor logoSecondary" id="bt_healthPalazzetti">
						<i class="fas fa-heartbeat"></i>
						<br>
						<span>{{Santé}}</span>
					</div>
					<div class="cursor logoSecondary" id="bt_heatingHistoryPalazzetti">
						<i class="fas fa-chart-area"></i>
						<br>
						<span>{{Historique de chauffe}}</span>
					</div>
				</div>
			</div>
			<?php
			// à conserver
			// sera afficher uniquement si l'utilisateur est en version 4.4 ou supérieur
			$jeedomVersion  = jeedom::version() ?? '0';
			$displayInfoValue = version_compare($jeedomVersion, '4.4.0', '>=');
			if ($displayInfoValue) {
			?>
				<div class="col-sm-2">
					<legend><i class=" fas fa-comments"></i> {{Community}}</legend>
					<div class="eqLogicThumbnailContainer">
						<div class="cursor eqLogicAction logoSecondary" data-action="createCommunityPost">
							<i class="fas fa-ambulance"></i>
							<br>
							<span style="color:var(--txt-color)">{{Créer un post Community}}</span>
						</div>
					</div>
				</div>
			<?php
			}
			?>
		</div>
		<legend><i class="fas fa-table"></i> {{Mes poêles connectés}}</legend>
		<?php
		if (count($eqLogics) == 0) {
			echo '<br><div class="text-center" style="font-size:1.2em;font-weight:bold;">{{Aucun équipement Template trouvé, cliquer sur "Ajouter" pour commencer}}</div>';
		} else {
			// Champ de recherche
			echo '<div class="input-group" style="margin:5px;">';
			echo '<input class="form-control roundedLeft" placeholder="{{Rechercher}}" id="in_searchEqlogic">';
			echo '<div class="input-group-btn">';
			echo '<a id="bt_resetSearch" class="btn" style="width:30px"><i class="fas fa-times"></i></a>';
			echo '<a class="btn roundedRight hidden" id="bt_pluginDisplayAsTable" data-coreSupport="1" data-state="0"><i class="fas fa-grip-lines"></i></a>';
			echo '</div>';
			echo '</div>';
			// Liste des équipements du plugin
			echo '<div class="eqLogicThumbnailContainer">';
			foreach ($eqLogics as $eqLogic) {
				$opacity = ($eqLogic->getIsEnable()) ? '' : 'disableCard';
				$communication = $eqLogic->getCommunicationHealth();
				$lastCommunication = trim((string) $eqLogic->getStatus('lastCommunication'));
				if (!$eqLogic->getIsEnable()) {
					$connectionState = __('Désactivé', __FILE__);
				} elseif ($communication['offline']) {
					$connectionState = __('Passerelle hors ligne', __FILE__);
				} elseif ($communication['stoveOffline']) {
					$connectionState = __('Poêle indisponible', __FILE__);
				} elseif ($lastCommunication !== '') {
					$connectionState = __('En ligne', __FILE__);
				} else {
					$connectionState = __('Non testé', __FILE__);
				}
				$statusValue = $palazzettiCommandValue($eqLogic, 'IStatus');
				$statusLabel = $statusValue === null || $statusValue === ''
					? '—'
					: Palazzetti::getStoveState((int) $statusValue);
				$temperature = $palazzettiCommandValue($eqLogic, 'ITemp');
				$setpoint = $palazzettiCommandValue($eqLogic, 'IConsigne');
				$power = $palazzettiCommandValue($eqLogic, 'IPower');
				$fan = $palazzettiCommandValue($eqLogic, 'IFan');
				$cardTitle = implode('<br/>', array(
					__('Connexion', __FILE__) . ' : ' . $connectionState,
					__('État', __FILE__) . ' : ' . $statusLabel,
					__('Température', __FILE__) . ' : ' . ($temperature === null || $temperature === '' ? '—' : $temperature . ' °C'),
					__('Consigne', __FILE__) . ' : ' . ($setpoint === null || $setpoint === '' ? '—' : $setpoint . ' °C'),
					__('Puissance', __FILE__) . ' : ' . ($power === null || $power === '' ? '—' : $power),
					__('Ventilation', __FILE__) . ' : ' . ($fan === null || $fan === '' ? '—' : $eqLogic->getFanState($fan)),
					__('Dernière communication', __FILE__) . ' : ' . ($lastCommunication !== '' ? $lastCommunication : __('Aucune', __FILE__))
				));

				echo '<div class="eqLogicDisplayCard cursor ' . $opacity . '" data-eqLogic_id="' . (int) $eqLogic->getId()
					. '" title="' . $palazzettiEscape($cardTitle) . '">';
				echo '<img src="' . htmlspecialchars((string) $eqLogic->getImage(), ENT_QUOTES, 'UTF-8') . '"/>';
				echo '<br>';
				echo '<span class="name">' . $eqLogic->getSafeHumanNameHtml() . '</span>';
				echo '<span class="hiddenAsCard displayTableRight hidden">';
				echo ($eqLogic->getIsVisible() == 1) ? '<i class="fas fa-eye" title="{{Equipement visible}}"></i>' : '<i class="fas fa-eye-slash" title="{{Equipement non visible}}"></i>';
				echo '</span>';
				echo '</div>';
			}
			echo '</div>';
		}
		?>
	</div>

	<div class="col-xs-12 eqLogic" style="display: none;">
		<div class="input-group pull-right" style="display:inline-flex;">
			<span class="input-group-btn">
				<a class="btn btn-sm btn-default eqLogicAction roundedLeft" data-action="configure"><i class="fas fa-cogs"></i><span class="hidden-xs"> {{Configuration avancée}}</span>
				</a><a class="btn btn-sm btn-default eqLogicAction" data-action="copy"><i class="fas fa-copy"></i><span class="hidden-xs"> {{Dupliquer}}</span>
				</a><a class="btn btn-sm btn-success eqLogicAction" data-action="save"><i class="fas fa-check-circle"></i> {{Sauvegarder}}
				</a><a class="btn btn-sm btn-danger eqLogicAction roundedRight" data-action="remove"><i class="fas fa-minus-circle"></i> {{Supprimer}}
				</a>
			</span>
		</div>
		<ul class="nav nav-tabs" role="tablist">
			<li role="presentation"><a href="#" class="eqLogicAction" aria-controls="home" role="tab" data-toggle="tab" data-action="returnToThumbnailDisplay"><i class="fas fa-arrow-circle-left"></i></a></li>
			<li role="presentation" class="active"><a href="#eqlogictab" aria-controls="home" role="tab" data-toggle="tab"><i class="fas fa-tachometer-alt"></i> {{Equipement}}</a></li>
			<li role="presentation"><a href="#commandtab" aria-controls="home" role="tab" data-toggle="tab"><i class="fas fa-list"></i> {{Commandes}}</a></li>
		</ul>
		<div class="tab-content">
			<div role="tabpanel" class="tab-pane active" id="eqlogictab">
				<form class="form-horizontal">
                    <fieldset>
						<div class="col-lg-6">
							<legend><i class="fas fa-wrench"></i> {{Paramètres généraux}}</legend>
							<div class="form-group">
								<label class="col-sm-4 control-label">{{Nom de l'équipement}}</label>
								<div class="col-sm-6">
									<input type="text" class="eqLogicAttr form-control" data-l1key="id" style="display:none;">
									<input type="text" class="eqLogicAttr form-control" data-l1key="name" placeholder="{{Nom de l'équipement}}">
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-4 control-label">{{Objet parent}}</label>
								<div class="col-sm-6">
									<select id="sel_object" class="eqLogicAttr form-control" data-l1key="object_id">
										<option value="">{{Aucun}}</option>
										<?php
										$options = '';
										foreach ((jeeObject::buildTree(null, false)) as $object) {
											$options .= '<option value="' . (int) $object->getId() . '">' . str_repeat('&nbsp;&nbsp;', (int) $object->getConfiguration('parentNumber')) . htmlspecialchars((string) $object->getName(), ENT_QUOTES, 'UTF-8') . '</option>';
										}
										echo $options;
										?>
									</select>
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-4 control-label">{{Catégorie}}</label>
								<div class="col-sm-6">
									<?php
									foreach (jeedom::getConfiguration('eqLogic:category') as $key => $value) {
										echo '<label class="checkbox-inline">';
										echo '<input type="checkbox" class="eqLogicAttr" data-l1key="category" data-l2key="' . htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8') . '" >' . htmlspecialchars((string) $value['name'], ENT_QUOTES, 'UTF-8');
										echo '</label>';
									}
									?>
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-4 control-label">{{Options}}</label>
								<div class="col-sm-6">
									<label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isEnable" checked>{{Activer}}</label>
									<label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="isVisible" checked>{{Visible}}</label>
								</div>
							</div>
                            <div class="form-group">
                                <label class="col-sm-4 control-label">{{Adresse IP}}</label>
                                <div class="col-sm-6">
                                    <input class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="addressip" type="text" placeholder="{{saisir l'adresse IP}}">
                                </div>
                            </div>
                            <div class="form-group">
                              <label class="col-sm-4 control-label help" data-help="{{Inverse la vitesse de ventilation AUTO et OFF .}}</br>{{Décoché}} : 0=OFF ; 7=AUTO</br>{{Coché}} : 0=AUTO ; 7=OFF">{{Inverser les valeurs de vitesse de ventilation}}
                              </label>
                              <div class="col-sm-6">
                                <input type="checkbox" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="invertFanSpeed" />
                              </div>
                            </div>
                            <div class="form-group">
                              <label class="col-sm-4 control-label help" data-help="{{Cocher la case pour utiliser le widget associé au type de l'appareil.}}</br>{{Laissez décoché pour laisser le core générer le widget par défaut.}}">{{Widget équipement}}
                              </label>
                              <div class="col-sm-6">
                                <input type="checkbox" class="eqLogicAttr form-control" id="widgetTemplate" data-l1key="configuration" data-l2key="widgetTemplate" />
                              </div>
                            </div>
                            <div class="form-group" id="buttonParam" style="display:none">
                                <label class="col-sm-4 control-label">{{Accès aux pages de paramètres}}</label>
                                <span class="input-group">
                                    <a class="btn btn-info btn-sm cmdAction roundedLeft" data-action="parametres" style="margin-top:5px;"><i class="fas fa-plus-circle"></i> {{Paramètres du poêle}}</a>
                                    <a class="btn btn-info btn-sm cmdAction roundedRight" data-action="hiddenParametres" style="margin-top:5px;"><i class="fas fa-plus-circle"></i> {{Paramètres cachés du poêle}}</a>
                                </span>
                            </div>
                        </div>
						<div class="col-lg-6" id="showWPalaControl" style="display:none;">
							<legend><i class="fas fa-info"></i> {{Informations}}</legend>
							<div class="form-group">
								<label class="col-sm-4 control-label">WirelessPalaControl</label>
								<div class="col-sm-6">
									<span class="eqLogicAttr" data-l1key="configuration" data-l2key="isWirelessPalaControl"></span>
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-4 control-label">{{Numéro de série}}</label>
								<div class="col-sm-6">
									<span class="eqLogicAttr" data-l1key="configuration" data-l2key="serialNumber"></span>
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-4 control-label">{{Modèle}}</label>
								<div class="col-sm-6">
									<span class="eqLogicAttr" data-l1key="configuration" data-l2key="model"></span>
								</div>
							</div>
							<div class="form-group">
								<label class="col-sm-4 control-label">{{Versions}}</label>
								<div class="col-sm-6">
									<span class="eqLogicAttr" data-l1key="configuration" data-l2key="versions"></span> <a href="https://github.com/Domochip/WirelessPalaControl/releases" class="btn btn-xs btn-info">{{Vérifier les mises à jour}}</a></li>
								</div>
							</div>
                            
						</div>
					</fieldset>
				</form>
			</div>
			<div role="tabpanel" class="tab-pane" id="commandtab">
				<a class="btn btn-success btn-sm cmdAction pull-right" data-action="add" style="margin-top:5px;"><i class="fas fa-plus-circle"></i> {{Ajouter une commande}}</a>
				<table id="table_cmd" class="table table-bordered table-condensed">
					<thead>
						<tr>
							<th>{{ID}}</th>
							<th>{{Nom}}</th>
							<th>{{Type}}</th>
							<th>{{Commande}}</th>
							<th>{{Paramètres}}</th>
							<th style="width:200px;">{{État}}</th>
							<th>{{Action}}</th>
						</tr>
					</thead>
					<tbody>
					</tbody>
				</table>
			</div>
		</div>
	</div>
</div>

<?php include_file('desktop', 'Palazzetti', 'js', 'Palazzetti'); ?>
<?php include_file('core', 'plugin.template', 'js'); ?>
