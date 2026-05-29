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

// ====================== LISTAR USUÁRIOS (GET) ======================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Seleciona usando a nomenclatura exata da sua tabela 'users'
        $stmt = $pdo->query("SELECT id, nome, email, role, autorizado FROM users ORDER BY id DESC");
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'usuarios' => $usuarios
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao buscar usuários no banco de dados']);
    }
    exit;
}

// ====================== SALVAR / EXCLUIR USUÁRIO (POST) ======================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
        exit;
    }

    // Ação especial para Deletar Usuário via POST (evita problemas de CORS com o método DELETE)
    if (isset($data['action']) && $data['action'] === 'DELETE' && isset($data['id'])) {
        try {
            $id = intval($data['id']);
            
            // Impede a deleção do Ricardo Master por segurança extra
            $check = $pdo->prepare("SELECT email FROM users WHERE id = ?");
            $check->execute([$id]);
            $userToDelete = $check->fetch(PDO::FETCH_ASSOC);
            
            if ($userToDelete && strtolower($userToDelete['email']) === 'ricardomaster@gmail.com') {
                echo json_encode(['success' => false, 'message' => 'Não é permitido excluir o administrador Master principal']);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $success = $stmt->execute([$id]);

            if ($success && $stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => 'Usuário deletado com sucesso']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Usuário não encontrado ou já deletado']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erro interno ao processar exclusão']);
        }
        exit;
    }

    // Fluxo de Cadastro de Novo Usuário
    $nome = trim($data['nome'] ?? '');
    $email = trim($data['email'] ?? '');
    $senha = trim($data['senha'] ?? '');
    $role = trim($data['role'] ?? 'user'); // Usa 'role' igual ao setup-db

    if (empty($nome) || empty($email) || empty($senha)) {
        echo json_encode(['success' => false, 'message' => 'Preencha todos os campos obrigatórios']);
        exit;
    }

    try {
        // Verifica se o e-mail já existe
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => false, 'message' => 'Este e-mail já está cadastrado']);
            exit;
        }

        // Aplica password_hash() seguro conforme definido no seu setup-db
        $senha_hash = password_hash($senha, PASSWORD_DEFAULT);

        // Insere usando as colunas exatas do seu banco
        $stmt = $pdo->prepare("INSERT INTO users (email, nome, senha_hash, role, autorizado) VALUES (?, ?, ?, ?, TRUE)");
        $success = $stmt->execute([$email, $nome, $senha_hash, $role]);

        if ($success) {
            echo json_encode(['success' => true, 'message' => 'Usuário cadastrado com sucesso!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Erro ao salvar o usuário']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro no servidor: ' . $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Método não suportado']);
?>
