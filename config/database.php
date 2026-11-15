<?php
/**
 * Database Connection Configuration
 * Technologies: PHP PDO & MySQL
 */

// Load custom configuration if present, otherwise fallback to defaults
$db_host = getenv('DB_HOST') ?: '127.0.0.1';
$db_port = getenv('DB_PORT') ?: '3306';
$db_name = getenv('DB_NAME') ?: 'billing_inventory_db';
$db_user = getenv('DB_USER') ?: 'root';
$db_pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';

$dsn = "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (PDOException $e) {
    // Graceful error display with setup guidance
    $error_msg = htmlspecialchars($e->getMessage());
    die("
    <!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Database Connection Error</title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #0f172a; color: #f8fafc; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; }
            .card { background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 32px; max-width: 600px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); }
            h1 { color: #f43f5e; margin-top: 0; font-size: 22px; }
            p { color: #94a3b8; line-height: 1.6; }
            code { background: #0f172a; color: #38bdf8; padding: 3px 6px; border-radius: 4px; font-family: monospace; }
            .steps { background: #0f172a; border-left: 3px solid #38bdf8; padding: 12px 16px; margin: 16px 0; border-radius: 0 8px 8px 0; }
            .error-box { background: rgba(244,63,94,0.1); border: 1px solid rgba(244,63,94,0.3); color: #fda4af; padding: 12px; border-radius: 6px; font-size: 13px; font-family: monospace; word-break: break-all; margin: 12px 0; }
        </style>
    </head>
    <body>
        <div class='card'>
            <h1>Database Connection Failed</h1>
            <p>Could not connect to the MySQL database. Please verify your connection credentials in <code>config/database.php</code>.</p>
            <div class='error-box'>{$error_msg}</div>
            <div class='steps'>
                <strong>Quick Setup Guide:</strong>
                <ol style='margin: 8px 0 0 18px; padding: 0; color: #cbd5e1;'>
                    <li>Ensure MySQL is running (e.g., via XAMPP, WAMP, or local service).</li>
                    <li>Create database: <code>CREATE DATABASE billing_inventory_db;</code></li>
                    <li>Import the schema file: <code>database/schema.sql</code></li>
                </ol>
            </div>
        </div>
    </body>
    </html>
    ");
}
