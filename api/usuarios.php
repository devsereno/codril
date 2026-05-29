<?php
// Permite acessos e requisições externas (CORS)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json; charset=UTF-8");

// Responde imediatamente a requisições de pré-conexão (OPTIONS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include 'config.php';

// ====================== LISTAR UTILIZADORES (GET) ======================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $pdo->query("SELECT id, nome, email, role, autorizado, requerer_troca FROM users ORDER BY id DESC");
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'usuarios' => $usuarios]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao listar utilizadores no servidor.']);
    }
    exit;
}

// ====================== PROCESSAR AÇÕES (POST) ======================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        echo json_encode(['success' => false, 'message' => 'Dados de requisição inválidos.']);
        exit;
    }

    $action = $data['action'] ?? '';

    // AÇÃO 1: EXCLUIR UTILIZADOR
    if ($action === 'DELETE' && isset($data['id'])) {
        try {
            $id = intval($data['id']);
            
            // Impede exclusão do administrador mestre
            $check = $pdo->prepare("SELECT email FROM users WHERE id = ?");
            $check->execute([$id]);
            $user = $check->fetch(PDO::FETCH_ASSOC);
            
            if ($user && strtolower($user['email']) === 'ricardomaster@gmail.com') {
                echo json_encode(['success' => false, 'message' => 'Não é permitido excluir o administrador mestre principal por motivos de segurança.']);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Utilizador removido com sucesso.']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erro ao remover o utilizador: ' . $e->getMessage()]);
        }
        exit;
    }

    // AÇÃO 2: ATUALIZAR NÍVEL DE ACESSO (ROLE)
    if ($action === 'UPDATE_ROLE' && isset($data['id']) && isset($data['role'])) {
        try {
            $id = intval($data['id']);
            $role = trim($data['role']);

            // Valida se o nível é aceitável
            if (!in_array($role, ['user', 'super', 'master'])) {
                echo json_encode(['success' => false, 'message' => 'Nível de acesso inválido.']);
                exit;
            }

            // Impede alterar nível do administrador mestre
            $check = $pdo->prepare("SELECT email FROM users WHERE id = ?");
            $check->execute([$id]);
            $user = $check->fetch(PDO::FETCH_ASSOC);
            
            if ($user && strtolower($user['email']) === 'ricardomaster@gmail.com') {
                echo json_encode(['success' => false, 'message' => 'O nível de acesso do Administrador Mestre não pode ser alterado.']);
                exit;
            }

            $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->execute([$role, $id]);
            echo json_encode(['success' => true, 'message' => 'Nível de acesso atualizado com sucesso!']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erro ao alterar o nível de acesso.']);
        }
        exit;
    }

    // AÇÃO 3: RESETAR SENHA (OBRIGAR TROCA NO PRÓXIMO ACESSO)
    if ($action === 'RESET_PASSWORD' && isset($data['id'])) {
        try {
            $id = intval($data['id']);
            $senhaPadrao = '123456'; // Senha padrão temporária
            $senha_hash = password_hash($senhaPadrao, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("UPDATE users SET senha_hash = ?, requerer_troca = TRUE WHERE id = ?");
            $stmt->execute([$senha_hash, $id]);

            echo json_encode([
                'success' => true, 
                'message' => "Senha redefinida para a padrão temporária: $senhaPadrao. O utilizador será obrigado a trocá-la no primeiro acesso."
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erro ao redefinir a senha do utilizador.']);
        }
        exit;
    }

    // AÇÃO 4: ALTERAR SENHA MANDATÓRIA (PRIMEIRO ACESSO)
    if ($action === 'CHANGE_PASSWORD') {
        $email = trim($data['email'] ?? '');
        $novaSenha = trim($data['nova_senha'] ?? '');

        if (empty($email) || empty($novaSenha)) {
            echo json_encode(['success' => false, 'message' => 'Dados incompletos para redefinição de senha.']);
            exit;
        }

        try {
            $senha_hash = password_hash($novaSenha, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET senha_hash = ?, requerer_troca = FALSE WHERE email = ?");
            $stmt->execute([$senha_hash, $email]);

            echo json_encode(['success' => true, 'message' => 'Sua senha definitiva foi salva com sucesso! Agora você pode entrar.']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erro ao salvar nova senha no servidor.']);
        }
        exit;
    }

    // AÇÃO 5: CRIAR NOVO UTILIZADOR (FLUXO PADRÃO)
    $nome = trim($data['nome'] ?? '');
    $email = trim($data['email'] ?? '');
    $senha = trim($data['senha'] ?? '');
    $role = trim($data['role'] ?? 'user');

    if (empty($nome) || empty($email) || empty($senha)) {
        echo json_encode(['success' => false, 'message' => 'Por favor, preencha todos os campos obrigatórios.']);
        exit;
    }

    try {
        // Verifica se já existe o e-mail
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => false, 'message' => 'Este endereço de e-mail já está cadastrado.']);
            exit;
        }

        $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
        
        // Cadastra o utilizador já pré-autorizado
        $stmt = $pdo->prepare("INSERT INTO users (email, nome, senha_hash, role, autorizado, requerer_troca) VALUES (?, ?, ?, ?, TRUE, FALSE)");
        $stmt->execute([$email, $nome, $senha_hash, $role]);

        echo json_encode(['success' => true, 'message' => 'Novo utilizador cadastrado com sucesso!']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao cadastrar utilizador: ' . $e->getMessage()]);
    }
    exit;
}
?>
