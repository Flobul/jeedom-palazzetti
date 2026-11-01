<?php
function __($text, $file = null) { return $text; }
class config {
    public static $values = array();
    public static function byKey($key, $plugin = '', $default = '') { return array_key_exists($key, self::$values) ? self::$values[$key] : $default; }
}
class eqLogic {
    public static $items = array();
    public static function byType($type) { return self::$items; }
}
class FakeEquipment {
    private $enabled;
    public function __construct($enabled) { $this->enabled = $enabled; }
    public function getIsEnable() { return $this->enabled; }
    public function getCmd() { return array(new stdClass(), new stdClass()); }
    public function getStatus($key, $default = '') { return $key === 'lastCommunication' ? 'available' : $default; }
    public function getConfiguration($key, $default = '') { return $default; }
    public function getCommunicationHealth() { return array('lastSuccess' => true, 'offline' => false, 'stoveOffline' => false); }
}
function extractCommunityMethod($source) {
    $start = strpos($source, 'public static function getConfigForCommunity');
    if ($start === false) throw new RuntimeException('Method missing');
    $brace = strpos($source, '{', $start);
    $depth = 0; $length = strlen($source);
    for ($i = $brace; $i < $length; $i++) {
        if ($source[$i] === '{') $depth++;
        if ($source[$i] === '}') { $depth--; if ($depth === 0) return substr($source, $start, $i - $start + 1); }
    }
    throw new RuntimeException('Unclosed method');
}
$root = dirname(__DIR__, 2);
$source = file_get_contents($root . '/core/class/Palazzetti.class.php');
$method = extractCommunityMethod($source);
foreach (array('config::all(', 'json_encode(', 'getName()', 'network::', 'curl_', 'file_get_contents(', 'sendRequest(', 'requestGet(', 'log::') as $forbidden) {
    if (strpos($method, $forbidden) !== false) throw new RuntimeException('Forbidden diagnostic source: ' . $forbidden);
}
eval('class CommunityDiagnosticHarness extends eqLogic {' . $method . '}');
config::$values = array(
    'password' => 'TOP_SECRET_PASSWORD', 'token' => 'TOP_SECRET_TOKEN', 'access_token' => 'TOP_SECRET_TOKEN',
    'ip' => '192.168.1.10', 'mqtt_host' => 'private.broker.local', 'recordDir' => '/Users/private/media',
    'email' => 'private@example.com', 'username' => 'private-user', 'discovery_networks' => '192.168.0.0/16'
);
foreach (array(array(), array(new FakeEquipment(1), new FakeEquipment(0))) as $items) {
    eqLogic::$items = $items;
    $output = CommunityDiagnosticHarness::getConfigForCommunity("\n");
    if (!is_string($output) || strlen($output) === 0 || strlen($output) > 4096) throw new RuntimeException('Invalid diagnostic size');
    if (substr_count($output, 'JEEDOM_PLUGIN_DIAGNOSTICS_START') !== 1 || substr_count($output, 'JEEDOM_PLUGIN_DIAGNOSTICS_END') !== 1) throw new RuntimeException('Optional diagnostic markers missing');
    foreach (array('TOP_SECRET', '192.168.', 'private.broker', '/Users/', 'private@example.com', 'private-user') as $secret) {
        if (strpos($output, $secret) !== false) throw new RuntimeException('Sensitive value leaked: ' . $secret);
    }
    if (strpos($output, 'Équipements configurés') === false && strpos($output, 'Poêles configurés') === false) throw new RuntimeException('Equipment count missing');
}
echo "Community diagnostic tests: OK (Palazzetti)\n";
