<?php
/**
 * Database connection (PDO + MySQL)
 * -----------------------------------
 * Every query in this app MUST go through PDO prepared statements.
 * Never concatenate user input into SQL strings - see includes/functions.php
 * for the query helpers that enforce this.
 */

declare(strict_types=1);

function app_config(): array
{
    static $config = null;

    if ($config === null) {
        $configFile = __DIR__ . '/config.php';

        if (!file_exists($configFile)) {
            http_response_code(500);
            die('Missing config/config.php. Copy config.example.php to config.php and fill in your details.');
        }

        $config = require $configFile;
    }

    return $config;
}

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $db = app_config()['db'];

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $db['host'],
        $db['port'],
        $db['database']
    );

    try {
        $pdo = new PDO($dsn, $db['username'], $db['password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // use REAL prepared statements
        ]);
    } catch (PDOException $e) {
        error_log('DB connection failed: ' . $e->getMessage());
        http_response_code(500);
        // Never echo $e->getMessage() to the browser - it can leak host/db/user details
        die('A server error occurred. Please try again later.');
    }

    return $pdo;
}
