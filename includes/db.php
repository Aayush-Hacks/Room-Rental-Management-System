<?php
/**
 * includes/db.php
 * -----------------------------------------------------------------
 * Single shared PDO connection for the whole app.
 * Every other PHP file should do: require_once __DIR__ . '/db.php';
 * and then use the $pdo variable with prepared statements.
 * -----------------------------------------------------------------
 */

// Base URL — change this if the app is deployed at a different path
if (!defined('BASE_URL')) {
    define('BASE_URL', '/RRMS');
}

$DB_HOST = 'localhost';
$DB_NAME = 'room_rental_system';
$DB_USER = 'root';       // XAMPP default — change if you set a MySQL password
$DB_PASS = '';           // XAMPP default is an empty password

$dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // throw on SQL errors instead of silent failure
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,        // return rows as associative arrays
    PDO::ATTR_EMULATE_PREPARES => false,                   // use real prepared statements (safer against SQL injection)
];

try {
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
} catch (PDOException $e) {
    // Log the error and show a generic message (never expose DB credentials)
    error_log('Database connection failed: ' . $e->getMessage());
    die('Database connection failed. Please try again later or contact the site administrator.');
}


try {
    $existingColumns = $pdo->query('SHOW COLUMNS FROM `users`')->fetchAll(PDO::FETCH_COLUMN);
    $missingColumns = array_diff(
        ['citizenship_front', 'citizenship_back', 'citizenship_status'],
        $existingColumns
    );
    if (!empty($missingColumns)) {
        $addStatements = [];
        foreach ($missingColumns as $column) {
            $definition = ($column === 'citizenship_status')
                ? "`citizenship_status` ENUM('pending','approved','rejected') DEFAULT NULL"
                : "`{$column}` VARCHAR(255) DEFAULT NULL";
            $addStatements[] = 'ADD COLUMN ' . $definition;
        }
        $pdo->exec('ALTER TABLE `users` ' . implode(', ', $addStatements));
        error_log('Auto-migration: added missing citizenship columns to users table.');
    }
} catch (PDOException $e) {
    // Never crash the app over the migration — log and continue.
    error_log('Auto-migration of citizenship columns failed: ' . $e->getMessage());
}
