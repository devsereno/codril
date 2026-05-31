<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Content-Type: application/json; charset=UTF-8");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include 'config.php';

// Captura o Token enviado pelo Frontend (seja via cabeçalho Authorization ou corpo JSON)
$headers = apache_request_headers();
$token = null;

if (isset($headers['Authorization'])) {
    $token = trim(str_replace('Bearer', '', $headers['Authorization']));
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$token && isset($data['token'])) {
    $token = $data['token'];
}

// =========================================================================
// VALIDAÇÃO GLOBAL DE TOKEN DE SESSÃO ATIVO
// =========================================================================
$usuarioSessao = null;
if ($token) {
    try {
        $stmtSessao = $pdo->prepare("
            SELECT u.id, u.nome, u.role 
            FROM user_sessions s 
            JOIN users u ON s.user_id = u.id 
            WHERE s.token = ? AND s.active = TRUE
        ");
        $stmtSessao->execute([$token]);
        $usuarioSessao = $stmtSessao->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Falha silenciosa para prosseguir no tratamento de erros abaixo
    }
}

// ====================== MÉTODO GET: LISTAR PRODUTOS ======================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Para listar produtos, o token também pode ser passado via parâmetro de URL (?token=...)
    $tokenGet = $_GET['token'] ?? null;
    if ($tokenGet && !$usuarioSessao) {
        try {
            $stmtSessao = $pdo->prepare("SELECT u.id, u.role FROM user_sessions s JOIN users u ON s.user_id = u.id WHERE s.token = ? AND s.active = TRUE");
            $stmtSessao->execute([$tokenGet]);
            $usuarioSessao = $stmtSessao->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {}
    }

    if (!$usuarioSessao) {
        echo json_encode(['success' => false, 'message' => 'Acesso negado. Token de sessão inativo ou inválido.']);
        exit;
    }

    try {
        $stmt = $pdo->query("SELECT id, tipo, endereco, codigo_produto, lote, quantidade, descricao, data_cadastro FROM enderecos ORDER BY id DESC");
        $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'itens' => $produtos, 'dados' => $produtos]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao obter itens do banco: ' . $e->getMessage()]);
    }
    exit;
}

// ====================== MÉTODO POST: SALVAR PRODUTOS ======================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$usuarioSessao) {
        echo json_encode(['success' => false, 'message' => 'Sessão expirada. Efetue login novamente para salvar registros.']);
        exit;
    }

    $tipo = trim($data['tipo'] ?? '');
    $endereco = trim($data['endereco'] ?? '');
    $codigo = trim($data['codigo_produto'] ?? '');
    $lote = trim($data['lote'] ?? '');
    $quantidade = intval($data['quantidade'] ?? 1);
    $descricao = trim($data['descricao'] ?? '');

    if (empty($tipo) || empty($endereco) || empty($codigo) || empty($lote)) {
        echo json_encode(['success' => false, 'message' => 'Por favor, preencha todos os campos obrigatórios.']);
        exit;
    }

    try {
        // Evita duplicados exatos de lote na mesma posição física do estoque
        $stmtCheck = $pdo->prepare("SELECT id FROM enderecos WHERE tipo = ? AND endereco = ? AND codigo_produto = ? AND lote = ?");
        $stmtCheck->execute([$tipo, $endereco, $codigo, $lote]);
        
        if ($stmtCheck->rowCount() > 0) {
            echo json_encode(['success' => false, 'message' => 'Este lote já se encontra cadastrado nesta posição física.']);
            exit;
        }

        // Insere o novo registro com suporte à coluna quantidade
        $stmt = $pdo->prepare("INSERT INTO enderecos (tipo, endereco, codigo_produto, lote, quantidade, descricao) VALUES (?, ?, ?, ?, ?, ?)");
        $success = $stmt->execute([$tipo, $endereco, $codigo, $lote, $quantidade, $descricao]);

        if ($success) {
            echo json_encode(['success' => true, 'message' => 'Nova posição de estoque gravada com sucesso!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Não foi possível salvar o registro no banco.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro interno de processamento: ' . $e->getMessage()]);
    }
    exit;
}
?>
