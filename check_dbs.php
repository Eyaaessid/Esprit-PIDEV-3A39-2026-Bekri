<?php
try {
    $pdo = new PDO('mysql:host=127.0.0.1', 'root', '');
    $result = $pdo->query("SHOW DATABASES");
    echo " All databases on your server:\n";
    while ($row = $result->fetch(PDO::FETCH_NUM)) {
        echo "  - " . $row[0] . "\n";
    }
} catch (PDOException $e) {
    echo " Error: " . $e->getMessage() . "\n";
}
