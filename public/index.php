<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use SmbWebClient\Application;
use SmbWebClient\Config;

require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Create configuration from environment
$config = Config::fromEnv();

// Create and run application
$app = new Application($config);
$app->run();
