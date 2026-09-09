#!/usr/bin/env php
<?php

/**
 * Standalone entry point — no Laravel app required.
 *
 *   composer install
 *   cp .env.example .env   (then fill in OLD_DB_* and NEW_DB_*)
 *   php migrate.php --fresh
 *   php migrate.php --only=geography --only=identity
 *   php migrate.php --chunk=1000
 */

require __DIR__ . '/vendor/autoload.php';

use Egoola\Migration\Migrator;
use Illuminate\Database\Capsule\Manager as Capsule;

if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

function env_get($key, $default = null)
{
    $value = getenv($key);
    return $value === false ? $default : $value;
}

$capsule = new Capsule;

$capsule->addConnection([
    'driver' => 'mysql',
    'host' => env_get('OLD_DB_HOST', '127.0.0.1'),
    'port' => env_get('OLD_DB_PORT', '3306'),
    'database' => env_get('OLD_DB_DATABASE', 'egoola_old'),
    'username' => env_get('OLD_DB_USERNAME', 'root'),
    'password' => env_get('OLD_DB_PASSWORD', ''),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
], 'old');

$capsule->addConnection([
    'driver' => 'mysql',
    'host' => env_get('NEW_DB_HOST', '127.0.0.1'),
    'port' => env_get('NEW_DB_PORT', '3306'),
    'database' => env_get('NEW_DB_DATABASE', 'egoola_new'),
    'username' => env_get('NEW_DB_USERNAME', 'root'),
    'password' => env_get('NEW_DB_PASSWORD', ''),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => '',
], 'new');

$capsule->setAsGlobal();
$capsule->bootEloquent();

// --- tiny argv parser: supports --fresh, --only=x (repeatable), --chunk=n ---
$options = ['fresh' => false, 'only' => [], 'chunk' => 500];
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--fresh') {
        $options['fresh'] = true;
    } elseif (strpos($arg, '--only=') === 0) {
        $options['only'][] = substr($arg, 7);
    } elseif (strpos($arg, '--chunk=') === 0) {
        $options['chunk'] = (int) substr($arg, 8);
    } else {
        fwrite(STDERR, "Unknown argument: {$arg}\n");
        exit(1);
    }
}

$schemaPath = __DIR__ . '/database/new_schema/new_schema.sql';

$migrator = new Migrator($schemaPath);
$migrator->run($options);
