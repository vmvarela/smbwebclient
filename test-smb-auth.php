<?php

require '/app/vendor/autoload.php';

use Icewind\SMB\ServerFactory;
use Icewind\SMB\BasicAuth;

// Test with explicit credentials
$factory = new ServerFactory();

// Test 1: Fully authenticated
echo "Test 1: Full authentication (user/pass)\n";
try {
    $auth = new BasicAuth('user', '', 'pass');
    $server = $factory->createServer('samba1', $auth);
    $shares = $server->listShares();
    echo "✅ Shares listed:\n";
    foreach ($shares as $share) {
        echo "  - " . $share->getName() . "\n";
    }
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\nTest 2: Anonymous access (empty credentials)\n";
try {
    $auth = new BasicAuth('', '', '');
    $server = $factory->createServer('samba1', $auth);
    $shares = $server->listShares();
    echo "✅ Shares listed:\n";
    foreach ($shares as $share) {
        echo "  - " . $share->getName() . "\n";
    }
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\nTest 3: Guest authentication\n";
try {
    $auth = new BasicAuth('guest', '', '');
    $server = $factory->createServer('samba1', $auth);
    $shares = $server->listShares();
    echo "✅ Shares listed:\n";
    foreach ($shares as $share) {
        echo "  - " . $share->getName() . "\n";
    }
} catch (\Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
