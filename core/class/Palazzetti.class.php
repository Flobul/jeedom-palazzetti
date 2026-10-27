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

/* * ***************************Includes********************************* */
if (!class_exists('eqLogic', false)) {
    $jeedomCore = dirname(__FILE__) . '/../../../../core/php/core.inc.php';
    if (is_file($jeedomCore)) {
        require_once $jeedomCore;
    }
    unset($jeedomCore);
}

/**
 * Équipement Jeedom représentant une passerelle ou un poêle Palazzetti.
 */
class Palazzetti extends eqLogic
{
    public static $_pluginVersion = '2.1.1';
    private const REQUEST_ERROR_LOG_INTERVAL = 3600;
    private const REDISCOVERY_FAILURE_THRESHOLD = 3;
    private const REDISCOVERY_COOLDOWN = 3600;
    private const DISCOVERY_PORT = 54549;
    private const DISCOVERY_TIMEOUT = 3;
    private const DISCOVERY_MESSAGE = 'plzbridge?';
    private const DISCOVERY_MAX_UNICAST_HOSTS = 1024;
    private const DISCOVERY_MAX_TARGETS = 4096;
    private const PROTOCOL_MAX_COMMAND_LENGTH = 256;
    private const DISCOVERY_MAX_TEXT_LENGTH = 120;
    private const HISTORY_MAX_DAYS = 31;
    private const HISTORY_MAX_POINTS_PER_SERIES = 3000;
    private const HISTORY_ACTIVE_STATES = array(2, 3, 4, 5, 6, 9, 10, 11, 12);
    private const HISTORY_SERIES = array(
        'status' => array('logicalId' => 'IStatus', 'step' => true),
        'power' => array('logicalId' => 'IPower', 'step' => true),
        'fan' => array('logicalId' => 'IFan', 'step' => true),
        'temperature' => array('logicalId' => 'ITemp', 'step' => false),
        'setpoint' => array('logicalId' => 'IConsigne', 'step' => true),
        'temperature2' => array('logicalId' => 'ITemp2', 'step' => false),
        'temperature3' => array('logicalId' => 'ITemp3', 'step' => false),
        'pellets' => array('logicalId' => 'IQuantite', 'step' => true),
        'heatingTime' => array('logicalId' => 'IHeuresChauffe', 'step' => true)
    );
    private static $rediscoveryRunning = false;

    /**
     * Normalise et valide une commande destinée à la passerelle.
     *
     * @param mixed $command Commande brute.
     * @return string Commande normalisée.
     * @throws InvalidArgumentException Si la commande est invalide.
     */
    public static function normalizeCommand($command)
    {
        $command = trim((string) $command);
        if ($command === '' || strlen($command) > self::PROTOCOL_MAX_COMMAND_LENGTH) {
            throw new InvalidArgumentException(__('Commande Palazzetti vide ou trop longue.', __FILE__));
        }
        if (preg_match('/[\x00-\x1F\x7F&?#$`"\\\\]/u', $command) === 1
            || preg_match('/(?:^|[+\s])(undefined|null|nan)(?:$|[+\s])/i', $command) === 1) {
            throw new InvalidArgumentException(__('Commande Palazzetti invalide.', __FILE__));
        }
        if (preg_match('/^[A-Z]{3}\+[A-Z0-9]{2,4}(?:\+.*)?$/u', $command) !== 1) {
            throw new InvalidArgumentException(__('Format de commande Palazzetti invalide.', __FILE__));
        }
        return $command;
    }

    /**
     * Valide un identifiant numérique PARM ou HPAR.
     *
     * @param mixed $value Identifiant brut.
     * @param int $maximum Valeur maximale admise.
     * @return int Identifiant validé.
     * @throws InvalidArgumentException Si l'identifiant est invalide.
     */
    public static function normalizeParameterId($value, $maximum = 255)
    {
        $value = trim((string) $value);
        if (preg_match('/^\d{1,3}$/', $value) !== 1) {
            throw new InvalidArgumentException(__('Identifiant de paramètre invalide.', __FILE__));
        }
        $id = (int) $value;
        if ($id < 0 || $id > (int) $maximum) {
            throw new InvalidArgumentException(__('Identifiant de paramètre hors limites.', __FILE__));
        }
        return $id;
    }

    /**
     * Valide une valeur de paramètre sur un octet.
     *
     * @param mixed $value Valeur brute.
     * @return int Valeur comprise entre 0 et 255.
     * @throws InvalidArgumentException Si la valeur est invalide.
     */
    public static function normalizeParameterValue($value)
    {
        $value = trim((string) $value);
        if (preg_match('/^\d{1,3}$/', $value) !== 1) {
            throw new InvalidArgumentException(__('Valeur de paramètre invalide.', __FILE__));
        }
        $number = (int) $value;
        if ($number < 0 || $number > 255) {
            throw new InvalidArgumentException(__('La valeur doit être comprise entre 0 et 255.', __FILE__));
        }
        return $number;
    }

    /**
     * Convertit une durée HH:MM en nombre décimal d'heures.
     *
     * @param mixed $value Durée brute.
     * @return float|null Durée convertie, ou null si le format est invalide.
     */
    public static function decimalHours($value)
    {
        if (preg_match('/^(\d+):([0-5]\d)$/', trim((string) $value), $matches) !== 1) {
            return null;
        }
        return round((int) $matches[1] + ((int) $matches[2] / 60), 2);
    }

    /**
     * Normalise une adresse MAC avec des séparateurs deux-points.
     *
     * @param mixed $mac Adresse MAC brute.
     * @return string Adresse normalisée, ou chaîne vide si elle est invalide.
     */
    public static function normalizeMac($mac)
    {
        $normalized = preg_replace('/[^0-9A-F]/i', '', (string) $mac);
        $compact = is_string($normalized) ? strtoupper($normalized) : '';
        return strlen($compact) === 12 ? implode(':', str_split($compact, 2)) : '';
    }

