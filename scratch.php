<?php
require 'includes/db.php';
$stmt = $pdo->query("SHOW TABLES");
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
print_r($tables);
foreach ($tables as $table) {
    echo "\n$table cols:\n";
    $stmt = $pdo->query("DESCRIBE $table");
    print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
}
