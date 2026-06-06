<?php
session_start();

define('DB_HOST', 'sql311.infinityfree.com');
define('DB_USER', 'ifo_42015736');
define('DB_PASS', 'QCYLp1afjNkCfP');
define('DB_NAME', 'ifo_42015736_pulmao_db');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Erro de conexão: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");
?>
