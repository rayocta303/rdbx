<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/src/Parsers/PdbParser.php';
require_once __DIR__ . '/src/Parsers/KeyParser.php';
require_once __DIR__ . '/src/Utils/Logger.php';

use RekordboxReader\Parsers\PdbParser;
use RekordboxReader\Parsers\KeyParser;
use RekordboxReader\Utils\Logger;

$pdbPath = __DIR__ . '/Rekordbox-USB/PIONEER/rekordbox/export.pdb';
$logger = new Logger(__DIR__ . '/output', false);

$pdbParser = new PdbParser($pdbPath, $logger);
$pdbParser->parse();

$keyParser = new KeyParser($pdbParser, $logger);
$keys = $keyParser->parseKeys();

echo "=== KEYS TABLE ===\n\n";
foreach ($keys as $keyId => $keyName) {
    echo "Key ID $keyId => '$keyName'\n";
}

echo "\n=== TEST KEY LOOKUP ===\n";
echo "getKeyName(1): " . $keyParser->getKeyName(1) . "\n";
echo "getKeyName(2): " . $keyParser->getKeyName(2) . "\n";
