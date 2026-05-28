<?php
// api/config.php - PostgreSQL no Render

define('DB_HOST', 'SEU_HOST_AQUI');           // ← Vou te passar depois
define('DB_PORT', '5432');
define('DB_NAME', 'pulmao_db');
define('DB_USER', 'pulmao_user');
define('DB_PASS', 'SUA_SENHA_AQUI');          // ← Vou te passar depois

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
