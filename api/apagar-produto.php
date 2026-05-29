<?php
// Habilita conexões remotas de qualquer origem (CORS)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

// Responde imediatamente a requisições de pré-voo (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

include 'config.php';
header("Content-Type: application/json; charset=UTF-8");

// Obtém o payload em formato JSON
$data = json_decode(file_get_contents('php://input'), true);
$id = $data['id'] ?? null;

if (!$id) {
    echo json_encode([
        'success' => false, 
        'message' => 'Parâmetro ID do registro não foi enviado!'
    ]);
    exit;
}

try {
    // Exclui o item correspondente do banco de dados com segurança
    $stmt = $pdo->prepare("DELETE FROM enderecos WHERE id = ?");
    $stmt->execute([$id]);

    echo json_encode([
        'success' => true, 
        'message' => 'Posição apagada com sucesso do Pulmão AE.'
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Erro interno ao tentar apagar item: ' . $e->getMessage()
    ]);
}