    /**
     * Indique si une adresse appartient à une plage IPv4 privée RFC 1918.
     *
     * @param mixed $address Adresse à contrôler.
     * @return bool Vrai pour une adresse privée valide.
     */
    public static function isPrivateIpv4($address)
    {
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return false;
        }
        $long = ip2long($address);
        if ($long === false) {
            return false;
        }
        if ($long < 0) {
            $long += 4294967296;
        }
        return ($long >= 167772160 && $long <= 184549375)
            || ($long >= 2886729728 && $long <= 2887778303)
            || ($long >= 3232235520 && $long <= 3232301055);
    }

    /**
     * Valide une IPv4 privée ou un nom d'hôte local autorisé.
     *
     * @param mixed $address Adresse ou nom d'hôte à contrôler.
     * @return bool Vrai lorsque la cible reste sur le réseau local.
     */
    public static function isLocalAddress($address)
    {
        $address = trim((string) $address);
        if (self::isPrivateIpv4($address)) {
            return true;
        }
        if (strlen($address) > 253
            || preg_match('/^[A-Za-z0-9](?:[A-Za-z0-9.-]{0,251}[A-Za-z0-9])?$/', $address) !== 1) {
            return false;
        }
        if (strpos($address, '.') !== false && substr(strtolower($address), -6) !== '.local') {
            return false;
        }
        if (strtolower($address) === 'localhost' || substr(strtolower($address), -10) === '.localhost') {
            return false;
        }
        foreach (explode('.', $address) as $label) {
            if ($label === '' || strlen($label) > 63
                || preg_match('/^[A-Za-z0-9](?:[A-Za-z0-9-]*[A-Za-z0-9])?$/', $label) !== 1) {
                return false;
            }
        }
        return true;
    }

    /**
     * Nettoie un texte reçu lors de la découverte ou de l'interrogation.
     *
     * @param mixed $value Valeur brute.
     * @param int $maximumLength Longueur maximale conservée.
     * @return string Texte nettoyé.
     */
    public static function sanitizeDiscoveryText($value, $maximumLength = self::DISCOVERY_MAX_TEXT_LENGTH)
    {
        if (!is_scalar($value)) {
            return '';
        }
        $value = trim(strip_tags((string) $value));
        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value);
        if (!is_string($value)) {
            return '';
        }
        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, (int) $maximumLength);
        }
        return substr($value, 0, (int) $maximumLength);
    }

    /**
     * Exécute le rafraîchissement périodique des équipements actifs.
     *
     * @return void
     */
    public static function pull()
    {
        log::add(__CLASS__, 'debug', __FUNCTION__ . ' : ' . __('Démarrage du cron', __FILE__));
        $autorefresh = config::byKey('autorefresh', __CLASS__, '');
        $eqLogics = eqLogic::byType(__CLASS__);
        if ($autorefresh != '') {
            try {
                $c = new Cron\CronExpression($autorefresh, new Cron\FieldFactory());
                if ($c->isDue()) {
                    try {
                        foreach ($eqLogics as $eqLogic) {
                            if ($eqLogic->getIsEnable()) {
                                try {
                                    $eqLogic->getInformations();
                                } catch (Exception $exc) {
                                    log::add(__CLASS__, 'warning', sprintf(
                                        __('Rafraîchissement impossible pour %s : %s', __FILE__),
                                        $eqLogic->getHumanName(),
                                        $exc->getMessage()
                                    ));
                                }
                            }
                        }
                    } catch (Exception $exc) {
                        log::add(__CLASS__, 'error', __('Erreur : ', __FILE__) . $exc->getMessage());
                    }
                }
            } catch (Exception $exc) {
                log::add(__CLASS__, 'error', __('Expression cron non valide : ', __FILE__) . $autorefresh);
            }
        }
        log::add(__CLASS__, 'debug', __FUNCTION__ . ' : ' . __('fin', __FILE__));
    }

    /**
     * Crée, met à jour ou désactive la tâche de découverte automatique.
     *
     * @return void
     */
    public static function configureAutoDiscoveryCron()
    {
        $schedule = trim((string) config::byKey('auto_discovery_interval', __CLASS__, ''));
        $cron = cron::byClassAndFunction(__CLASS__, 'cronAutoDiscover');

        if ($schedule === '') {
            if (is_object($cron)) {
                $cron->setEnable(0);
                $cron->save();
                $cron->stop();
            }
            return;
        }

        if (!is_object($cron)) {
            $cron = new cron();
        }
        $cron->setClass(__CLASS__);
        $cron->setFunction('cronAutoDiscover');
        $cron->setEnable(1);
        $cron->setDeamon(0);
        $cron->setSchedule($schedule);
        $cron->setTimeout(60);
        $cron->save();
    }

    /**
     * Réapplique immédiatement l'intervalle lorsque la configuration est sauvée.
     *
     * @param mixed $value Nouvelle valeur de configuration.
     * @return void
     */
    public static function postConfig_auto_discovery_interval($value)
    {
        self::configureAutoDiscoveryCron();
    }

    /**
     * Lance la découverte depuis la tâche Jeedom dédiée.
     *
     * @return void
     */
    public static function cronAutoDiscover()
    {
        if (trim((string) config::byKey('auto_discovery_interval', __CLASS__, '')) === '') {
            return;
        }

        try {
            $result = self::discover('automatic');
            cache::set(__CLASS__ . '::discoveryCandidates', $result, 0);
            log::add(__CLASS__, 'info', __FUNCTION__ . ' - ' . sprintf(
                __('%d passerelle(s) détectée(s), %d équipement(s) actualisé(s), %d appareil(s) inconnu(s) ignoré(s)', __FILE__),
                $result['found'],
                $result['updated'],
                $result['skipped']
            ));
        } catch (Exception $e) {
            log::add(__CLASS__, 'error', __FUNCTION__ . __(' - échec de la découverte automatique : ', __FILE__) . $e->getMessage());
        }
    }

    /**
     * Retourne les contrôles affichés dans les pages de santé Jeedom.
     *
     * @return array<int, array<string, mixed>> Résultats des contrôles.
     */
    public static function health()
    {
        $return = array();
        $cron = cron::byClassAndFunction(__CLASS__, 'pull');
        $cronEnabled = is_object($cron) && (int) $cron->getEnable(0) === 1;
        $return[] = array(
            'test' => __('Tâche de rafraîchissement', __FILE__),
            'result' => $cronEnabled ? __('OK', __FILE__) : __('NOK', __FILE__),
            'advice' => $cronEnabled ? '' : __('Vérifiez la tâche Palazzetti::pull dans le moteur des tâches.', __FILE__),
            'state' => $cronEnabled
        );

        $socketAvailable = function_exists('socket_create') && function_exists('socket_sendto');
        $return[] = array(
            'test' => __('Découverte UDP', __FILE__),
            'result' => $socketAvailable ? __('Disponible', __FILE__) : __('Indisponible', __FILE__),
            'advice' => $socketAvailable ? '' : __('Installez ou activez l\'extension PHP sockets pour utiliser la découverte.', __FILE__),
            'state' => $socketAvailable
        );

        $discoverySchedule = trim((string) config::byKey('auto_discovery_interval', __CLASS__, ''));
        $discoveryCron = cron::byClassAndFunction(__CLASS__, 'cronAutoDiscover');
        $discoveryCronEnabled = is_object($discoveryCron) && (int) $discoveryCron->getEnable(0) === 1;
        $discoveryCronHealthy = $discoverySchedule === ''
            ? !$discoveryCronEnabled
            : ($discoveryCronEnabled && (string) $discoveryCron->getSchedule() === $discoverySchedule);
        $return[] = array(
            'test' => __('Découverte automatique', __FILE__),
            'result' => $discoverySchedule === '' ? __('Désactivée', __FILE__) : $discoverySchedule,
            'advice' => $discoveryCronHealthy
                ? ''
                : __('Enregistrez à nouveau la configuration du plugin pour resynchroniser la tâche.', __FILE__),
            'state' => $discoveryCronHealthy
        );

        $eqLogics = eqLogic::byType(__CLASS__);
        $enabled = 0;
        $gatewayOffline = 0;
        $stoveOffline = 0;
        $invalidAddresses = 0;
        foreach ($eqLogics as $eqLogic) {
            if (!$eqLogic->getIsEnable()) {
                continue;
            }
            $enabled++;
            if (!self::isLocalAddress($eqLogic->getConfiguration('addressip'))) {
                $invalidAddresses++;
            }
            $communication = $eqLogic->getCommunicationHealth();
            if ($communication['offline']) {
                $gatewayOffline++;
            } elseif ($communication['stoveOffline']) {
                $stoveOffline++;
            }
        }

        $equipmentState = $invalidAddresses === 0 && $gatewayOffline === 0 && $stoveOffline === 0;
        $return[] = array(
            'test' => __('Équipements', __FILE__),
            'result' => sprintf(
                __('%d configuré(s), %d actif(s), %d passerelle(s) hors ligne, %d poêle(s) indisponible(s)', __FILE__),
                count($eqLogics),
                $enabled,
                $gatewayOffline,
                $stoveOffline
            ),
            'advice' => $invalidAddresses > 0
                ? sprintf(__('%d adresse(s) invalide(s) sont configurées.', __FILE__), $invalidAddresses)
                : (($gatewayOffline + $stoveOffline) > 0 ? __('Consultez les équipements en erreur ci-dessous.', __FILE__) : ''),
            'state' => $equipmentState
        );

        return $return;
    }

    /**
     * Retourne les historiques bornés et leur synthèse pour le chronogramme.
     *
     * @param string $dateStart Date de début au format Y-m-d.
     * @param string $dateEnd Date de fin au format Y-m-d.
     * @return array<string, mixed> Séries, cycles et statistiques quotidiennes.
     * @throws InvalidArgumentException Si la période est invalide.
     */
    public function getHeatingHistory($dateStart, $dateEnd)
    {
        $range = self::validateHeatingHistoryRange($dateStart, $dateEnd);
        $series = array();
        $missing = array();
        $rawTemperaturePoints = array();
        $rawStatusPoints = array();
        $rawPelletPoints = array();
        $rawHeatingTimePoints = array();

        foreach (self::HISTORY_SERIES as $key => $definition) {
            $cmd = $this->getCmd('info', $definition['logicalId']);
            $available = is_object($cmd);
            $historized = $available && (int) $cmd->getIsHistorized() === 1;
            $rawPoints = $historized
                ? self::readHeatingHistoryPoints($cmd, $range['startTimestamp'], $range['endTimestamp'])
                : array();

            if ($key === 'temperature') {
                $rawTemperaturePoints = $rawPoints;
            } elseif ($key === 'status') {
                $rawStatusPoints = $rawPoints;
            } elseif ($key === 'pellets') {
                $rawPelletPoints = $rawPoints;
            } elseif ($key === 'heatingTime') {
                $rawHeatingTimePoints = $rawPoints;
            }

            $points = $definition['step']
                ? self::compressHeatingHistoryStepPoints($rawPoints, $range['effectiveEndTimestamp'])
                : self::limitHeatingHistoryPoints($rawPoints);
            $series[$key] = array(
                'logicalId' => $definition['logicalId'],
                'name' => $available ? (string) $cmd->getName() : $definition['logicalId'],
                'unit' => $available ? (string) $cmd->getUnite() : '',
                'available' => $available,
                'historized' => $historized,
                'points' => $points
            );
            if (!$available || !$historized) {
                $missing[] = $definition['logicalId'];
            }
        }

        $sessions = self::buildHeatingSessions(
            $rawStatusPoints,
            $range['startTimestamp'] * 1000,
            $range['effectiveEndTimestamp'] * 1000
        );
        $daily = self::buildHeatingDailyStatistics(
            $range['start'],
            $range['end'],
            $sessions,
            $rawTemperaturePoints,
            $rawPelletPoints,
            $rawHeatingTimePoints
        );

        $totalSeconds = 0;
        foreach ($sessions as $session) {
            $totalSeconds += (int) $session['durationSeconds'];
        }
        $dailyHeatingMinutes = 0.0;
        $hasHeatingCounter = false;
        foreach ($daily as $statistics) {
            if ($statistics['heatingDurationMinutes'] !== null) {
                $dailyHeatingMinutes += (float) $statistics['heatingDurationMinutes'];
                $hasHeatingCounter = true;
            }
        }
        $summaryDurationMinutes = $hasHeatingCounter ? $dailyHeatingMinutes : ($totalSeconds / 60);
        $object = $this->getObject();

        return array(
            'equipment' => array(
                'id' => (int) $this->getId(),
                'name' => (string) $this->getName(),
                'objectName' => is_object($object) ? (string) $object->getName() : ''
            ),
            'range' => array(
                'start' => $range['start']->format('Y-m-d'),
                'end' => $range['end']->format('Y-m-d'),
                'days' => $range['days'],
                'maximumDays' => self::HISTORY_MAX_DAYS,
                'startTimestamp' => $range['startTimestamp'] * 1000,
                'endTimestamp' => $range['effectiveEndTimestamp'] * 1000
            ),
            'series' => $series,
            'sessions' => $sessions,
            'daily' => $daily,
            'summary' => array(
                'cycleCount' => count($sessions),
                'durationMinutes' => round($summaryDurationMinutes, 1),
                'averageDailyMinutes' => round($summaryDurationMinutes / $range['days'], 1)
            ),
            'missing' => array_values(array_unique($missing))
        );
    }

    /**
     * Valide les bornes temporelles demandées pour le chronogramme.
     *
     * @param mixed $dateStart Date de début brute.
     * @param mixed $dateEnd Date de fin brute.
     * @return array<string, mixed> Bornes normalisées et timestamps.
     * @throws InvalidArgumentException Si la période est invalide.
     */
    private static function validateHeatingHistoryRange($dateStart, $dateEnd)
    {
        $dateStart = trim((string) $dateStart);
        $dateEnd = trim((string) $dateEnd);
        $start = self::parseHeatingHistoryDate($dateStart, __('Date de début invalide.', __FILE__));
        $end = self::parseHeatingHistoryDate($dateEnd, __('Date de fin invalide.', __FILE__));

        if ($end->getTimestamp() < $start->getTimestamp()) {
            throw new InvalidArgumentException(__('La date de fin doit être postérieure ou égale à la date de début.', __FILE__));
        }
        $days = (int) $start->diff($end)->days + 1;
        if ($days > self::HISTORY_MAX_DAYS) {
            throw new InvalidArgumentException(sprintf(
                __('La période est limitée à %d jours.', __FILE__),
                self::HISTORY_MAX_DAYS
            ));
        }

        $startTimestamp = $start->getTimestamp();
        $endOfDay = clone $end;
        $endOfDay->setTime(23, 59, 59);
        $endTimestamp = $endOfDay->getTimestamp();
        $effectiveEndTimestamp = min($endTimestamp, time());
        if ($effectiveEndTimestamp < $startTimestamp) {
            $effectiveEndTimestamp = $startTimestamp;
        }

        return array(
            'start' => $start,
            'end' => $end,
            'days' => $days,
            'startTimestamp' => $startTimestamp,
            'endTimestamp' => $endTimestamp,
            'effectiveEndTimestamp' => $effectiveEndTimestamp
        );
    }

    /**
     * Analyse strictement une date de chronogramme.
     *
     * @param string $value Date au format Y-m-d.
     * @param string $errorMessage Message associé à une date invalide.
     * @return DateTime Date analysée.
     * @throws InvalidArgumentException Si la date est invalide.
     */
    private static function parseHeatingHistoryDate($value, $errorMessage)
    {
        $date = DateTime::createFromFormat('!Y-m-d', $value);
        $errors = DateTime::getLastErrors();
        if (!$date
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException($errorMessage);
        }
        return $date;
    }

    /**
     * Lit et normalise les points historisés d'une commande Jeedom.
     *
     * @param cmd $cmd Commande historisée.
     * @param int $startTimestamp Début de période en secondes Unix.
     * @param int $endTimestamp Fin de période en secondes Unix.
     * @return array<int, array{0:int, 1:float}> Points triés.
     */
    private static function readHeatingHistoryPoints($cmd, $startTimestamp, $endTimestamp)
    {
        $pointsByTimestamp = array();
        $history = $cmd->getHistory(
            date('Y-m-d H:i:s', $startTimestamp),
            date('Y-m-d H:i:s', $endTimestamp),
            null,
            true
        );
        foreach ($history as $entry) {
            $value = $entry->getValue();
            if (!is_numeric($value)) {
                continue;
            }
            $timestamp = strtotime((string) $entry->getDatetime());
            if ($timestamp === false || $timestamp > $endTimestamp) {
                continue;
            }
            $timestamp = max($startTimestamp, $timestamp);
            $pointsByTimestamp[$timestamp] = array($timestamp * 1000, (float) $value);
        }
        ksort($pointsByTimestamp, SORT_NUMERIC);
        return array_values($pointsByTimestamp);
    }

    /**
     * Compresse une série en escalier et la prolonge jusqu'à la borne finale.
     *
     * @param array<int, array{0:int, 1:float}> $points Points bruts.
     * @param int $effectiveEndTimestamp Fin effective en secondes Unix.
     * @return array<int, array{0:int, 1:float}> Points compressés.
     */
    private static function compressHeatingHistoryStepPoints($points, $effectiveEndTimestamp)
    {
        $result = array();
        $previousValue = null;
        foreach ($points as $point) {
            if ($previousValue !== null && (float) $previousValue === (float) $point[1]) {
                continue;
            }
            $result[] = $point;
            $previousValue = $point[1];
        }
        if (count($result) > 0) {
            $endMilliseconds = $effectiveEndTimestamp * 1000;
            $last = $result[count($result) - 1];
            if ($last[0] < $endMilliseconds) {
                $result[] = array($endMilliseconds, $last[1]);
            }
        }
        return self::limitHeatingHistoryPoints($result);
    }

    /**
     * Réduit une série tout en conservant ses première et dernière valeurs.
     *
     * @param array<int, array{0:int, 1:float}> $points Points à limiter.
     * @return array<int, array{0:int, 1:float}> Série limitée.
     */
    private static function limitHeatingHistoryPoints($points)
    {
        $count = count($points);
        if ($count <= self::HISTORY_MAX_POINTS_PER_SERIES) {
            return $points;
        }
        $stride = (int) ceil(($count - 2) / (self::HISTORY_MAX_POINTS_PER_SERIES - 2));
        $limited = array($points[0]);
        for ($index = $stride; $index < $count - 1; $index += $stride) {
            $limited[] = $points[$index];
        }
        $limited[] = $points[$count - 1];
        return $limited;
    }

    /**
     * Construit les cycles de chauffe à partir des transitions d'état.
     *
     * @param array<int, array{0:int, 1:float}> $statusPoints Historique des états.
     * @param int $rangeStartMilliseconds Début en millisecondes Unix.
     * @param int $rangeEndMilliseconds Fin en millisecondes Unix.
     * @return array<int, array<string, int|float>> Cycles détectés.
     */
    private static function buildHeatingSessions($statusPoints, $rangeStartMilliseconds, $rangeEndMilliseconds)
    {
        $sessions = array();
        $activeStart = null;
        foreach ($statusPoints as $point) {
            $timestamp = max($rangeStartMilliseconds, min($rangeEndMilliseconds, (int) $point[0]));
            $isActive = in_array((int) $point[1], self::HISTORY_ACTIVE_STATES, true);
            if ($isActive && $activeStart === null) {
                $activeStart = $timestamp;
            } elseif (!$isActive && $activeStart !== null) {
                self::appendHeatingSession($sessions, $activeStart, $timestamp);
                $activeStart = null;
            }
        }
        if ($activeStart !== null) {
            self::appendHeatingSession($sessions, $activeStart, $rangeEndMilliseconds);
        }
        return $sessions;
    }

    /**
     * Ajoute un cycle non vide à la collection.
     *
     * @param array<int, array<string, int|float>> $sessions Collection modifiée.
     * @param int $startMilliseconds Début du cycle.
     * @param int $endMilliseconds Fin du cycle.
     * @return void
     */
    private static function appendHeatingSession(&$sessions, $startMilliseconds, $endMilliseconds)
    {
        if ($endMilliseconds <= $startMilliseconds) {
            return;
        }
        $seconds = (int) round(($endMilliseconds - $startMilliseconds) / 1000);
        $sessions[] = array(
            'start' => (int) $startMilliseconds,
            'end' => (int) $endMilliseconds,
            'durationSeconds' => $seconds,
            'durationMinutes' => round($seconds / 60, 1)
        );
    }

    /**
     * Calcule les statistiques de chauffe pour chaque journée demandée.
     *
     * @param DateTime $start Première journée.
     * @param DateTime $end Dernière journée.
     * @param array<int, array<string, int|float>> $sessions Cycles détectés.
     * @param array<int, array{0:int, 1:float}> $temperaturePoints Températures.
     * @param array<int, array{0:int, 1:float}> $pelletPoints Compteur de pellets.
     * @param array<int, array{0:int, 1:float}> $heatingTimePoints Compteur de chauffe.
     * @return array<int, array<string, mixed>> Statistiques quotidiennes.
     */
    private static function buildHeatingDailyStatistics(
        $start,
        $end,
        $sessions,
        $temperaturePoints,
        $pelletPoints,
        $heatingTimePoints
    ) {
        $daily = array();
        $day = clone $start;
        while ($day->getTimestamp() <= $end->getTimestamp()) {
            $key = $day->format('Y-m-d');
            $daily[$key] = array(
                'date' => $key,
                'durationSeconds' => 0,
                'durationMinutes' => 0,
                'heatingDurationMinutes' => null,
                'cycleCount' => 0,
                'temperatureMin' => null,
                'temperatureAverage' => null,
                'temperatureMax' => null,
                'pelletConsumption' => null,
                '_temperatureSum' => 0.0,
                '_temperatureCount' => 0
            );
            $day->modify('+1 day');
        }

        foreach ($sessions as $session) {
            $sessionStart = (int) floor($session['start'] / 1000);
            $sessionEnd = (int) ceil($session['end'] / 1000);
            $startDay = date('Y-m-d', $sessionStart);
            if (isset($daily[$startDay])) {
                $daily[$startDay]['cycleCount']++;
            }
            foreach ($daily as $key => &$statistics) {
                $dayStart = strtotime($key . ' 00:00:00');
                $dayEnd = strtotime($key . ' +1 day');
                $overlap = max(0, min($sessionEnd, $dayEnd) - max($sessionStart, $dayStart));
                $statistics['durationSeconds'] += $overlap;
            }
            unset($statistics);
        }

        foreach ($temperaturePoints as $point) {
            $key = date('Y-m-d', (int) floor($point[0] / 1000));
            if (!isset($daily[$key])) {
                continue;
            }
            $value = (float) $point[1];
            $daily[$key]['temperatureMin'] = $daily[$key]['temperatureMin'] === null
                ? $value
                : min($daily[$key]['temperatureMin'], $value);
            $daily[$key]['temperatureMax'] = $daily[$key]['temperatureMax'] === null
                ? $value
                : max($daily[$key]['temperatureMax'], $value);
            $daily[$key]['_temperatureSum'] += $value;
            $daily[$key]['_temperatureCount']++;
        }

        self::applyPositiveHeatingCounterDifferences($daily, $pelletPoints, 'pelletConsumption', 1, 3);
        self::applyPositiveHeatingCounterDifferences($daily, $heatingTimePoints, 'heatingDurationMinutes', 60, 1);

        foreach ($daily as &$statistics) {
            $statistics['durationMinutes'] = round($statistics['durationSeconds'] / 60, 1);
            if ($statistics['_temperatureCount'] > 0) {
                $statistics['temperatureMin'] = round($statistics['temperatureMin'], 2);
                $statistics['temperatureAverage'] = round(
                    $statistics['_temperatureSum'] / $statistics['_temperatureCount'],
                    2
                );
                $statistics['temperatureMax'] = round($statistics['temperatureMax'], 2);
            }
            if ($statistics['pelletConsumption'] !== null) {
                $statistics['pelletConsumption'] = round($statistics['pelletConsumption'], 3);
            }
            unset($statistics['_temperatureSum'], $statistics['_temperatureCount']);
        }
        unset($statistics);

        return array_values($daily);
    }

    /**
     * Répartit les hausses d'un compteur cumulatif entre les journées.
     *
     * @param array<string, array<string, mixed>> $daily Statistiques modifiées.
     * @param array<int, array{0:int, 1:float}> $points Valeurs du compteur.
     * @param string $field Champ quotidien à alimenter.
     * @param float|int $multiplier Facteur de conversion.
     * @param int $precision Précision d'arrondi.
     * @return void
     */
    private static function applyPositiveHeatingCounterDifferences(
        &$daily,
        $points,
        $field,
        $multiplier,
        $precision
    ) {
        if (count($points) === 0) {
            return;
        }
        foreach ($daily as &$statistics) {
            $statistics[$field] = 0.0;
        }
        unset($statistics);

        $previousValue = (float) $points[0][1];
        for ($index = 1, $count = count($points); $index < $count; $index++) {
            $currentValue = (float) $points[$index][1];
            $difference = $currentValue - $previousValue;
            $key = date('Y-m-d', (int) floor($points[$index][0] / 1000));
            if ($difference >= 0 && isset($daily[$key])) {
                $daily[$key][$field] += $difference * $multiplier;
            }
            $previousValue = $currentValue;
        }
        foreach ($daily as &$statistics) {
            $statistics[$field] = round($statistics[$field], $precision);
        }
        unset($statistics);
    }

    /**
     * Recherche les passerelles Palazzetti par broadcast UDP.
     *
     * Le mode preview ne modifie rien. Le mode overwrite actualise les
     * équipements existants et crée les inconnus désactivés. Le mode replace
     * crée un remplacement et désactive l'ancien. Le mode automatic actualise
     * uniquement les équipements reconnus, afin de ne jamais les dupliquer.
     *
     * @param string|bool $mode Stratégie preview, overwrite, replace ou automatic.
     * @param string $targetIdentity Identité de l'appareil ciblé lors d'une action manuelle.
     * @return array<string, mixed> Résultat détaillé de la découverte.
     * @throws InvalidArgumentException Si le mode est inconnu.
     * @throws Exception Si la découverte réseau échoue.
     */
    public static function discover($mode = 'preview', $targetIdentity = '')
    {
        if ($mode === true) {
            $mode = 'overwrite';
        } elseif ($mode === false) {
            $mode = 'preview';
        }
        if (!in_array($mode, array('preview', 'overwrite', 'replace', 'automatic'), true)) {
            throw new InvalidArgumentException(__('Mode de découverte invalide.', __FILE__));
        }
        $targetIdentity = self::normalizeDiscoveryTargetIdentity($targetIdentity);
        if (!function_exists('socket_create')) {
            throw new Exception(__('L\'extension PHP sockets est nécessaire pour la découverte UDP.', __FILE__));
        }

        $lockKey = __CLASS__ . '::discoveryLock';
        $lockTimestamp = (int) cache::byKey($lockKey)->getValue(0);
        if ($lockTimestamp > 0 && (time() - $lockTimestamp) < 30) {
            throw new Exception(__('Une découverte Palazzetti est déjà en cours.', __FILE__));
        }
        cache::set($lockKey, time(), 60);

        $socket = null;
        try {
            $socket = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
            if ($socket === false) {
                $socket = null;
                throw new Exception(__('Impossible de créer le socket de découverte UDP.', __FILE__));
            }
            if (!@socket_set_option($socket, SOL_SOCKET, SO_BROADCAST, 1)
                || !@socket_bind($socket, '0.0.0.0', 0)) {
                throw new Exception(__('Impossible d\'initialiser le socket de découverte UDP : ', __FILE__)
                    . socket_strerror(socket_last_error($socket)));
            }

            $targets = self::getDiscoveryTargetAddresses();
            $sentTargets = 0;
            foreach ($targets as $targetAddress) {
                $length = strlen(self::DISCOVERY_MESSAGE);
                if (@socket_sendto($socket, self::DISCOVERY_MESSAGE, $length, 0, $targetAddress, self::DISCOVERY_PORT) === $length) {
                    $sentTargets++;
                }
            }
            if ($sentTargets === 0) {
                throw new Exception(__('Aucune requête de découverte UDP n\'a pu être envoyée.', __FILE__));
            }
            log::add(__CLASS__, 'debug', __FUNCTION__ . ' - ' . sprintf(
                __('%d cible(s) UDP contactée(s) sur %d', __FILE__),
                $sentTargets,
                count($targets)
            ));

            $devices = array();
            $deadline = microtime(true) + self::DISCOVERY_TIMEOUT;
            while (microtime(true) < $deadline) {
                $remaining = max(0, $deadline - microtime(true));
                $seconds = (int) floor($remaining);
                $microseconds = (int) (($remaining - $seconds) * 1000000);
                $read = array($socket);
                $write = null;
                $except = null;
                $selected = @socket_select($read, $write, $except, $seconds, $microseconds);
                if ($selected === false || $selected === 0) {
                    break;
                }

                $payload = '';
                $sourceAddress = '';
                $sourcePort = 0;
                $received = @socket_recvfrom($socket, $payload, 65535, 0, $sourceAddress, $sourcePort);
                if ($received === false || $received === 0) {
                    continue;
                }
                $device = self::parseDiscoveryResponse($payload, $sourceAddress);
                if ($device === null) {
                    continue;
                }
                $identity = $device['mac'] !== '' ? $device['mac'] : $device['ip'];
                $devices[$identity] = $device;
            }
            socket_close($socket);
            $socket = null;

            foreach ($devices as $identity => $device) {
                $devices[$identity] = self::detectDiscoveredGatewayType($device);
            }
            $deviceList = array_values($devices);
            if ($targetIdentity !== '') {
                $deviceList = array_values(array_filter($deviceList, function ($device) use ($targetIdentity) {
                    return self::getDiscoveryDeviceIdentity($device) === $targetIdentity;
                }));
                if (count($deviceList) !== 1) {
                    throw new Exception(__('L\'appareil sélectionné n\'a pas répondu à la nouvelle découverte.', __FILE__));
                }
            }
            $result = $mode === 'preview'
                ? self::previewDiscoveredDevices($deviceList)
                : self::saveDiscoveredDevices($deviceList, $mode);
            $result['mode'] = $mode;
            $result['applied'] = $mode !== 'preview';

            if ($mode === 'preview') {
                log::add(__CLASS__, 'info', __FUNCTION__ . ' - ' . sprintf(
                    __('%d passerelle(s) trouvée(s), en attente de confirmation', __FILE__),
                    $result['found']
                ));
            } else {
                log::add(__CLASS__, 'info', __FUNCTION__ . ' - ' . sprintf(
                    __('%d passerelle(s) traitée(s), %d créée(s), %d mise(s) à jour, %d remplacée(s), %d ignorée(s)', __FILE__),
                    $result['found'],
                    $result['created'],
                    $result['updated'],
                    $result['replaced'],
                    $result['skipped']
                ));
            }
            return $result;
        } finally {
            if ($socket !== null) {
                @socket_close($socket);
            }
            cache::delete($lockKey);
        }
    }

    /**
     * Construit l'identité stable utilisée pour cibler une passerelle découverte.
     *
     * @param array<string, mixed> $device Appareil découvert.
     * @return string Identité fondée sur la MAC ou, à défaut, sur l'adresse IP.
     */
    private static function getDiscoveryDeviceIdentity($device)
    {
        $mac = isset($device['mac']) ? self::normalizeMac($device['mac']) : '';
        if ($mac !== '') {
            return 'mac:' . $mac;
        }
        return 'ip:' . (isset($device['ip']) ? (string) $device['ip'] : '');
    }

    /**
     * Valide l'identité d'un appareil sélectionné dans la découverte manuelle.
     *
     * @param mixed $identity Identité transmise par l'interface.
     * @return string Identité normalisée ou chaîne vide.
     * @throws InvalidArgumentException Si l'identité est invalide.
     */
    private static function normalizeDiscoveryTargetIdentity($identity)
    {
        $identity = trim((string) $identity);
        if ($identity === '') {
            return '';
        }
        if (stripos($identity, 'mac:') === 0) {
            $mac = self::normalizeMac(substr($identity, 4));
            if ($mac !== '') {
                return 'mac:' . $mac;
            }
        } elseif (stripos($identity, 'ip:') === 0) {
            $ip = substr($identity, 3);
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
                return 'ip:' . $ip;
            }
        }
        throw new InvalidArgumentException(__('Identité de passerelle invalide.', __FILE__));
    }

    /**
     * Construit la liste des adresses de broadcast IPv4 locales.
     *
     * @return string[] Adresses de broadcast uniques.
     */
    private static function getDiscoveryBroadcastAddresses()
    {
        $addresses = array('255.255.255.255');
        if (!function_exists('net_get_interfaces')) {
            return $addresses;
        }

        $interfaces = @net_get_interfaces();
        if (!is_array($interfaces)) {
            return $addresses;
        }
        foreach ($interfaces as $interface) {
            if (!isset($interface['unicast']) || !is_array($interface['unicast'])) {
                continue;
            }
            foreach ($interface['unicast'] as $unicast) {
                if (!isset($unicast['family'], $unicast['address'], $unicast['netmask'])
                    || (int) $unicast['family'] !== AF_INET
                    || filter_var($unicast['address'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false
                    || filter_var($unicast['netmask'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false
                    || strpos($unicast['address'], '127.') === 0) {
                    continue;
                }
                $address = ip2long($unicast['address']);
                $netmask = ip2long($unicast['netmask']);
                if ($address === false || $netmask === false) {
                    continue;
                }
                $broadcast = long2ip(($address & $netmask) | ((~$netmask) & 0xffffffff));
                if ($broadcast !== false) {
                    $addresses[] = $broadcast;
                }
            }
        }
        return array_values(array_unique($addresses));
    }

    /**
     * Ajoute aux broadcasts locaux les sous-réseaux CIDR configurés.
     *
     * Le broadcast dirigé suffit sur les réseaux qui l'autorisent. Pour les
     * environnements Docker/VLAN où il est souvent filtré, les petites plages
     * sont également parcourues en unicast UDP.
     *
     * @return string[] Cibles IPv4 de découverte.
     */
    private static function getDiscoveryTargetAddresses()
    {
        $targets = array_fill_keys(self::getDiscoveryBroadcastAddresses(), true);
        $rawNetworks = (string) config::byKey('discovery_networks', __CLASS__, '');
        $networks = array_filter(array_map('trim', preg_split('/[\r\n,;]+/', $rawNetworks)));

        foreach ($networks as $cidr) {
            if (count($targets) >= self::DISCOVERY_MAX_TARGETS) {
                log::add(__CLASS__, 'warning', sprintf(
                    __('Limite globale de %d cibles de découverte atteinte.', __FILE__),
                    self::DISCOVERY_MAX_TARGETS
                ));
                break;
            }
            $range = self::parseDiscoveryCidr($cidr);
            if ($range === null) {
                log::add(__CLASS__, 'warning', __FUNCTION__ . ' - ' . sprintf(
                    __('Sous-réseau CIDR privé invalide ignoré : %s', __FILE__),
                    $cidr
                ));
                continue;
            }

            $targets[long2ip($range['broadcast'])] = true;
            if ($range['hostCount'] > self::DISCOVERY_MAX_UNICAST_HOSTS) {
                log::add(__CLASS__, 'warning', __FUNCTION__ . ' - ' . sprintf(
                    __('Scan unicast ignoré pour %s : %d hôtes dépassent la limite de %d. Le broadcast du sous-réseau reste utilisé.', __FILE__),
                    $cidr,
                    $range['hostCount'],
                    self::DISCOVERY_MAX_UNICAST_HOSTS
                ));
                continue;
            }

            for ($address = $range['firstHost'];
                $address <= $range['lastHost'] && count($targets) < self::DISCOVERY_MAX_TARGETS;
                $address++) {
                $targets[long2ip($address)] = true;
            }
        }

        return array_keys($targets);
    }

    /**
     * Valide un CIDR IPv4 privé et retourne ses bornes sous forme d'entiers.
     *
     * @param mixed $cidr Sous-réseau au format adresse/préfixe.
     * @return array<string, int>|null Bornes du réseau, ou null si invalide.
     */
    private static function parseDiscoveryCidr($cidr)
    {
        if (preg_match('/^([^\/]+)\/(\d{1,2})$/', trim((string) $cidr), $matches) !== 1) {
            return null;
        }

        $address = $matches[1];
        $prefix = (int) $matches[2];
        if ($prefix < 16 || $prefix > 32
            || filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false
            || !self::isPrivateDiscoveryAddress($address)) {
            return null;
        }

        $addressLong = ip2long($address);
        if ($addressLong === false) {
            return null;
        }
        if ($addressLong < 0) {
            $addressLong += 4294967296;
        }

        $hostBits = 32 - $prefix;
        $hostMask = $hostBits === 0 ? 0 : (2 ** $hostBits) - 1;
        $network = $addressLong & (0xffffffff ^ $hostMask);
        $broadcast = $network | $hostMask;
        $firstHost = $prefix >= 31 ? $network : $network + 1;
        $lastHost = $prefix >= 31 ? $broadcast : $broadcast - 1;

        return array(
            'network' => $network,
            'broadcast' => $broadcast,
            'firstHost' => $firstHost,
            'lastHost' => $lastHost,
            'hostCount' => max(0, $lastHost - $firstHost + 1)
        );
    }

    /**
     * Vérifie qu'une adresse de découverte est une IPv4 privée.
     *
     * @param mixed $address Adresse à contrôler.
     * @return bool Vrai pour une IPv4 privée valide.
     */
    private static function isPrivateDiscoveryAddress($address)
    {
        return self::isPrivateIpv4($address);
    }

    /**
     * Valide et normalise une réponse UDP GET STDT.
     *
     * @param mixed $payload Charge utile JSON.
     * @param mixed $sourceAddress Adresse IPv4 émettrice.
     * @return array<string, mixed>|null Appareil normalisé, ou null si invalide.
     */
    private static function parseDiscoveryResponse($payload, $sourceAddress)
    {
        if (!self::isPrivateDiscoveryAddress($sourceAddress)) {
            return null;
        }
        $response = json_decode(trim((string) $payload), true);
        if (!is_array($response) || !isset($response['DATA']) || !is_array($response['DATA'])) {
            return null;
        }
        if (isset($response['INFO']['RSP']) && strtoupper((string) $response['INFO']['RSP']) !== 'OK') {
            return null;
        }

        $data = $response['DATA'];
        if (!array_key_exists('APLCONN', $data)
            || (!isset($data['LABEL']) && !isset($data['MAC']) && !isset($data['WMAC']) && !isset($data['SN']))) {
            return null;
        }
        $name = isset($data['LABEL']) ? self::sanitizeDiscoveryText($data['LABEL'], 80) : '';
        if ($name === '') {
            $name = 'Palazzetti ' . $sourceAddress;
        }

        $mac = self::normalizeMac(isset($data['WMAC']) ? $data['WMAC'] : (isset($data['MAC']) ? $data['MAC'] : ''));
        $serial = isset($data['SN']) && is_scalar($data['SN'])
            ? self::sanitizeDiscoveryText($data['SN'], 40)
            : '';
        $model = isset($data['MOD']) && is_scalar($data['MOD'])
            ? self::sanitizeDiscoveryText($data['MOD'], 80)
            : '';
        $versions = array();
        foreach (array('SYSTEM', 'plzbridge', 'sendmsg') as $versionKey) {
            if (isset($data[$versionKey]) && is_scalar($data[$versionKey]) && trim((string) $data[$versionKey]) !== '') {
                $versions[] = $versionKey . ': ' . self::sanitizeDiscoveryText($data[$versionKey]);
            }
        }

        return array(
            'ip' => $sourceAddress,
            'name' => $name,
            'mac' => $mac,
            'serial' => $serial,
            'model' => $model,
            'versions' => implode(' | ', $versions),
            'gatewayType' => __('Connection Box / WPalaControl', __FILE__),
            'isWirelessPalaControl' => false,
            'isApplianceConnected' => !empty($data['APLCONN']),
            'stoveSerial' => $serial,
            'stoveModel' => $model
        );
    }

    /**
     * Identifie WPalaControl grâce à ses points d'état HTTP propres.
     *
     * @param array<string, mixed> $device Appareil découvert.
     * @return array<string, mixed> Appareil enrichi.
     */
    private static function detectDiscoveredGatewayType($device)
    {
        $endpoints = array(
            '/gs0' => array('serial' => 'sn', 'model' => 'model', 'version' => 'version'),
            '/ffffffff' => array('serial' => 'sn', 'model' => 'm', 'version' => 'v')
        );
        foreach ($endpoints as $endpoint => $fields) {
            try {
                $request = new com_http('http://' . $device['ip'] . $endpoint);
                $rawResponse = $request->exec(2, 1);
            } catch (Exception $e) {
                continue;
            }
            $response = json_decode((string) $rawResponse, true);
            if (!is_array($response)) {
                continue;
            }

            $model = isset($response[$fields['model']]) && is_scalar($response[$fields['model']])
                ? self::sanitizeDiscoveryText($response[$fields['model']], 80)
                : '';
            $isWPalaControl = $endpoint === '/ffffffff'
                || stripos($model, 'palacontrol') !== false
                || (isset($response['manufacturer']) && stripos((string) $response['manufacturer'], 'domochip') !== false);
            if (!$isWPalaControl) {
                continue;
            }

            $device['gatewayType'] = 'WPalaControl';
            $device['isWirelessPalaControl'] = true;
            if (isset($response[$fields['serial']]) && is_scalar($response[$fields['serial']])) {
                $device['serial'] = self::sanitizeDiscoveryText($response[$fields['serial']], 40);
            }
            if ($model !== '') {
                $device['model'] = $model;
            }
            if (isset($response[$fields['version']]) && is_scalar($response[$fields['version']])) {
                $device['versions'] = self::sanitizeDiscoveryText($response[$fields['version']]);
            }
            return $device;
        }

        return $device;
    }

    /**
     * Prépare un aperçu sans modifier les équipements Jeedom.
     *
     * @param array<int, array<string, mixed>> $devices Appareils découverts.
     * @return array<string, mixed> Aperçu des changements.
     */
    private static function previewDiscoveredDevices($devices)
    {
        $index = self::buildDiscoveryEquipmentIndex();

        $result = array(
            'found' => count($devices),
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'pendingCreate' => 0,
            'pendingUpdate' => 0,
            'skipped' => 0,
            'replaced' => 0,
            'devices' => array()
        );
        foreach ($devices as $device) {
            $eqLogic = self::matchDiscoveredEquipment($device, $index);

            $action = 'create';
            if (is_object($eqLogic)) {
                $action = (string) $eqLogic->getConfiguration('addressip') === (string) $device['ip']
                    ? 'unchanged'
                    : 'update';
            }
            if ($action === 'create') {
                $result['pendingCreate']++;
            } elseif ($action === 'update') {
                $result['pendingUpdate']++;
            } else {
                $result['unchanged']++;
            }
            $result['devices'][] = self::formatDiscoveredDevice($device, $eqLogic, $action);
        }
        return $result;
    }

    /**
     * Applique une découverte selon la stratégie demandée.
     *
     * @param array<int, array<string, mixed>> $devices Appareils découverts.
     * @param string $mode Stratégie d'application.
     * @return array<string, mixed> Résultat des modifications.
     */
    private static function saveDiscoveredDevices($devices, $mode)
    {
        $index = self::buildDiscoveryEquipmentIndex();
        $result = array(
            'found' => count($devices),
            'created' => 0,
            'updated' => 0,
            'unchanged' => 0,
            'pendingCreate' => 0,
            'pendingUpdate' => 0,
            'skipped' => 0,
            'replaced' => 0,
            'devices' => array()
        );

        foreach ($devices as $device) {
            $existing = self::matchDiscoveredEquipment($device, $index);

            if ($mode === 'automatic' && !is_object($existing)) {
                $result['skipped']++;
                $result['devices'][] = self::formatDiscoveredDevice($device, null, 'skipped');
                continue;
            }

            if ($mode === 'replace' && is_object($existing)) {
                $existingName = $existing->getName();
                $existingEnabled = (int) $existing->getIsEnable();
                $existingVisible = (int) $existing->getIsVisible();
                $existingObjectId = method_exists($existing, 'getObject_id') ? $existing->getObject_id() : null;

                $existing->setIsEnable(0);
                $existing->setConfiguration('discoverySuperseded', 1);
                $existing->save();

                $eqLogic = self::createDiscoveredEquipment(
                    $device,
                    $existingName,
                    $existingEnabled,
                    $existingVisible,
                    $existingObjectId,
                    true
                );
                $result['replaced']++;
                $details = self::formatDiscoveredDevice($device, $eqLogic, 'replaced');
                $details['previousId'] = (int) $existing->getId();
                $details['resultId'] = (int) $eqLogic->getId();
                $result['devices'][] = $details;
                continue;
            }

            if (!is_object($existing)) {
                $eqLogic = self::createDiscoveredEquipment($device, $device['name'], 0, 0);
                $result['created']++;
                $details = self::formatDiscoveredDevice($device, $eqLogic, 'created');
                $details['resultId'] = (int) $eqLogic->getId();
                $result['devices'][] = $details;
                continue;
            }

            $changed = self::applyDiscoveredConfiguration($existing, $device, false);
            if ($changed) {
                $existing->save();
                $result['updated']++;
                $action = 'updated';
            } else {
                $result['unchanged']++;
                $action = 'unchanged';
            }
            $existing->setStatus('lastDiscovery', date('Y-m-d H:i:s'));
            $details = self::formatDiscoveredDevice($device, $existing, $action);
            $details['resultId'] = (int) $existing->getId();
            $result['devices'][] = $details;
        }
        return $result;
    }

    /**
     * Indexe les équipements existants par IP, MAC et numéro de série.
     *
     * @return array<string, array<string, Palazzetti>> Index des équipements.
     */
    private static function buildDiscoveryEquipmentIndex()
    {
        $byIp = array();
        $byMac = array();
        $bySerial = array();
        foreach (eqLogic::byType(__CLASS__) as $eqLogic) {
            if ((int) $eqLogic->getConfiguration('discoverySuperseded', 0) === 1) {
                continue;
            }
            $ip = trim((string) $eqLogic->getConfiguration('addressip'));
            $mac = self::normalizeMac($eqLogic->getConfiguration('discoveryMac'));
            $serial = trim((string) $eqLogic->getConfiguration('serialNumber'));
            if ($ip !== '') {
                $byIp[$ip] = $eqLogic;
            }
            if ($mac !== '') {
                $byMac[$mac] = $eqLogic;
            }
            if ($serial !== '') {
                $bySerial[$serial] = $eqLogic;
            }
        }
        return array('ip' => $byIp, 'mac' => $byMac, 'serial' => $bySerial);
    }

    /**
     * Recherche l'équipement correspondant à un appareil découvert.
     *
     * @param array<string, mixed> $device Appareil découvert.
     * @param array<string, array<string, Palazzetti>> $index Index existant.
     * @return Palazzetti|null Équipement correspondant.
     */
    private static function matchDiscoveredEquipment($device, $index)
    {
        if ($device['mac'] !== '' && isset($index['mac'][$device['mac']])) {
            return $index['mac'][$device['mac']];
        }
        if ($device['serial'] !== '' && isset($index['serial'][$device['serial']])) {
            return $index['serial'][$device['serial']];
        }
        if (isset($index['ip'][$device['ip']])) {
            return $index['ip'][$device['ip']];
        }
        return null;
    }

    /**
     * Formate un appareil pour la réponse de découverte AJAX.
     *
     * @param array<string, mixed> $device Appareil découvert.
     * @param Palazzetti|null $eqLogic Équipement associé.
     * @param string $action Action proposée ou appliquée.
     * @return array<string, mixed> Description sérialisable.
     */
    private static function formatDiscoveredDevice($device, $eqLogic, $action)
    {
        return array(
            'id' => is_object($eqLogic) ? (int) $eqLogic->getId() : 0,
            'identity' => self::getDiscoveryDeviceIdentity($device),
            'name' => $device['name'],
            'ip' => $device['ip'],
            'mac' => $device['mac'],
            'serial' => $device['serial'],
            'model' => $device['model'],
            'versions' => $device['versions'],
            'gatewayType' => $device['gatewayType'],
            'isApplianceConnected' => !empty($device['isApplianceConnected']),
            'action' => $action,
            'existingName' => is_object($eqLogic) ? $eqLogic->getName() : '',
            'existingIp' => is_object($eqLogic) ? (string) $eqLogic->getConfiguration('addressip') : '',
            'existingEnabled' => is_object($eqLogic) ? (bool) $eqLogic->getIsEnable() : false
        );
    }

    /**
     * Crée un équipement à partir d'un appareil découvert.
     *
     * @param array<string, mixed> $device Appareil découvert.
     * @param string $name Nom de l'équipement.
     * @param int|bool $enabled État d'activation.
     * @param int|bool $visible État de visibilité.
     * @param int|null $objectId Objet Jeedom associé.
     * @param bool $replacement Indique la création d'un remplacement.
     * @return Palazzetti Équipement créé.
     */
    private static function createDiscoveredEquipment(
        $device,
        $name,
        $enabled,
        $visible,
        $objectId = null,
        $replacement = false
    ) {
        $eqLogic = new self();
        $eqLogic->setEqType_name(__CLASS__);
        $identity = $device['mac'] !== '' ? $device['mac'] : $device['ip'];
        if ($replacement) {
            $identity .= ':' . uniqid('', true);
        }
        $eqLogic->setLogicalId('discovered_' . substr(sha1($identity), 0, 24));
        $eqLogic->setName($name !== '' ? $name : $device['name']);
        $eqLogic->setIsEnable((int) $enabled);
        $eqLogic->setIsVisible((int) $visible);
        if ($objectId !== null && method_exists($eqLogic, 'setObject_id')) {
            $eqLogic->setObject_id($objectId);
        }
        self::applyDiscoveredConfiguration($eqLogic, $device, true);
        $eqLogic->save();
        $eqLogic->setStatus('lastDiscovery', date('Y-m-d H:i:s'));
        return $eqLogic;
    }

    /**
     * Copie les propriétés découvertes dans un équipement.
     *
     * @param Palazzetti $eqLogic Équipement à modifier.
     * @param array<string, mixed> $device Appareil découvert.
     * @param bool $isNew Indique un nouvel équipement.
     * @return bool Vrai si une configuration a changé.
     */
    private static function applyDiscoveredConfiguration($eqLogic, $device, $isNew)
    {
        $changed = false;
        $configuration = array(
            'addressip' => $device['ip'],
            'discoveryMac' => $device['mac'],
            'stoveSerialNumber' => $device['stoveSerial'],
            'stoveModel' => $device['stoveModel'],
            'discoveredByUdp' => 1,
            'discoverySuperseded' => 0
        );
        $knownAsWPalaControl = in_array(
            $eqLogic->getConfiguration('isWirelessPalaControl'),
            array(true, 1, '1', 'true'),
            true
        );
        if ($device['isWirelessPalaControl'] || !$knownAsWPalaControl) {
            $configuration['serialNumber'] = $device['serial'];
            $configuration['model'] = $device['model'];
            $configuration['versions'] = $device['versions'];
            $configuration['gatewayType'] = $device['gatewayType'];
        }
        if ($device['isWirelessPalaControl']) {
            $configuration['isWirelessPalaControl'] = 1;
        }
        foreach ($configuration as $key => $value) {
            if ($value === '' && !$isNew) {
                continue;
            }
            if ((string) $eqLogic->getConfiguration($key, '') !== (string) $value) {
                $eqLogic->setConfiguration($key, $value);
                $changed = true;
            }
        }

        return $changed;
    }

    /**
     * Expose l'état de la dernière communication pour la page Santé.
     *
     * @return array<string, mixed> État réseau et protocolaire mémorisé.
     */
    public function getCommunicationHealth()
    {
        $state = cache::byKey($this->getRequestErrorCacheKey())->getValue(array());
        if (!is_array($state)) {
            $state = array();
        }
        return array(
            'offline' => !empty($state['offline']),
            'stoveOffline' => !empty($state['stoveOffline']),
            'error' => isset($state['error']) ? (string) $state['error'] : '',
            'lastFailure' => isset($state['lastFailure']) ? (int) $state['lastFailure'] : 0,
            'failureCount' => isset($state['failureCount']) ? (int) $state['failureCount'] : 0,
            'lastRediscovery' => isset($state['lastRediscovery']) ? (int) $state['lastRediscovery'] : 0,
            'rediscoveredAddress' => isset($state['rediscoveredAddress']) ? (string) $state['rediscoveredAddress'] : '',
            'lastSuccess' => isset($state['lastSuccess']) ? (int) $state['lastSuccess'] : 0
        );
    }

    /**
     * Rend le nom Jeedom avec l'icône d'objet autorisée, sans réinjecter les
     * noms d'objet ou d'équipement comme HTML.
     *
     * @return string Nom humain échappé avec icône autorisée.
     */
    public function getSafeHumanNameHtml()
    {
        $object = $this->getObject();
        if (is_object($object)) {
            $icon = '';
            $rawIcon = trim((string) $object->getDisplay('icon'));
            if (preg_match('/^<i\s+class=(["\'])([A-Za-z0-9 _-]+)\1\s*><\/i>$/i', $rawIcon, $matches) === 1) {
                $icon = '<i class="' . htmlspecialchars(trim($matches[2]), ENT_QUOTES, 'UTF-8') . '"></i> ';
            }
            $objectLabel = $icon . htmlspecialchars((string) $object->getName(), ENT_QUOTES, 'UTF-8');
        } else {
            $objectLabel = htmlspecialchars(__('Aucun', __FILE__), ENT_QUOTES, 'UTF-8');
        }

        return '<span class="label labelObjectHuman">' . $objectLabel . '</span><br><strong>'
            . htmlspecialchars((string) $this->getName(), ENT_QUOTES, 'UTF-8') . '</strong>';
    }

    /**
     * Valide l'adresse locale avant l'enregistrement de l'équipement.
     *
     * @return void
     * @throws Exception Si l'adresse n'est pas locale ou valide.
     */
    public function preUpdate()
    {
        log::add(__CLASS__, 'debug', __('Début', __FILE__) . ' : ' . __FUNCTION__);

        $address = trim((string) $this->getConfiguration('addressip'));
        if (!self::isLocalAddress($address)) {
            throw new Exception(__('L\'adresse doit être une adresse IPv4 privée ou un nom d\'hôte local valide.', __FILE__));
        }
        $this->setConfiguration('addressip', $address);
        log::add(__CLASS__, 'debug', __('Fin', __FILE__) . ' : ' . __FUNCTION__);
    }

    /**
     * Termine la sauvegarde sans déclencher d'accès réseau bloquant.
     *
     * @return void
     */
    public function postUpdate()
    {
        log::add(__CLASS__, 'debug', __('Début', __FILE__) . ' : ' . __FUNCTION__);

        // Aucun accès réseau pendant une sauvegarde : le cron ou le bouton
        // Rafraîchir s'en charge sans bloquer l'interface Jeedom.
        log::add(__CLASS__, 'debug', __('Fin', __FILE__) . ' : ' . __FUNCTION__);
    }

    /**
     * Crée ou met à niveau les commandes après la sauvegarde.
     *
     * @return void
     */
    public function postSave()
    {
        log::add(__CLASS__, 'debug', __('Début', __FILE__) . ' : ' . __FUNCTION__);
        $this->createCmdFromConfig();
        log::add(__CLASS__, 'debug', __('Fin', __FILE__) . ' : ' . __FUNCTION__);
    }

    /**
     * Met à niveau les commandes existantes sans recréer les équipements.
     *
     * @return void
     */
    public static function migrateEquipmentCommands()
    {
        foreach (eqLogic::byType(__CLASS__) as $eqLogic) {
            if ($eqLogic instanceof self) {
                $eqLogic->createCmdFromConfig();
            }
        }
    }

    public static $_widgetPossibility = array('custom' => array(
        'visibility' => true,
        'displayName' => true,
        'displayObjectName' => true,
        'optionalParameters' => true,
        'background-color' => true,
        'text-color' => true,
        'border' => true,
        'border-radius' => true,
        'background-opacity' => true,
    ));

    /**
     * Crée et met à niveau les commandes définies dans la configuration JSON.
     *
     * @return bool|void Faux si la configuration ne peut pas être chargée.
     */
    private function createCmdFromConfig()
    {
        $cmdConfig = $this->loadCmdFromConf('palazzetti');
        if (!$cmdConfig) {
            return false;
        }

        $existingCommands = $this->getCmd();
        foreach ($cmdConfig as $config) {
            $cmd = null;
            foreach ($existingCommands as $liste_cmd) {
                if ((isset($config['logicalId']) && $liste_cmd->getLogicalId() == $config['logicalId'])
                || (isset($config['name']) && $liste_cmd->getName() == $config['name'])) {
                    $cmd = $liste_cmd;
                    break;
                }
            }
            if ($cmd === null || !is_object($cmd)) {
                log::add(__CLASS__, 'debug', __('Création commande ', __FILE__) . ' : ' . __FUNCTION__ . $config['logicalId']);
                $cmd = new PalazzettiCmd();
                $cmd->setEqLogic_id($this->getId());
                utils::a2o($cmd, $config);
                $cmd->save();
                $existingCommands[] = $cmd;
                continue;
            }

            $changed = false;
            if (isset($config['logicalId']) && $cmd->getLogicalId() !== $config['logicalId']) {
                $cmd->setLogicalId($config['logicalId']);
                $changed = true;
            }
            if (isset($config['type']) && $cmd->getType() !== $config['type']) {
                $cmd->setType($config['type']);
                $changed = true;
            }
            if (isset($config['subType']) && $cmd->getSubType() !== $config['subType']) {
                $cmd->setSubType($config['subType']);
                $changed = true;
            }
            foreach (isset($config['configuration']) ? $config['configuration'] : array() as $key => $value) {
                if ((string) $cmd->getConfiguration($key, '') !== (string) $value) {
                    $cmd->setConfiguration($key, $value);
                    $changed = true;
                }
            }

            // Migration ciblée de la correction sémantique OVERTMPERRORS :
            // il s'agit d'un compteur d'erreurs, pas d'une durée.
            if (in_array($config['logicalId'], array('IHeuresSurChauffe', 'RHeuresSurChauffe'), true)
                && $cmd->getName() !== $config['name']) {
                $cmd->setName($config['name']);
                $changed = true;
            }
            if ($config['logicalId'] === 'IHeuresSurChauffe'
                && $cmd->getUnite() !== (isset($config['unite']) ? $config['unite'] : '')) {
                $cmd->setUnite(isset($config['unite']) ? $config['unite'] : '');
                $changed = true;
            }
            // Le chronogramme v2 s'appuie sur les transitions d'état. La
            // migration n'impose l'historisation qu'à cette commande précise.
            if ($config['logicalId'] === 'IStatus' && (int) $cmd->getIsHistorized() !== 1) {
                $cmd->setIsHistorized(1);
                $changed = true;
            }
            if ($changed) {
                $cmd->save();
            }
        }
    }

    /**
     * Charge un fichier de configuration de commandes autorisé.
     *
     * @param mixed $type Nom logique du fichier sans extension.
     * @return array<int, array<string, mixed>>|false Configuration décodée.
     */
    public function loadCmdFromConf($type)
    {
        $type = trim((string) $type);
        if (preg_match('/^[A-Za-z0-9_-]+$/', $type) !== 1) {
            log::add(__CLASS__, 'warning', __('Nom de configuration de commandes invalide.', __FILE__));
            return false;
        }
        $configurationFile = dirname(__FILE__) . '/../../core/config/' . $type . '.json';
        if (!is_file($configurationFile)) {
            log::add(__CLASS__, 'debug', __('Fichier introuvable : ', __FILE__) . $configurationFile);
            return false;
        }
        $content = file_get_contents($configurationFile);
        if (!is_json($content)) {
            log::add(__CLASS__, 'debug', __('JSON invalide : ', __FILE__) . $type . '.json');
            return false;
        }
        $device = json_decode($content, true);
        if (!is_array($device) || !isset($device)) {
            log::add(__CLASS__, 'debug', __('Tableau incorrect : ', __FILE__) . $type . '.json');
            return false;
        }
        return $device;
    }

    /**
     * Exécute une commande HTTP validée sur la passerelle locale.
     *
     * @param mixed $cmd Commande Palazzetti.
     * @param int $_timeout Délai d'attente en secondes.
     * @return object|false Réponse JSON décodée, ou faux en cas d'échec.
     */
    public function makeRequest($cmd, $_timeout = 5)
    {
        $requestedCommand = (string) $cmd;
        try {
            $cmd = self::normalizeCommand($requestedCommand);
        } catch (InvalidArgumentException $e) {
            log::add(__CLASS__, 'warning', __FUNCTION__ . ' - ' . $e->getMessage());
            return false;
        }

        $address = trim((string) $this->getConfiguration('addressip'));
        if (!self::isLocalAddress($address)) {
            $this->reportRequestFailure($cmd, __('adresse invalide : ', __FILE__) . $address);
            return false;
        }

        $requestAddress = $address;
        if (!self::isPrivateIpv4($requestAddress)) {
            $resolvedAddress = @gethostbyname($requestAddress);
            if (!self::isPrivateIpv4($resolvedAddress)) {
                $this->reportRequestFailure($cmd, __('nom d\'hôte non résolu vers le réseau privé : ', __FILE__) . $address);
                return false;
            }
            $requestAddress = $resolvedAddress;
        }

        $baseUrl = 'http://' . $requestAddress . '/cgi-bin/sendmsg.lua?cmd=';
        $commandVariants = array($cmd, rawurlencode($cmd));
        $lastError = '';
        $gatewayResponded = false;

        foreach (array_unique($commandVariants) as $commandVariant) {
            $url = $baseUrl . $commandVariant;
            log::add(__CLASS__, 'debug', __FUNCTION__ . ' - get URL ' . $url);
            try {
                $requestHttp = new com_http($url);
                $rawResponse = $requestHttp->exec($_timeout, 2);
            } catch (Exception $e) {
                $lastError = $e->getMessage();
                log::add(__CLASS__, 'debug', __FUNCTION__ . ' - ' . $e->getCode() . __(' problème de connexion : ', __FILE__) . $lastError);
                continue;
            }

            if (!is_string($rawResponse) || trim($rawResponse) === '') {
                $lastError = __('réponse vide', __FILE__);
                continue;
            }
            $gatewayResponded = true;

            $response = json_decode($rawResponse);
            if (!is_object($response)) {
                $lastError = __('JSON invalide', __FILE__);
                continue;
            }

            if (isset($response->INFO) && is_object($response->INFO) && isset($response->INFO->RSP)) {
                $rsp = strtoupper(trim((string) $response->INFO->RSP));
                if ($rsp !== 'OK') {
                    $this->setStatus('lastGatewayCommunication', date('Y-m-d H:i:s'));
                    $this->reportProtocolFailure($cmd, __('réponse de la passerelle : ', __FILE__) . $rsp);
                    return false;
                }
                log::add(__CLASS__, 'debug', __FUNCTION__ . __(' - résultat : ', __FILE__) . json_encode($response));
                $this->markCommunicationSuccess();
                return $response;
            }
            if (property_exists($response, 'PARM') || property_exists($response, 'HPAR') || property_exists($response, 'DATA')) {
                log::add(__CLASS__, 'debug', __FUNCTION__ . __(' - résultat données : ', __FILE__) . json_encode($response));
                $this->markCommunicationSuccess();
                return $response;
            }

            $lastError = __('réponse non reconnue', __FILE__);
        }

        if ($gatewayResponded) {
            $this->setStatus('lastGatewayCommunication', date('Y-m-d H:i:s'));
            $this->reportProtocolFailure($cmd, $lastError);
        } else {
            $this->reportRequestFailure($cmd, $lastError);
        }
        return false;
    }

    /**
     * Retourne la clé de cache de l'état de communication.
     *
     * @return string Clé de cache propre à l'équipement.
     */
    private function getRequestErrorCacheKey()
    {
        return __CLASS__ . '::requestError::' . $this->getId();
    }

    /**
     * Mémorise un échec réseau et tente une redécouverte si nécessaire.
     *
     * @param string $cmd Commande concernée.
     * @param mixed $error Erreur retournée.
     * @return void
     */
    private function reportRequestFailure($cmd, $error)
    {
        $now = time();
        $error = trim((string) $error);
        $signature = sha1($error);
        $state = cache::byKey($this->getRequestErrorCacheKey())->getValue(array());
        if (!is_array($state)) {
            $state = array();
        }

        $lastLog = isset($state['lastLog']) ? intval($state['lastLog']) : 0;
        $lastSignature = isset($state['signature']) ? (string) $state['signature'] : '';
        $failureCount = !empty($state['offline']) && isset($state['failureCount'])
            ? (int) $state['failureCount'] + 1
            : 1;
        if ($lastSignature !== $signature || ($now - $lastLog) >= self::REQUEST_ERROR_LOG_INTERVAL) {
            $message = 'makeRequest' . __(' - aucune réponse exploitable pour ', __FILE__) . $cmd;
            if ($error !== '') {
                $message .= ' (' . $error . ')';
            }
            log::add(__CLASS__, 'error', $message);
            $lastLog = $now;
        }

        $state = array_merge($state, array(
            'offline' => 1,
            'stoveOffline' => 0,
            'lastLog' => $lastLog,
            'signature' => $signature,
            'error' => $error,
            'lastFailure' => $now,
            'failureCount' => $failureCount
        ));
        cache::set($this->getRequestErrorCacheKey(), $state, 0);

        if ($failureCount >= self::REDISCOVERY_FAILURE_THRESHOLD) {
            $this->attemptRediscovery($state);
        }
    }

    /**
     * La passerelle HTTP répond, mais le protocole ou le poêle est indisponible.
     *
     * @param string $cmd Commande concernée.
     * @param mixed $error Erreur protocolaire.
     * @return void
     */
    private function reportProtocolFailure($cmd, $error)
    {
        $now = time();
        $error = trim((string) $error);
        $signature = sha1('protocol:' . $error);
        $state = cache::byKey($this->getRequestErrorCacheKey())->getValue(array());
        if (!is_array($state)) {
            $state = array();
        }

        $lastLog = isset($state['lastLog']) ? (int) $state['lastLog'] : 0;
        $lastSignature = isset($state['signature']) ? (string) $state['signature'] : '';
        $failureCount = !empty($state['stoveOffline']) && isset($state['failureCount'])
            ? (int) $state['failureCount'] + 1
            : 1;
        if ($lastSignature !== $signature || ($now - $lastLog) >= self::REQUEST_ERROR_LOG_INTERVAL) {
            log::add(__CLASS__, 'warning', sprintf(
                __('Passerelle joignable, mais requête %s indisponible : %s', __FILE__),
                $cmd,
                $error
            ));
            $lastLog = $now;
        }

        $state = array_merge($state, array(
            'offline' => 0,
            'stoveOffline' => 1,
            'lastLog' => $lastLog,
            'signature' => $signature,
            'error' => $error,
            'lastFailure' => $now,
            'failureCount' => $failureCount
        ));
        cache::set($this->getRequestErrorCacheKey(), $state, 0);
    }

    /**
     * Réinitialise les erreurs et enregistre une communication réussie.
     *
     * @return void
     */
    private function markCommunicationSuccess()
    {
        $state = cache::byKey($this->getRequestErrorCacheKey())->getValue(array());
        if (is_array($state) && (!empty($state['offline']) || !empty($state['stoveOffline']))) {
            log::add(__CLASS__, 'info', __('Connexion rétablie avec ', __FILE__) . $this->getConfiguration('addressip'));
        }
        if (!is_array($state)) {
            $state = array();
        }
        $now = time();
        $state = array_merge($state, array(
            'offline' => 0,
            'stoveOffline' => 0,
            'lastLog' => 0,
            'signature' => '',
            'error' => '',
            'failureCount' => 0,
            'lastSuccess' => $now
        ));
        if (isset($state['rediscoveredAddress'])
            && (string) $state['rediscoveredAddress'] === (string) $this->getConfiguration('addressip')) {
            $state['rediscoveredAddress'] = '';
        }
        cache::set($this->getRequestErrorCacheKey(), $state, 0);
        $communicationDate = date('Y-m-d H:i:s', $now);
        $this->setStatus('lastGatewayCommunication', $communicationDate);
        $this->setStatus('lastCommunication', $communicationDate);
    }

    /**
     * Recherche une nouvelle adresse après plusieurs échecs réseau.
     *
     * @param array<string, mixed> $state État courant de communication.
     * @return void
     */
    private function attemptRediscovery($state)
    {
        $now = time();
        $lastRediscovery = isset($state['lastRediscovery']) ? (int) $state['lastRediscovery'] : 0;
        if (self::$rediscoveryRunning || ($now - $lastRediscovery) < self::REDISCOVERY_COOLDOWN) {
            return;
        }

        self::$rediscoveryRunning = true;
        $state['lastRediscovery'] = $now;
        cache::set($this->getRequestErrorCacheKey(), $state, 0);
        try {
            $previousAddress = (string) $this->getConfiguration('addressip');
            $result = self::discover('automatic');
            foreach ($result['devices'] as $device) {
                if (!isset($device['resultId']) || (int) $device['resultId'] !== (int) $this->getId()) {
                    continue;
                }
                if ((string) $device['ip'] !== $previousAddress) {
                    $this->setConfiguration('addressip', (string) $device['ip']);
                    log::add(__CLASS__, 'info', sprintf(
                        __('Adresse automatiquement actualisée pour %s : %s -> %s.', __FILE__),
                        $this->getHumanName(),
                        $previousAddress,
                        $device['ip']
                    ));
                }
                break;
            }
        } catch (Exception $e) {
            log::add(__CLASS__, 'debug', __FUNCTION__ . ' - ' . $e->getMessage());
        } finally {
            self::$rediscoveryRunning = false;
        }
    }

    /**
     * Traduit la vitesse du ventilateur principal selon sa configuration.
     *
     * @param mixed $num Valeur brute.
     * @return mixed Libellé spécial ou valeur d'origine.
     */
    public function getFanState($num)
    {
        switch ($num) {
            case 0:
                return $this->getConfiguration('invertFanSpeed', false) ? 'OFF' : 'AUTO';
            case 6:
                return 'HIGH';
            case 7:
                return $this->getConfiguration('invertFanSpeed', false) ? 'AUTO' : 'OFF';
            default:
                return $num;
        }
    }

    /**
     * Traduit l'état binaire du ventilateur F3L.
     *
     * @param mixed $num Valeur brute.
     * @return mixed Libellé ON/OFF ou valeur d'origine.
     */
    public static function getFanStateF3L($num)
    {
        switch ($num) {
            case 0:
                return 'OFF';
            case 1:
                return 'ON';
            default:
                return $num;
        }
    }

    /**
     * Traduit l'état binaire du ventilateur F4L.
     *
     * @param mixed $num Valeur brute.
     * @return mixed Libellé ON/OFF ou valeur d'origine.
     */
    public static function getFanStateF4L($num)
    {
        switch ($num) {
            case 0:
                return 'OFF';
            case 1:
                return 'ON';
            default:
                return $num;
        }
    }

    /**
     * Traduit un code d'état du poêle.
     *
     * @param mixed $num Code d'état.
     * @return mixed Libellé traduit ou code d'origine.
     */
    public static function getStoveState($num)
    {
        $lib = [
            0 => __('Eteint', __FILE__),
            1 => __('Arrêté', __FILE__),
            2 => __('Vérification', __FILE__),
            3 => __('Chargement granulés', __FILE__),
            4 => __('Allumage', __FILE__),
            5 => __('Contrôle combustion', __FILE__),
            6 => __('En chauffe', __FILE__),
            9 => __('Diffusion', __FILE__),
            10 => __('Extinction', __FILE__),
            11 => __('Nettoyage', __FILE__),
            12 => __('Refroidissement', __FILE__),
            241 => __('Erreur Nettoyage', __FILE__),
            243 => __('Erreur Grille', __FILE__),
            244 => __('NTC2 ALARM', __FILE__),
            245 => __('NTC3 ALARM', __FILE__),
            247 => __('Erreur Porte', __FILE__),
            248 => __('Erreur Dépression', __FILE__),
            249 => __('NTC1 ALARM', __FILE__),
            250 => __('TC1 ALARM', __FILE__),
            252 => __('Erreur évacuation Fumées', __FILE__),
            253 => __('Pas de pellets', __FILE__)
        ];

        return $lib[$num] ?? $num;
    }

    /**
     * Traduit un numéro de jour Palazzetti.
     *
     * @param mixed $num Numéro de 1 à 7.
     * @return string Libellé du jour ou libellé générique.
     */
    public static function getWeekDay($num)
    {
        $lib = [
            1 => __('Lundi', __FILE__),
            2 => __('Mardi', __FILE__),
            3 => __('Mercredi', __FILE__),
            4 => __('Jeudi', __FILE__),
            5 => __('Vendredi', __FILE__),
            6 => __('Samedi', __FILE__),
            7 => __('Dimanche', __FILE__)
        ];

        return $lib[$num] ?? __('Jour #', __FILE__) . $num;
    }

    /**
     * Envoie une commande d'action et met à jour sa commande d'information.
     *
     * @param PalazzettiCmd $CMD Commande Jeedom exécutée.
     * @param mixed $_options Options transmises par Jeedom.
     * @return string|false Résultat d'exécution.
     */
    public function sendCommand($CMD, $_options)
    {
        try {
            $cmdString = $this->buildCommandString($CMD, $_options);
        } catch (Exception $e) {
            log::add(__CLASS__, 'warning', sprintf(
                __('Commande %s refusée : %s', __FILE__),
                $CMD->getHumanName(),
                $e->getMessage()
            ));
            return 'ERROR';
        }

        log::add(__CLASS__, 'debug', __FUNCTION__ . __(' - commande ', __FILE__) . $cmdString);
        $DATA = $this->makeRequest($cmdString, 6);
        if (!$DATA) {
            return 'ERROR';
        }

        $expl = explode('+', $cmdString);
        if (count($expl) < 2 || !isset($DATA->DATA) || !is_object($DATA->DATA)) {
            log::add(__CLASS__, 'warning', __FUNCTION__ . __(' - réponse sans données pour ', __FILE__) . $cmdString);
            return 'ERROR';
        }
        $pattern = $expl[0] . '+' . $expl[1];
        $value = null;

        switch ($pattern) {
            case 'CMD+ON':
            case 'CMD+OFF':
            case 'GET+STAT':
                $value = $this->responseValue($DATA->DATA, 'STATUS');
                break;
            case 'GET+ALLS':
                $this->updateAllsData($DATA->DATA, $DATA);
                $value = json_encode($DATA->DATA);
                break;
            case 'GET+LABL':
            case 'SET+LABL':
                $value = $this->responseValue($DATA->DATA, 'LABEL');
                break;
            case 'GET+POWR':
            case 'SET+POWR':
                $this->updateCommandIfPresent('IFan', $DATA->DATA, 'F2L');
                $value = $this->responseValue($DATA->DATA, 'PWR');
                break;
            case 'GET+SETP':
            case 'SET+SETP':
                $value = $this->responseValue($DATA->DATA, 'SETP');
                break;
            case 'GET+FAND':
                $value = $this->responseValue($DATA->DATA, 'F2L');
                break;
            case 'SET+RFAN':
                $this->updateCommandIfPresent('IPower', $DATA->DATA, 'PWR');
                $value = $this->responseValue($DATA->DATA, 'F2L');
                break;
            case 'SET+FN3L':
                $value = $this->responseValue($DATA->DATA, 'F3L');
                break;
            case 'SET+FN4L':
                $value = $this->responseValue($DATA->DATA, 'F4L');
                break;
            case 'GET+TMPS':
                $value = $this->responseValue($DATA->DATA, 'T1');
                $this->updateCommandIfPresent('ITemp2', $DATA->DATA, 'T2');
                $this->updateCommandIfPresent('ITemp3', $DATA->DATA, 'T3');
                break;
            case 'GET+CHRD':
                $value = json_encode($DATA->DATA);
                break;
            case 'GET+CNTR':
                $this->updateCounters($DATA->DATA);
                $value = json_encode($DATA->DATA);
                break;
            case 'EXT+ADRD':
                $property = isset($expl[2]) ? 'ADDR_' . $expl[2] : '';
                $value = $property !== '' ? $this->responseValue($DATA->DATA, $property) : null;
                break;
            default:
                $value = json_encode($DATA->DATA);
                break;
        }

        $updateLogicalId = $CMD->getConfiguration('updateLogicalId');
        if ($value !== null && $updateLogicalId !== '') {
            $INFO = $this->getCmd('info', $updateLogicalId);
            if (is_object($INFO)) {
                $INFO->event($value);
            }
        }
        return 'OK';
    }

    /**
     * Construit une commande d'action à partir d'options strictement validées.
     *
     * @param PalazzettiCmd $cmd Commande Jeedom.
     * @param mixed $options Options d'exécution.
     * @return string Commande Palazzetti validée.
     * @throws Exception Si les options sont invalides.
     */
    private function buildCommandString($cmd, $options)
    {
        $base = trim((string) $cmd->getConfiguration('actionCmd'));
        if ($base === '') {
            throw new Exception(__('commande vide', __FILE__));
        }

        $suffix = '';
        if (is_array($options)) {
            // Jeedom ajoute ces métadonnées au tableau transmis à execute().
            // Elles décrivent le contexte d'appel et non la valeur métier de
            // la commande.
            $options = array_diff_key($options, array(
                'user_login' => 0,
                'user_id' => 0,
                'username' => 0,
                'callEvent' => 0,
                'data' => 0,
                'linkData' => 0,
                'tags' => 0,
            ));

            // Les deux commandes de planning utilisent un payload interne au
            // plugin, construit par desktop/modal/planning.php. Ce ne sont pas
            // des options Jeedom génériques.
            if ($base === 'SET+CDAY+') {
                if (array_diff_key($options, array('jour' => 0, 'tranche' => 0, 'programme' => 0)) !== array()) {
                    throw new Exception(__('options de planning non reconnues', __FILE__));
                }
                if (!isset($options['jour'], $options['tranche'], $options['programme'])) {
                    throw new Exception(__('options de planning incomplètes', __FILE__));
                }
                $suffix = implode('+', array(
                    $this->validatedInteger($options['jour'], 1, 7, 'jour'),
                    $this->validatedInteger($options['tranche'], 1, 3, 'tranche'),
                    $this->validatedInteger($options['programme'], 0, 6, 'programme')
                ));
            } elseif ($base === 'SET+CPRD+') {
                if (array_diff_key($options, array('numero' => 0, 'temperature' => 0, 'h1' => 0, 'm1' => 0, 'h2' => 0, 'm2' => 0)) !== array()) {
                    throw new Exception(__('options de tranche non reconnues', __FILE__));
                }
                if (!isset($options['numero'], $options['temperature'], $options['h1'], $options['m1'], $options['h2'], $options['m2'])) {
                    throw new Exception(__('options de tranche incomplètes', __FILE__));
                }
                if (!is_numeric($options['temperature']) || (float) $options['temperature'] < 0 || (float) $options['temperature'] > 40) {
                    throw new Exception(__('température hors limites', __FILE__));
                }
                $suffix = implode('+', array(
                    $this->validatedInteger($options['numero'], 1, 6, 'numero'),
                    (string) (float) $options['temperature'],
                    $this->validatedInteger($options['h1'], 0, 23, 'h1'),
                    $this->validatedInteger($options['m1'], 0, 59, 'm1'),
                    $this->validatedInteger($options['h2'], 0, 23, 'h2'),
                    $this->validatedInteger($options['m2'], 0, 59, 'm2')
                ));
            } elseif (in_array($base, array('SET+POWR+', 'SET+SETP+', 'SET+RFAN+', 'SET+FN3L+', 'SET+FN4L+'), true)) {
                if (array_diff_key($options, array('slider' => 0)) !== array()) {
                    throw new Exception(__('options de slider non reconnues', __FILE__));
                }
                if (!array_key_exists('slider', $options)) {
                    throw new Exception(__('option slider manquante', __FILE__));
                }
                $minimum = is_numeric($cmd->getConfiguration('minValue')) ? (float) $cmd->getConfiguration('minValue') : 0;
                $maximum = is_numeric($cmd->getConfiguration('maxValue')) ? (float) $cmd->getConfiguration('maxValue') : 255;
                if (!is_numeric($options['slider'])
                    || (float) $options['slider'] < $minimum
                    || (float) $options['slider'] > $maximum) {
                    throw new Exception(__('valeur hors limites', __FILE__));
                }
                $suffix = (string) (float) $options['slider'];
            } elseif ($base === 'SET+LABL+') {
                if (array_diff_key($options, array('message' => 0, 'title' => 0)) !== array()) {
                    throw new Exception(__('options de message non reconnues', __FILE__));
                }
                if (!array_key_exists('message', $options)) {
                    throw new Exception(__('option message manquante', __FILE__));
                }
                $suffix = self::sanitizeDiscoveryText((string) $options['message'], 40);
                if (strpos($suffix, '+') !== false) {
                    throw new Exception(__('libellé invalide', __FILE__));
                }
            } elseif ($options !== array()) {
                throw new Exception(__('options non reconnues', __FILE__));
            }
        } elseif ($options !== null && $options !== '') {
            if ($base !== 'SET+CSST+' || !is_numeric($options)) {
                throw new Exception(__('valeur scalaire non autorisée', __FILE__));
            }
            $suffix = (string) (float) $options;
        }

        $command = self::normalizeCommand($base . $suffix);
        if ($command === null) {
            throw new Exception(__('format de commande invalide', __FILE__));
        }
        return $command;
    }

    /**
     * Valide un entier inclus dans une plage fermée.
     *
     * @param mixed $value Valeur brute.
     * @param int $minimum Borne minimale.
     * @param int $maximum Borne maximale.
     * @param string $name Nom utilisé dans le message d'erreur.
     * @return int Entier validé.
     * @throws Exception Si la valeur est hors limites.
     */
    private function validatedInteger($value, $minimum, $maximum, $name)
    {
        $validated = filter_var($value, FILTER_VALIDATE_INT);
        if ($validated === false || $validated < $minimum || $validated > $maximum) {
            throw new Exception(sprintf(__('%s hors limites', __FILE__), $name));
        }
        return $validated;
    }

    /**
     * Lit une propriété dans un objet de réponse.
     *
     * @param mixed $data Objet de données.
     * @param string $property Propriété recherchée.
     * @return mixed|null Valeur trouvée.
     */
    private function responseValue($data, $property)
    {
        return is_object($data) && property_exists($data, $property) ? $data->{$property} : null;
    }

    /**
     * Met à jour une commande lorsque la propriété source existe.
     *
     * @param string $logicalId Identifiant logique Jeedom.
     * @param mixed $data Objet de données.
     * @param string $property Propriété source.
     * @param int|null $round Nombre de décimales éventuel.
     * @return void
     */
    private function updateCommandIfPresent($logicalId, $data, $property, $round = null)
    {
        $value = $this->responseValue($data, $property);
        if ($value === null) {
            return;
        }
        if ($round !== null && is_numeric($value)) {
            $value = round((float) $value, $round);
        }
        $this->checkAndUpdateCmd($logicalId, $value);
    }

    /**
     * Encode une valeur destinée à être insérée comme littéral JavaScript.
     *
     * @param mixed $value Valeur à encoder.
     * @return string Littéral JSON sûr.
     */
    private static function encodeInlineJavaScriptValue($value)
    {
        $encoded = json_encode(
            (string) $value,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
        return is_string($encoded) ? $encoded : '""';
    }

    /**
     * Génère le widget personnalisé ou délègue au rendu Jeedom standard.
     *
     * @param string $_version Version dashboard, mobile ou scénario.
     * @return string HTML du widget.
     */
    public function toHtml($_version = 'dashboard')
    {
        if ($this->getConfiguration('widgetTemplate') != 1) {
            return parent::toHtml($_version);
        }
        $replace = $this->preToHtml($_version);
        if (!is_array($replace)) {
            return $replace;
        }
        $version = jeedom::versionAlias($_version);

        $replace['#invertFanSpeed#'] = $this->getConfiguration('invertFanSpeed', false);
        $replace['#lastCommunication#'] = $this->getStatus('lastCommunication');

        $heure = $this->getCmd('info', 'ITime');
        $replace['#heure#'] = is_object($heure) ? $heure->execCmd() : '';
        $replace['#heure_id#'] = is_object($heure) ? $heure->getId() : '';
        $replace['#heure_display#'] = (is_object($heure) && $heure->getIsVisible()) ? '' : 'none';

        $temps = $this->getCmd('info', 'ITemp');
        $replace['#temperature#'] = is_object($temps) ? $temps->execCmd() : '';
        $replace['#temperature_id#'] = is_object($temps) ? $temps->getId() : '';
        $replace['#temperature_valueDate#'] = is_object($temps) ? $temps->getValueDate() : '';
        $replace['#temperature_collectDate#'] = is_object($temps) ? $temps->getCollectDate() : '';
        $replace['#temperature_unite#'] = is_object($temps) ? $temps->getUnite() : '';
        $replace['#temperature_display#'] = (is_object($temps) && $temps->getIsVisible()) ? '' : 'none';

        $temps2 = $this->getCmd('info', 'ITemp2');
        $replace['#temperature2#'] = is_object($temps2) ? $temps2->execCmd() : '';
        $replace['#temperature2_id#'] = is_object($temps2) ? $temps2->getId() : '';
        $replace['#temperature2_valueDate#'] = is_object($temps2) ? $temps2->getValueDate() : '';
        $replace['#temperature2_collectDate#'] = is_object($temps2) ? $temps2->getCollectDate() : '';
        $replace['#temperature2_unite#'] = is_object($temps2) ? $temps2->getUnite() : '';
        $replace['#temperature2_display#'] = (is_object($temps2) && $temps2->getIsVisible()) ? '' : 'none';

        $temps3 = $this->getCmd('info', 'ITemp3');
        $replace['#temperature3#'] = is_object($temps3) ? $temps3->execCmd() : '';
        $replace['#temperature3_id#'] = is_object($temps3) ? $temps3->getId() : '';
        $replace['#temperature3_valueDate#'] = is_object($temps3) ? $temps3->getValueDate() : '';
        $replace['#temperature3_collectDate#'] = is_object($temps3) ? $temps3->getCollectDate() : '';
        $replace['#temperature3_unite#'] = is_object($temps3) ? $temps3->getUnite() : '';
        $replace['#temperature3_display#'] = (is_object($temps3) && $temps3->getIsVisible()) ? '' : 'none';

        $status = $this->getCmd('info', 'IStatus');
        $replace['#status#'] = is_object($status) ? $status->execCmd() : '';
        $replace['#status_id#'] = is_object($status) ? $status->getId() : '';
        $replace['#status_display#'] = (is_object($status) && $status->getIsVisible()) ? '' : 'none';

        $consigne = $this->getCmd('info', 'IConsigne');
        $replace['#consigne#'] = is_object($consigne) ? $consigne->execCmd() : '';
        $replace['#consigne_id#'] = is_object($consigne) ? $consigne->getId() : '';
        $replace['#consigne_valueDate#'] = is_object($consigne) ? $consigne->getValueDate() : '';
        $replace['#consigne_collectDate#'] = is_object($consigne) ? $consigne->getCollectDate() : '';
        $replace['#consigne_unite#'] = is_object($consigne) ? $consigne->getUnite() : '';
        $replace['#consigne_minValue#'] = is_object($consigne) ? $consigne->getConfiguration('minValue') : '';
        $replace['#consigne_maxValue#'] = is_object($consigne) ? $consigne->getConfiguration('maxValue') : '';
        $replace['#consigne_display#'] = (is_object($consigne) && $consigne->getIsVisible()) ? '' : 'none';
        $Wconsigne = $this->getCmd('action', 'WConsigne');
        $replace['#consigneSet_id#'] = is_object($Wconsigne) ? $Wconsigne->getId() : '';
        $replace['#consigne_step#'] = 1;
        if (is_object($Wconsigne)) {
            if (is_array($Wconsigne->getDisplay('parameters'))) {
                foreach ($Wconsigne->getDisplay('parameters') as $key => $value) {
                    $replace['#consigne_' . $key . '#'] = $value;
                }
            }
        }

        $power = $this->getCmd('info', 'IPower');
        $replace['#power#'] = is_object($power) ? $power->execCmd() : '';
        $replace['#power_id#'] = is_object($power) ? $power->getId() : '';
        $replace['#power_valueDate#'] = is_object($power) ? $power->getValueDate() : '';
        $replace['#power_collectDate#'] = is_object($power) ? $power->getCollectDate() : '';
        $replace['#power_minValue#'] = is_object($power) ? $power->getConfiguration('minValue') : '';
        $replace['#power_maxValue#'] = is_object($power) ? $power->getConfiguration('maxValue') : '';
        $replace['#power_display#'] = (is_object($power) && $power->getIsVisible()) ? '' : 'none';
        $Wpower = $this->getCmd('action', 'WPower');
        $replace['#powerSet_id#'] = is_object($Wpower) ? $Wpower->getId() : '';
        $replace['#power_step#'] = 1;
        if (is_object($Wpower)) {
            if (is_array($Wpower->getDisplay('parameters'))) {
                foreach ($Wpower->getDisplay('parameters') as $key => $value) {
                    $replace['#power_' . $key . '#'] = $value;
                }
            }
        }

        $fan = $this->getCmd('info', 'IFan');
        $replace['#fan#'] = is_object($fan) ? $fan->execCmd() : '';
        $replace['#fan_id#'] = is_object($fan) ? $fan->getId() : '';
        $replace['#fan_valueDate#'] = is_object($fan) ? $fan->getValueDate() : '';
        $replace['#fan_collectDate#'] = is_object($fan) ? $fan->getCollectDate() : '';
        $replace['#fan_minValue#'] = is_object($fan) ? $fan->getConfiguration('minValue') : '';
        $replace['#fan_maxValue#'] = is_object($fan) ? $fan->getConfiguration('maxValue') : '';
        $replace['#fan_display#'] = (is_object($fan) && $fan->getIsVisible()) ? '' : 'none';
        $Wfan = $this->getCmd('action', 'WFan');
        $replace['#fanSet_id#'] = is_object($Wfan) ? $Wfan->getId() : '';
        $replace['#fan_step#'] = 1;
        if (is_object($Wfan)) {
            if (is_array($Wfan->getDisplay('parameters'))) {
                foreach ($Wfan->getDisplay('parameters') as $key => $value) {
                    $replace['#fan_' . $key . '#'] = $value;
                }
            }
        }

        $nbAll = $this->getCmd('info', 'INbAllumage');
        $replace['#nbAll#'] = is_object($nbAll) ? $nbAll->execCmd() : '';
        $replace['#nbAll_id#'] = is_object($nbAll) ? $nbAll->getId() : '';
        $replace['#nbAll_unit#'] = is_object($nbAll) ? $nbAll->getUnite() : '';
        $replace['#nbAll_valueDate#'] = is_object($nbAll) ? $nbAll->getValueDate() : '';
        $replace['#nbAll_collectDate#'] = is_object($nbAll) ? $nbAll->getCollectDate() : '';
        $replace['#nbAll_display#'] = (is_object($nbAll) && $nbAll->getIsVisible()) ? '' : 'none';

        $hae = $this->getCmd('info', 'IHeuresAlimElec');
        $replace['#hae#'] = is_object($hae) ? $hae->execCmd() : '';
        $replace['#hae_id#'] = is_object($hae) ? $hae->getId() : '';
        $replace['#hae_unit#'] = is_object($hae) ? $hae->getUnite() : '';
        $replace['#hae_valueDate#'] = is_object($hae) ? $hae->getValueDate() : '';
        $replace['#hae_collectDate#'] = is_object($hae) ? $hae->getCollectDate() : '';
        $replace['#hae_display#'] = (is_object($hae) && $hae->getIsVisible()) ? '' : 'none';

        $hc = $this->getCmd('info', 'IHeuresChauffe');
        $replace['#hc#'] = is_object($hc) ? $hc->execCmd() : '';
        $replace['#hc_id#'] = is_object($hc) ? $hc->getId() : '';
        $replace['#hc_unit#'] = is_object($hc) ? $hc->getUnite() : '';
        $replace['#hc_valueDate#'] = is_object($hc) ? $hc->getValueDate() : '';
        $replace['#hc_collectDate#'] = is_object($hc) ? $hc->getCollectDate() : '';
        $replace['#hc_display#'] = (is_object($hc) && $hc->getIsVisible()) ? '' : 'none';

        $hsc = $this->getCmd('info', 'IHeuresSurChauffe');
        $replace['#hsc#'] = is_object($hsc) ? $hsc->execCmd() : '';
        $replace['#hsc_id#'] = is_object($hsc) ? $hsc->getId() : '';
        $replace['#hsc_unit#'] = is_object($hsc) ? $hsc->getUnite() : '';
        $replace['#hsc_valueDate#'] = is_object($hsc) ? $hsc->getValueDate() : '';
        $replace['#hsc_collectDate#'] = is_object($hsc) ? $hsc->getCollectDate() : '';
        $replace['#hsc_display#'] = (is_object($hsc) && $hsc->getIsVisible()) ? '' : 'none';

        $hde = $this->getCmd('info', 'IHeuresDepuisEntretien');
        $replace['#hde#'] = is_object($hde) ? $hde->execCmd() : '';
        $replace['#hde_id#'] = is_object($hde) ? $hde->getId() : '';
        $replace['#hde_unit#'] = is_object($hde) ? $hde->getUnite() : '';
        $replace['#hde_valueDate#'] = is_object($hde) ? $hde->getValueDate() : '';
        $replace['#hde_collectDate#'] = is_object($hde) ? $hde->getCollectDate() : '';
        $replace['#hde_display#'] = (is_object($hde) && $hde->getIsVisible()) ? '' : 'none';

        $haf = $this->getCmd('info', 'INbAllumageFailed');
        $replace['#haf#'] = is_object($haf) ? $haf->execCmd() : '';
        $replace['#haf_id#'] = is_object($haf) ? $haf->getId() : '';
        $replace['#haf_unit#'] = is_object($haf) ? $haf->getUnite() : '';
        $replace['#haf_valueDate#'] = is_object($haf) ? $haf->getValueDate() : '';
        $replace['#haf_collectDate#'] = is_object($haf) ? $haf->getCollectDate() : '';
        $replace['#haf_display#'] = (is_object($haf) && $haf->getIsVisible()) ? '' : 'none';

        $pqt = $this->getCmd('info', 'IQuantite');
        $replace['#pqt#'] = is_object($pqt) ? $pqt->execCmd() : '';
        $replace['#pqt_id#'] = is_object($pqt) ? $pqt->getId() : '';
        $replace['#pqt_unit#'] = is_object($pqt) ? $pqt->getUnite() : '';
        $replace['#pqt_valueDate#'] = is_object($pqt) ? $pqt->getValueDate() : '';
        $replace['#pqt_collectDate#'] = is_object($pqt) ? $pqt->getCollectDate() : '';
        $replace['#pqt_display#'] = (is_object($pqt) && $pqt->getIsVisible()) ? '' : 'none';

        $network = $this->getCmd('info', 'INetwork');
        $replace['#network#'] = is_object($network) ? $network->execCmd() : '';
        $replace['#network_id#'] = is_object($network) ? $network->getId() : '';
        $replace['#network_display#'] = (is_object($network) && $network->getIsVisible()) ? '' : 'none';

        $WOn = $this->getCmd('action', 'WOn');
        $replace['#on_id#'] = is_object($WOn) ? $WOn->getId() : '';
        $WOff = $this->getCmd('action', 'WOff');
        $replace['#off_id#'] = is_object($WOff) ? $WOff->getId() : '';

        $fanF3L = $this->getCmd('info', 'IFanF3L');
        $replace['#fanF3L#'] = is_object($fanF3L) ? $this->getFanStateF3L($fanF3L->execCmd()) : '';
        $WfanF3L = $this->getCmd('action', 'WFanF3L');
        $replace['#fanF3L_id#'] = is_object($WfanF3L) ? $WfanF3L->getId() : '';

        $fanF4L = $this->getCmd('info', 'IFanF4L');
        $replace['#fanF4L#'] = is_object($fanF4L) ? $this->getFanStateF4L($fanF4L->execCmd()) : '';
        $WfanF4L = $this->getCmd('action', 'WFanF4L');
        $replace['#fanF4L_id#'] = is_object($WfanF4L) ? $WfanF4L->getId() : '';

        $snap = $this->getCmd('info', 'ISnap');
        $replace['#snap_id#'] = is_object($snap) ? $snap->getId() : '';
        $replace['#snap_display#'] = (is_object($snap) && $snap->getIsVisible()) ? '' : 'none';

        $refresh = $this->getCmd('action', 'refresh');
        $replace['#refresh_id#'] = is_object($refresh) ? $refresh->getId() : '';

        // template_replace ne protège pas le contexte JavaScript. Les valeurs
        // du poêle et les paramètres d'affichage sont donc encodés avant leur
        // insertion dans le bloc <script> du widget.
        $javascriptPlaceholders = array(
            'invertFanSpeed',
            'heure',
            'status',
            'temperature', 'temperature_valueDate', 'temperature_collectDate', 'temperature_unite',
            'temperature2', 'temperature2_valueDate', 'temperature2_collectDate', 'temperature2_unite',
            'temperature3', 'temperature3_valueDate', 'temperature3_collectDate', 'temperature3_unite',
            'consigne', 'consigne_valueDate', 'consigne_collectDate', 'consigne_unite',
            'consigne_minValue', 'consigne_maxValue', 'consigne_step',
            'power', 'power_minValue', 'power_maxValue', 'power_step',
            'fan', 'fan_minValue', 'fan_maxValue', 'fan_step',
            'nbAll', 'nbAll_unit', 'nbAll_valueDate', 'nbAll_collectDate',
            'hae', 'hae_unit', 'hae_valueDate', 'hae_collectDate',
            'hc', 'hc_unit', 'hc_valueDate', 'hc_collectDate',
            'hsc', 'hsc_unit', 'hsc_valueDate', 'hsc_collectDate',
            'hde', 'hde_unit', 'hde_valueDate', 'hde_collectDate',
            'haf', 'haf_unit', 'haf_valueDate', 'haf_collectDate',
            'pqt', 'pqt_unit', 'pqt_valueDate', 'pqt_collectDate',
            'network'
        );
        foreach ($javascriptPlaceholders as $placeholder) {
            $sourceKey = '#' . $placeholder . '#';
            $replace['#' . $placeholder . '_js#'] = self::encodeInlineJavaScriptValue(
                isset($replace[$sourceKey]) ? $replace[$sourceKey] : ''
            );
        }

        $html = template_replace($replace, getTemplate('core', $version, __CLASS__, __CLASS__));
        $html = translate::exec($html, 'plugins/' . __CLASS__ . '/core/template/' . $version . '/' . __CLASS__ . '.html');
        return $html;
    }

    /**
     * Actualise quotidiennement les métadonnées statiques des équipements.
     *
     * @return int Nombre d'équipements actualisés.
     */
    public static function cronDaily()
    {
        $refreshed = 0;
        foreach (eqLogic::byType(__CLASS__) as $eqLogic) {
            if (!$eqLogic->getIsEnable()) {
                continue;
            }
            try {
                if ($eqLogic->refreshStaticData(true)) {
                    $refreshed++;
                }
            } catch (Exception $e) {
                log::add(__CLASS__, 'warning', sprintf(
                    __('Actualisation quotidienne impossible pour %s : %s', __FILE__),
                    $eqLogic->getHumanName(),
                    $e->getMessage()
                ));
            }
        }
        return $refreshed;
    }

    /**
     * Interroge la passerelle et actualise toutes les commandes d'information.
     *
     * @return bool Vrai lorsque le cycle dynamique est complet.
     */
    public function getInformations()
    {
        log::add(__CLASS__, 'debug', __('Début', __FILE__) . ' : ' . __FUNCTION__);

        // GET TIME sert aussi de test de disponibilité. En cas d'échec, le
        // cycle est interrompu afin de ne pas cumuler plusieurs timeouts.
        $time = $this->makeRequest('GET+TIME');
        if (!$time) {
            log::add(__CLASS__, 'debug', __FUNCTION__ . __(' - cycle interrompu, équipement injoignable', __FILE__));
            return false;
        }
        if (isset($time->DATA) && is_object($time->DATA)) {
            $this->checkAndUpdateCmd('ITime', json_encode($time->DATA));
        }

        // Ces données sont utilisées par les pages d'informations et de
        // planning : elles sont donc relues à chaque cycle configuré.
        $this->refreshStaticData(false);

        $schedule = $this->makeRequest('GET+CHRD', 6);
        if ($schedule && isset($schedule->DATA) && is_object($schedule->DATA)) {
            $this->checkAndUpdateCmd('IPH', json_encode($schedule->DATA));
        }

        $counters = $this->makeRequest('GET+CNTR', 6);
        if ($counters && isset($counters->DATA) && is_object($counters->DATA)) {
            $this->updateCounters($counters->DATA);
        } else {
            $this->refreshLegacyCounters();
        }

        $all = $this->makeRequest('GET+ALLS', 6);
        if (!$all || !isset($all->DATA) || !is_object($all->DATA)) {
            log::add(__CLASS__, 'debug', __FUNCTION__ . __(' - données dynamiques indisponibles', __FILE__));
            return false;
        }
        $this->updateAllsData($all->DATA, $all);

        log::add(__CLASS__, 'debug', __('Fin', __FILE__) . ' : ' . __FUNCTION__);
        return true;
    }

    /**
     * Applique les données agrégées retournées par GET ALLS.
     *
     * @param object $data Données de la réponse.
     * @param object|null $fullResponse Réponse complète pour l'instantané.
     * @return void
     */
    private function updateAllsData($data, $fullResponse = null)
    {
        $map = array(
            'IPower' => array('PWR', null),
            'IConsigne' => array('SETP', null),
            'IFan' => array('F2L', null),
            'IFanF3L' => array('F3L', null),
            'IFanF4L' => array('F4L', null),
            'ITemp' => array('T1', 2),
            'ITemp2' => array('T2', 2),
            'ITemp3' => array('T3', 2),
            'IStatus' => array('STATUS', null),
            'IQuantite' => array('PQT', null)
        );
        foreach ($map as $logicalId => $definition) {
            $this->updateCommandIfPresent($logicalId, $data, $definition[0], $definition[1]);
        }

        $date = $this->responseValue($data, 'STOVE_DATETIME');
        $weekday = $this->responseValue($data, 'STOVE_WDAY');
        if ($date === null) {
            $date = $this->responseValue($data, 'APLTS');
        }
        if ($weekday === null) {
            $weekday = $this->responseValue($data, 'APLWDAY');
        }
        if ($date !== null || $weekday !== null) {
            $this->checkAndUpdateCmd('ITime', json_encode(array(
                'STOVE_DATETIME' => $date,
                'STOVE_WDAY' => $weekday
            )));
        }
        $this->checkAndUpdateCmd('ISnap', json_encode($fullResponse !== null ? $fullResponse : $data));
    }

    /**
     * Applique les compteurs retournés par GET CNTR.
     *
     * @param object $data Données de compteurs.
     * @return void
     */
    private function updateCounters($data)
    {
        $this->updateCommandIfPresent('INbAllumage', $data, 'IGN');
        $this->updateCommandIfPresent('INbAllumageFailed', $data, 'IGNERRORS');
        $this->updateDurationIfPresent('IHeuresAlimElec', $data, 'POWERTIME');
        $this->updateDurationIfPresent('IHeuresChauffe', $data, 'HEATTIME');
        $this->updateCommandIfPresent('IHeuresSurChauffe', $data, 'OVERTMPERRORS');
        $this->updateDurationIfPresent('IHeuresDepuisEntretien', $data, 'SERVICETIME');
        $this->updateCommandIfPresent('IQuantite', $data, 'PQT');
    }

    /**
     * Convertit et met à jour une durée présente dans une réponse.
     *
     * @param string $logicalId Identifiant logique Jeedom.
     * @param object $data Données de réponse.
     * @param string $property Propriété contenant la durée.
     * @return void
     */
    private function updateDurationIfPresent($logicalId, $data, $property)
    {
        $rawValue = $this->responseValue($data, $property);
        if ($rawValue === null) {
            return;
        }
        $value = self::convertTimeToDec($rawValue);
        if ($value !== null) {
            $this->checkAndUpdateCmd($logicalId, $value);
        }
    }

    /**
     * Relit les compteurs via EXT ADRD lorsque GET CNTR est indisponible.
     *
     * @return void
     */
    private function refreshLegacyCounters()
    {
        $definitions = array(
            array('EXT+ADRD+2066+1', 'ADDR_2066', 'INbAllumage', false),
            array('EXT+ADRD+207C+1', 'ADDR_207C', 'INbAllumageFailed', false),
            array('EXT+ADRD+206A+1', 'ADDR_206A', 'IHeuresAlimElec', true),
            array('EXT+ADRD+2070+1', 'ADDR_2070', 'IHeuresChauffe', true),
            array('EXT+ADRD+207A+1', 'ADDR_207A', 'IHeuresSurChauffe', false),
            array('EXT+ADRD+2076+1', 'ADDR_2076', 'IHeuresDepuisEntretien', true)
        );
        foreach ($definitions as $definition) {
            $response = $this->makeRequest($definition[0], 6);
            if (!$response || !isset($response->DATA) || !is_object($response->DATA)) {
                continue;
            }
            if ($definition[3]) {
                $this->updateDurationIfPresent($definition[2], $response->DATA, $definition[1]);
            } else {
                $this->updateCommandIfPresent($definition[2], $response->DATA, $definition[1]);
            }
        }
    }

    /**
     * Actualise les données réseau et éventuellement les métadonnées Jeedom.
     *
     * @param bool $saveMetadata Enregistre les métadonnées si elles changent.
     * @return bool Vrai lorsque GET STDT a répondu correctement.
     */
    private function refreshStaticData($saveMetadata)
    {
        $response = $this->makeRequest('GET+STDT', 6);
        if (!$response || !isset($response->DATA) || !is_object($response->DATA)) {
            return false;
        }
        $data = $response->DATA;
        $this->updateCommandIfPresent('IName', $data, 'LABEL');
        $this->checkAndUpdateCmd('INetwork', json_encode($data));

        if (!$saveMetadata) {
            return true;
        }
        $changed = false;
        foreach (array('serialNumber' => 'SN', 'model' => 'MOD') as $configuration => $property) {
            $value = $this->responseValue($data, $property);
            if ($value !== null && $value !== ''
                && (string) $this->getConfiguration($configuration, '') !== (string) $value) {
                $this->setConfiguration($configuration, self::sanitizeDiscoveryText($value, 80));
                $changed = true;
            }
        }
        $versions = array();
        foreach (array('SYSTEM', 'plzbridge', 'sendmsg') as $property) {
            $value = $this->responseValue($data, $property);
            if ($value !== null && $value !== '') {
                $versions[] = $property . ': ' . self::sanitizeDiscoveryText($value, 80);
            }
        }
        $versions = implode(' | ', $versions);
        if ($versions !== '' && (string) $this->getConfiguration('versions', '') !== $versions) {
            $this->setConfiguration('versions', $versions);
            $changed = true;
        }
        if ($changed) {
            $this->save();
        }
        return true;
    }

    /**
     * Convertit une durée Palazzetti HH:MM en heures décimales.
     *
     * @param mixed $_time Durée brute.
     * @return float|null Durée convertie.
     */
    public static function convertTimeToDec($_time)
    {
        return self::decimalHours($_time);
    }
}

/**
 * Commande Jeedom associée à un équipement Palazzetti.
 */
class PalazzettiCmd extends cmd
{
    /*     * *************************Attributs******************************
    public static $_widgetPossibility = array('custom' => false);

    /*     * *********************Methode d'instance************************* */

    /**
     * Exécute un rafraîchissement ou transmet une action au poêle.
     *
     * @param mixed $_options Options fournies par Jeedom.
     * @return void
     */
    public function execute($_options = null)
    {
        $eqLogic = $this->getEqLogic();

        log::add('Palazzetti', 'debug', __FUNCTION__ . ' options ' . json_encode($this->getConfiguration('options')));
        log::add('Palazzetti', 'debug', __FUNCTION__ . ' $options ' . json_encode($_options));
        if ($this->getLogicalId('') == 'refresh') {
            $eqLogic->getInformations();
        } else {
            $return = $eqLogic->sendCommand($this, $_options);
            log::add('Palazzetti', 'debug', __FUNCTION__ . __(' resultat ', __FILE__) . $return);
        }
    }
}
