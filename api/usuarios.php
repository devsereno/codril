<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $pdo->query("SELECT id, nome, email, role, autorizado, requerer_troca FROM users ORDER BY id DESC");
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'usuarios' => $usuarios]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao listar utilizadores.']);
    }
    exit;
}

// ====================== POST ACTIONS ======================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $action = $data['action'] ?? '';

    // ==================== ALTERAR SENHA (COM VERIFICAÇÃO DA ANTIGA) ====================
    if ($action === 'CHANGE_PASSWORD') {
        $email       = trim($data['email'] ?? '');
        $senha_atual = $data['senha_atual'] ?? '';
        $nova_senha  = $data['nova_senha'] ?? '';

        if (empty($email) || empty($senha_atual) || empty($nova_senha)) {
            echo json_encode(['success' => false, 'message' => 'Todos os campos são obrigatórios.']);
            exit;
        }

        try {
            $stmt = $pdo->prepare("SELECT id, senha_hash FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                echo json_encode(['success' => false, 'message' => 'Usuário não encontrado.']);
                exit;
            }

            if (!password_verify($senha_atual, $user['senha_hash'])) {
                echo json_encode(['success' => false, 'message' => 'Senha atual incorreta.']);
                exit;
            }

            $nova_hash = password_hash($nova_senha, PASSWORD_DEFAULT);

            $update = $pdo->prepare("UPDATE users SET senha_hash = ?, requerer_troca = FALSE WHERE id = ?");
            $update->execute([$nova_hash, $user['id']]);

            echo json_encode(['success' => true, 'message' => 'Senha alterada com sucesso!']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erro ao atualizar senha.']);
        }
        exit;
    }

    // ==================== OUTRAS AÇÕES (mantidas) ====================
    if ($action === 'DELETE' && isset($data['id'])) {
        // ... seu código original de DELETE ...
    }

    if ($action === 'UPDATE_ROLE' && isset($data['id']) && isset($data['role'])) {
        // ... seu código original ...
    }

    if ($action === 'RESET_PASSWORD' && isset($data['id'])) {
        // ... seu código original ...
    }

    // ... resto do seu código (criar usuário, etc.) ...

    echo json_encode(['success' => false, 'message' => 'Ação desconhecida.']);
    exit;
}
?>
