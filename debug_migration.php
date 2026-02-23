<?php
require 'vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->loadEnv(__DIR__.'/.env');

$dsn = $_ENV['DATABASE_URL'] ?? $_SERVER['DATABASE_URL'] ?? null;
echo "DSN: $dsn\n\n";

// Parse the DSN and connect directly
$pdo = new PDO(
    'mysql:host=127.0.0.1;dbname=bekri_db',
    'root',
    '',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// Show tables
$stmt = $pdo->query("SHOW TABLES");
echo "Tables:\n";
while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
    echo "  - {$row[0]}\n";
}

// Check doctrine_migration_versions
echo "\n--- doctrine_migration_versions ---\n";
try {
    $stmt = $pdo->query("DESCRIBE doctrine_migration_versions");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  {$row['Field']}: {$row['Type']} (Null: {$row['Null']}, Key: {$row['Key']})\n";
    }
} catch (Exception $e) {
    echo "  Table does not exist: {$e->getMessage()}\n";
}
