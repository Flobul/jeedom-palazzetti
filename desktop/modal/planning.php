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
	$eqLogic = eqLogic::byId((int) init('id'));
    if (!is_object($eqLogic) || $eqLogic->getEqType_name() !== 'Palazzetti' || !($eqLogic instanceof Palazzetti)) {
        throw new Exception(__('Objet non trouvé', __FILE__));
    }
    $cmdPH = $eqLogic->getCmd('info','IPH');
    if (!is_object($cmdPH)) {
        throw new Exception(__('Commande non trouvée', __FILE__));
    }
    $PH = json_decode($cmdPH->getCache()['value']);
	if (!is_object($PH) || !isset($PH)) {
        throw new Exception(__('Valeur de la commande IPH incorrecte', __FILE__));
	}
	/**
	 * Lit une propriété scalaire dans les données du planning.
	 *
	 * @param mixed $object Objet source.
	 * @param string $property Propriété recherchée.
	 * @param mixed $default Valeur de repli.
	 * @return mixed Valeur trouvée ou valeur de repli.
	 */
	function palazzettiPlanningValue($object, $property, $default = '') {
		return is_object($object) && property_exists($object, $property) && is_scalar($object->{$property})
			? $object->{$property}
			: $default;
	}
	/**
	 * Échappe une valeur destinée à la modale Planning.
	 *
	 * @param mixed $value Valeur à afficher.
	 * @return string Valeur échappée en UTF-8.
	 */
	function palazzettiPlanningEscape($value) {
		return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
	}
?>
<div id="Palazzetti_PH">
<div>
    <legend style="height: 40px;">
        <span class="objectName"></span>
            <?php echo ((int) palazzettiPlanningValue($PH, 'CHRSTATUS', 0) === 0)
                ? '<a class="btn btn-info" id="Palazzetti_ph_onoff" title="' . __('Activer/désactiver', __FILE__) . '">' . __('INACTIF', __FILE__) . '</a>'
                : '<a class="btn btn-success" id="Palazzetti_ph_onoff" title="' . __('Activer/désactiver', __FILE__) . '">' . __('ACTIF', __FILE__) . '</a>'; ?>
    </legend>
</div>

<table class="table table-condensed tablesorter" id="table_Palazzetti_ph">
	<thead>
		<tr><td colspan="4" style="background-color:#444">{{CONFIGURATION DE LA SEMAINE}}</td></tr>
		<tr>
			<th>{{Jour}}</th>
			<th>{{Programme 1}}</th>
			<th>{{Programme 2}}</th>
			<th>{{Programme 3}}</th>
		</tr>
	</thead>
	<tbody>
	 <?php
	 	$OPTION = '';
	 	for($d = 1; $d < 8; $d++) {
			$day = isset($PH->{'D'.$d}) && is_object($PH->{'D'.$d}) ? $PH->{'D'.$d} : null;
	 		echo '<tr><td>'.Palazzetti::getWeekDay($d).'</td>';
	 		echo '<td><select data-jour="'.$d.'" data-programme="1"><option value="0">OFF</option>';
		 	for($i = 1; $i < 7; $i++) {
            echo '<option value="'.$i.'" '.((palazzettiPlanningValue($day, 'M1') == 'P'.$i) ? ' selected':'' ).'>T'.$i.'</option>';
		 	}
	 		echo '</select></td>';
	 		echo '<td><select data-jour="'.$d.'" data-programme="2"><option value="0">OFF</option>';
		 	for($i = 1; $i < 7; $i++) {
            echo '<option value="'.$i.'" '.((palazzettiPlanningValue($day, 'M2') == 'P'.$i) ? ' selected':'' ).'>T'.$i.'</option>';
		 	}
	 		echo '</select></td>';
	 		echo '<td><select data-jour="'.$d.'" data-programme="3"><option value="0">OFF</option>';
		 	for($i = 1; $i < 7; $i++) {
            echo '<option value="'.$i.'" '.((palazzettiPlanningValue($day, 'M3') == 'P'.$i) ? ' selected':'' ).'>T'.$i.'</option>';
		 	}
	 		echo '</select></td>';
	 		echo '</tr>';
	 	}
?>
	</tbody>
</table>

