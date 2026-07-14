<?php
/**
 * PDO connection, shared across a single request.
 */

require_once __DIR__ . '/config.php';

// function get_db(): PDO
// {
//     static $pdo = null;

//     if ($pdo === null) {
//         $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4';
//         $pdo = new PDO($dsn, DB_USER, DB_PASS, [
//             PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
//             PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
//         ]);
//     }

//     return $pdo;
// }

function get_db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        // Hardcoding MAMP values directly to bypass config.php entirely
        $dsn = 'mysql:host=127.0.0.1;port=8889;dbname=petcom;charset=utf8mb4';
        $pdo = new PDO($dsn, 'root', 'root', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }

    return $pdo;
}
