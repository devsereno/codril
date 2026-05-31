<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include 'config.php';

$data = json_decode(file_get_contents('php://input'), true);
$id = intval($data['id'] ?? 0);
$token = $data['token'] ?? '';

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'ID do item inválido.']);
    exit;
}

if (empty($token)) {
    echo json_encode(['success' => false, 'message' => 'Autenticação em falta. Forneça o token de acesso.']);
    exit;
}

try {
    // Valida se a sessão pertence a um administrador ('master' ou 'super')
    $stmtSessao = $pdo->prepare("
        SELECT u.role 
        FROM user_sessions s 
        JOIN users u ON s.user_id = u.id 
        WHERE s.token = ? AND s.active = TRUE
    ");
    $stmtSessao->execute([$token]);
    $user = $stmtSessao->fetch(PDO::FETCH_ASSOC);

    if (!$user || !in_array($user['role'], ['master', 'super'])) {
        echo json_encode(['success' => false, 'message' => 'Acesso negado. Apenas administradores Master ou Super podem deletar estoque.']);
        exit;
    }

    // Deleta o registro fisicamente
    $stmt = $pdo->prepare("DELETE FROM enderecos WHERE id = ?");
    $stmt->execute([$id]);

    echo json_encode([
        'success' => true,
        'message' => 'Registro de estoque removido com sucesso!'
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Erro interno ao processar exclusão de registro.']);
}
exit;
?>
