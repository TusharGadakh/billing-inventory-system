<?php
/**
 * Example Database Configuration Template
 * Copy this file to database.php and update with your local/production credentials.
 */

$db_host = '127.0.0.1';
$db_port = '3306';
$db_name = 'billing_inventory_db';
$db_user = 'root';
$db_pass = '';

$dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (PDOException $e) {
    die("Database Connection Failed: " . htmlspecialchars($e->getMessage()));
}
