<?php
// Permite conexões cruzadas seguras (CORS)
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
        echo json_encode(['success' => false, 'message' => 'Por favor, preencha todos os campos.']);
        exit;
    }

    try {
        // Localiza o utilizador na tabela 'users'
        $stmt = $pdo->prepare("SELECT id, nome, email, senha_hash, role, autorizado, requerer_troca FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Verifica a senha encriptada de forma segura
            if (password_verify($senha, $user['senha_hash']) || $senha === $user['senha_hash']) {
                
                if (!$user['autorizado']) {
                    echo json_encode(['success' => false, 'message' => 'Este utilizador ainda não está autorizado a aceder.']);
                    exit;
                }

                // Desativa tokens antigos desse usuário antes de criar um novo
                $stmtDesativar = $pdo->prepare("UPDATE user_sessions SET active = FALSE WHERE user_id = ?");
                $stmtDesativar->execute([$user['id']]);

                // GERA TOKEN ÚNICO, GRANDE E ALEATÓRIO (UUID)
                $token = bin2hex(random_bytes(32));

                // Registra o token ativo no banco de dados na tabela 'user_sessions'
                $stmtSession = $pdo->prepare("INSERT INTO user_sessions (user_id, token, ip_address) VALUES (?, ?, ?)");
                $stmtSession->execute([$user['id'], $token, $_SERVER['REMOTE_ADDR']]);

                // Inicia sessão PHP no servidor para redundância de segurança
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                $_SESSION['user_token'] = $token;
                $_SESSION['user_id'] = $user['id'];

                echo json_encode([
                    'success' => true,
                    'message' => 'Login efetuado com sucesso!',
                    'token' => $token,
                    'user' => [
                        'nome' => $user['nome'],
                        'email' => $user['email'],
                        'role' => $user['role'],
                        'requerer_troca' => (bool)$user['requerer_troca']
                    ]
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Senha incorreta.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Utilizador não cadastrado no sistema.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro interno de servidor: ' . $e->getMessage()]);
    }
    exit;
}

http_response_code(405);
echo json_encode(['success' => false, 'message' => 'Método de requisição não suportado']);
?>