<table class="table table-condensed tablesorter" id="table_Palazzetti_tranche">
	<thead>
		<tr><td colspan="4" style="background-color:#444">{{CONFIGURATION DES TRANCHES}}</td></tr>
		<tr>
			<th>{{Numéro tranche}}</th>
			<th>{{Début tranche}}</th>
			<th>{{Fin tranche}}</th>
			<th>{{Consigne tranche}}</th>
		</tr>
	</thead>
	<tbody>
	 <?php

	 	for($i = 1; $i < 7; $i++) {
			$period = isset($PH->{'P'.$i}) && is_object($PH->{'P'.$i}) ? $PH->{'P'.$i} : null;
	 		echo '<tr data-numero="'.$i.'"><td>Tranche '.$i.'</td>';
        echo '<td><input class="form-control input-sm in_timepicker" data-type="start" value="'.palazzettiPlanningEscape(palazzettiPlanningValue($period, 'START')).'" /></td>';
        echo '<td><input class="form-control input-sm in_timepicker" data-type="end" value="'.palazzettiPlanningEscape(palazzettiPlanningValue($period, 'STOP')).'" /></td>';
        echo '<td><input type="text" data-type="temperature" value="'.palazzettiPlanningEscape(palazzettiPlanningValue($period, 'CHRSETP')).'" />°C</td>';
	 		echo '</tr>';
	 	}
?>
	</tbody>
</table>

</div>
<?php
	// recuperation des commandes
		$planningCommands = array();
		foreach (array('WPH', 'WPHtoDay', 'WPHtranche', 'RPH') as $logicalId) {
			$planningCommands[$logicalId] = $eqLogic->getCmd('action', $logicalId);
			if (!is_object($planningCommands[$logicalId])) {
				throw new Exception(__('Commande de planning manquante : ', __FILE__) . $logicalId);
			}
		}
		$PHToggleID = $planningCommands['WPH']->getId();
		$PHDayID = $planningCommands['WPHtoDay']->getId();
		$PHTrancheID = $planningCommands['WPHtranche']->getId();
		$PHRefresh = $planningCommands['RPH']->getId();
?>
<script>
	        jeedomUtils.dateTimePickerInit(5);
	 		// activation / desactivation
	 		document.getElementById('Palazzetti_ph_onoff')?.addEventListener('click', function() {
				if (this.textContent.trim() === 'ACTIF') {
					jeedom.cmd.execute({id: <?php echo $PHToggleID; ?>, value: 0});
					this.classList.remove('btn-success');
					this.classList.add('btn-info');
					this.textContent = 'INACTIF';
				} else if (this.textContent.trim() === 'INACTIF') {
					jeedom.cmd.execute({id: <?php echo $PHToggleID; ?>, value: 1});
					this.classList.remove('btn-info');
					this.classList.add('btn-success');
					this.textContent = 'ACTIF';
				}
	 			setTimeout(function() { jeedom.cmd.execute({id: <?php echo $PHRefresh; ?>}); }, 1500);
	 		});
	 		// enregistrement des tranches
			document.querySelectorAll('#table_Palazzetti_ph select').forEach(function(select) {
			  select.addEventListener('change', function() {
				var J = select.dataset.jour;
				var T = select.dataset.programme;
				var P = select.value;
				jeedom.cmd.execute({id: <?php echo $PHDayID; ?>, value :{jour: J, tranche: T, programme: P}});
				setTimeout(function() { jeedom.cmd.execute({id: <?php echo $PHRefresh; ?>}); }, 1500);
			  });
			});


 		// enregistement du planning
	 		document.querySelectorAll('#table_Palazzetti_tranche input').forEach(function(input) {
	 		  input.addEventListener('change', function() {
	 			var tr = input.closest('tr');
	 			var numero = tr.dataset.numero;
	 			var temperature = tr.querySelector("input[data-type='temperature']").value;
	 			var start = tr.querySelector("input[data-type='start']").value.split(':');
	 			var end = tr.querySelector("input[data-type='end']").value.split(':');
				jeedom.cmd.execute({id: <?php echo $PHTrancheID; ?>, value :{numero: numero, temperature: temperature, h1: parseInt(start[0]), m1: parseInt(start[1]), h2: parseInt(end[0]), m2: parseInt(end[1])}});
				setTimeout(function() { jeedom.cmd.execute({id: <?php echo $PHRefresh; ?>}); }, 1500);
	 		  });
	 		});
</script>
