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

// ====================== MÉTODO GET: LISTAR UTILIZADORES OU CONVITES ATIVOS ======================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = $_GET['token'] ?? '';
    
    // Valida o Token de Sessão administrativo
    try {
        $stmtSessao = $pdo->prepare("
            SELECT u.role 
            FROM user_sessions s 
            JOIN users u ON s.user_id = u.id 
            WHERE s.token = ? AND s.active = TRUE
        ");
        $stmtSessao->execute([$token]);
        $sessao = $stmtSessao->fetch(PDO::FETCH_ASSOC);

        if (!$sessao || !in_array($sessao['role'], ['master', 'super'])) {
            echo json_encode(['success' => false, 'message' => 'Sessão inválida ou sem permissão.']);
            exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro de segurança de sessão.']);
        exit;
    }

    // Se pedir convites ativos (?convites=true)
    if (isset($_GET['convites'])) {
        try {
            $stmtConvites = $pdo->query("SELECT id, codigo, role, usado, criado_em FROM user_invites WHERE usado = FALSE ORDER BY id DESC");
            $convites = $stmtConvites->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'convites' => $convites]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erro ao listar convites.']);
        }
        exit;
    }

    // Caso contrário, lista utilizadores padrão
    try {
        $stmt = $pdo->query("SELECT id, nome, email, role, autorizado, requerer_troca FROM users ORDER BY id DESC");
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'usuarios' => $usuarios]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao listar utilizadores.']);
    }
    exit;
}

