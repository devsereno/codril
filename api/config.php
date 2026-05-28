<?php
// api/config.php

define('DB_HOST', 'dpg-d8brkk9o3t8c73aokdr0-a.virginia-postgres.render.com');
define('DB_PORT', '5432');
define('DB_NAME', 'pulmao_db');
define('DB_USER', 'pulmao_user');
define('DB_PASS', 'OjXMXgHy2SDFDdwpstxEbixPvuqskgCd');

try {
    $pdo = new PDO(
        "pgsql:host=".DB_HOST.";port=".DB_PORT.";dbname=".DB_NAME,
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch(PDOException $e) {
    die("Erro de conexão: " . $e->getMessage());
}
?>
