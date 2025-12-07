<?php

require '/app/vendor/autoload.php';

use SmbWebClient\Config;
use SmbWebClient\SmbClient;

// Load .env
$dotenv = \Dotenv\Dotenv::createImmutable('/app');
$dotenv->load();

// Create config
$config = Config::fromEnv();

// Create client with anonymous mode
$client = new SmbClient($config, null, null);

// Test listing shares
echo "Testing anonymous access to shares on 'samba1':\n";

try {
    $shares = $client->listShares('samba1');
    echo "✅ Successfully listed shares:\n";
    foreach ($shares as $share) {
        echo "  - " . $share->getName() . "\n";
    }
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

// Test getting specific directory
echo "\nTesting access to SHARE1 on samba1:\n";
try {
    $share = $client->getShare('samba1', 'SHARE1');
    $files = $share->listDirectory('/');
    echo "✅ Successfully listed SHARE1 contents:\n";
    foreach ($files as $file) {
        echo "  - " . $file->getName() . " (" . ($file->isDirectory() ? "DIR" : "FILE") . ")\n";
    }
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Code: " . $e->getCode() . "\n";
}
