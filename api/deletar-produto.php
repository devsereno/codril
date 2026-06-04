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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $id = intval($data['id'] ?? 0);

    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID inválido para deleção']);
        exit;
    }

    try {
        // Remove da tabela 'enderecos'
        $stmt = $pdo->prepare("DELETE FROM enderecos WHERE id = ?");
        $success = $stmt->execute([$id]);

        if ($success && $stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'Produto deletado com sucesso']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Registro não localizado ou já excluído']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro interno de banco ao excluir produto']);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Método não suportado']);
?>
