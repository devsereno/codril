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

    $email = trim($data['email'] ?? '');
    $senha = trim($data['senha'] ?? '');

    if (empty($email) || empty($senha)) {
        echo json_encode(['success' => false, 'message' => 'Preencha todos os campos']);
        exit;
    }

    try {
        // Busca o usuário na tabela 'users'
        $stmt = $pdo->prepare("SELECT id, nome, email, senha_hash, role, autorizado FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Verifica a senha em hash (usado para senhas encriptadas com password_hash)
            // Se for login provisório sem hash, tenta comparação direta para compatibilidade
            if (password_verify($senha, $user['senha_hash']) || $senha === $user['senha_hash']) {
                
                if (!$user['autorizado']) {
                    echo json_encode(['success' => false, 'message' => 'Usuário ainda não está autorizado pelo administrador']);
                    exit;
                }

                echo json_encode([
                    'success' => true,
                    'user' => [
                        'nome' => $user['nome'],
                        'email' => $user['email'],
                        'role' => $user['role'] // 'master', 'super', 'user'
                    ]
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Senha incorreta']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'E-mail não cadastrado']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro interno ao realizar login: ' . $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Método não suportado']);
?>
