<?php
require_once 'config.php';

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS
    );

    echo "Database Connected Successfully!";
} catch (PDOException $e) {
    echo "Connection Failed: " . $e->getMessage();
}