// ====================== MÉTODO POST: PROCESSAR REQUISIÇÕES ======================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        echo json_encode(['success' => false, 'message' => 'Dados de requisição inválidos.']);
        exit;
    }

    $action = $data['action'] ?? '';

    // =========================================================================
    // FLUXO PÚBLICO: NOVO REGISTRO UTILIZANDO CONVITE DE AUTORIZAÇÃO
    // =========================================================================
    if ($action === 'REGISTER_WITH_INVITE') {
        $codigo = trim($data['codigo'] ?? '');
        $nome = trim($data['nome'] ?? '');
        $email = trim($data['email'] ?? '');
        $senha = trim($data['senha'] ?? '');

        if (empty($codigo) || empty($nome) || empty($email) || empty($senha)) {
            echo json_encode(['success' => false, 'message' => 'Por favor, preencha todos os campos do registo.']);
            exit;
        }

        try {
            // Verifica se o convite existe, é válido e ainda não foi usado
            $stmtConvite = $pdo->prepare("SELECT id, role, usado FROM user_invites WHERE codigo = ? AND usado = FALSE");
            $stmtConvite->execute([$codigo]);
            $convite = $stmtConvite->fetch(PDO::FETCH_ASSOC);

            if (!$convite) {
                echo json_encode(['success' => false, 'message' => 'Código de autorização inválido, inexistente ou já utilizado.']);
                exit;
            }

            // Verifica se o e-mail escolhido já está em uso
            $stmtEmail = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmtEmail->execute([$email]);
            if ($stmtEmail->rowCount() > 0) {
                echo json_encode(['success' => false, 'message' => 'Este e-mail já está cadastrado no sistema.']);
                exit;
            }

            // Inicia transação SQL para garantir integridade total
            $pdo->beginTransaction();

            // Insere o novo usuário herdando o nível ('role') predefinido no convite
            $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
            $stmtInsere = $pdo->prepare("INSERT INTO users (email, nome, senha_hash, role, autorizado, requerer_troca) VALUES (?, ?, ?, ?, TRUE, FALSE)");
            $stmtInsere->execute([$email, $nome, $senha_hash, $convite['role']]);

            // Marca o convite correspondente como utilizado
            $stmtUsaConvite = $pdo->prepare("UPDATE user_invites SET usado = TRUE WHERE id = ?");
            $stmtUsaConvite->execute([$convite['id']]);

            $pdo->commit();

            echo json_encode(['success' => true, 'message' => 'Registo efetuado com sucesso! Agora você já pode iniciar sessão com seu e-mail e senha.']);
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo json_encode(['success' => false, 'message' => 'Erro interno de processamento ao registrar usuário: ' . $e->getMessage()]);
        }
        exit;
    }

    // =========================================================================
    // REQUISIÇÕES ADMINISTRATIVAS (EXIGEM VALIDAÇÃO DE TOKEN DE SESSÃO DO MASTER/SUPER)
    // =========================================================================
    $token = $data['token'] ?? '';
    
    try {
        $stmtSessao = $pdo->prepare("
            SELECT u.id, u.role 
            FROM user_sessions s 
            JOIN users u ON s.user_id = u.id 
            WHERE s.token = ? AND s.active = TRUE
        ");
        $stmtSessao->execute([$token]);
        $usuarioLogado = $stmtSessao->fetch(PDO::FETCH_ASSOC);

        if (!$usuarioLogado || !in_array($usuarioLogado['role'], ['master', 'super'])) {
            echo json_encode(['success' => false, 'message' => 'Sessão administrativa inválida ou expirada.']);
            exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro de validação de segurança.']);
        exit;
    }

    // 1. GERAR NOVO CÓDIGO DE CONVITE (NOVO!)
    if ($action === 'CREATE_INVITE') {
        $roleConvite = trim($data['role'] ?? 'user');

        if (!in_array($roleConvite, ['user', 'super', 'master'])) {
            echo json_encode(['success' => false, 'message' => 'Nível de permissão inválido para o convite.']);
            exit;
        }

        try {
            // Gera um código alfanumérico aleatório seguro e curto de 8 dígitos (ex: K9FA39BD)
            $novoCodigo = strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

            $stmtConvite = $pdo->prepare("INSERT INTO user_invites (codigo, role, criado_por) VALUES (?, ?, ?)");
            $stmtConvite->execute([$novoCodigo, $roleConvite, $usuarioLogado['id']]);

            echo json_encode([
                'success' => true,
                'message' => 'Código de convite gerado com sucesso!',
                'codigo' => $novoCodigo
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erro ao gerar o código no banco.']);
        }
        exit;
    }

    // 2. EXCLUIR CONVITE ATIVO
    if ($action === 'DELETE_INVITE' && isset($data['id'])) {
        try {
            $id = intval($data['id']);
            $stmt = $pdo->prepare("DELETE FROM user_invites WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Código de convite removido.']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erro ao apagar código.']);
        }
        exit;
    }

    // 3. EXCLUIR UTILIZADOR
    if ($action === 'DELETE' && isset($data['id'])) {
        try {
            $id = intval($data['id']);
            
            $check = $pdo->prepare("SELECT email FROM users WHERE id = ?");
            $check->execute([$id]);
            $user = $check->fetch(PDO::FETCH_ASSOC);
            
            if ($user && strtolower($user['email']) === 'ricardomaster@gmail.com') {
                echo json_encode(['success' => false, 'message' => 'Não é permitido excluir o administrador mestre principal.']);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Utilizador removido com sucesso.']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erro ao remover o utilizador.']);
        }
        exit;
    }

    // 4. ATUALIZAR NÍVEL DE ACESSO (ROLE)
    if ($action === 'UPDATE_ROLE' && isset($data['id']) && isset($data['role'])) {
        try {
            $id = intval($data['id']);
            $role = trim($data['role']);

            if (!in_array($role, ['user', 'super', 'master'])) {
                echo json_encode(['success' => false, 'message' => 'Nível de acesso inválido.']);
                exit;
            }

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

    // 5. RESETAR SENHA
    if ($action === 'RESET_PASSWORD' && isset($data['id'])) {
        try {
            $id = intval($data['id']);
            $senhaPadrao = '123456';
            $senha_hash = password_hash($senhaPadrao, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("UPDATE users SET senha_hash = ?, requerer_troca = TRUE WHERE id = ?");
            $stmt->execute([$senha_hash, $id]);

            echo json_encode([
                'success' => true, 
                'message' => "Senha redefinida para a padrão temporária: $senhaPadrao. O utilizador será obrigado a trocá-la no primeiro acesso."
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erro ao redefinir a senha.']);
        }
        exit;
    }

    // 6. ALTERAR SENHA MANDATÓRIA (PRIMEIRO ACESSO)
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

    // 7. CRIAR UTILIZADOR MANUALMENTE (FLUXO ANTIGO/RESERVA)
    $nome = trim($data['nome'] ?? '');
    $email = trim($data['email'] ?? '');
    $senha = trim($data['senha'] ?? '');
    $role = trim($data['role'] ?? 'user');

    if (empty($nome) || empty($email) || empty($senha)) {
        echo json_encode(['success' => false, 'message' => 'Preencha todos os campos obrigatórios.']);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => false, 'message' => 'Este e-mail já está cadastrado.']);
            exit;
        }

        $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (email, nome, senha_hash, role, autorizado, requerer_troca) VALUES (?, ?, ?, ?, TRUE, FALSE)");
        $stmt->execute([$email, $nome, $senha_hash, $role]);

        echo json_encode(['success' => true, 'message' => 'Utilizador cadastrado com sucesso!']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao cadastrar: ' . $e->getMessage()]);
    }
    exit;
}
?>
