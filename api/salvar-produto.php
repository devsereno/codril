<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include 'config.php';

// ====================== LISTAR ITENS (GET) ======================
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $pdo->query("SELECT * FROM enderecos ORDER BY data_cadastro DESC");
        $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'itens' => $itens,
            'total' => count($itens)
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Erro ao buscar itens']);
    }
    exit;
}

// ====================== SALVAR ITEM (POST) ======================
$data = json_decode(file_get_contents('php://input'), true);

$tipo = trim($data['tipo'] ?? '');
$endereco = trim($data['endereco'] ?? '');
$codigo_produto = trim($data['codigo_produto'] ?? '');
$lote = trim($data['lote'] ?? '');
$descricao = trim($data['descricao'] ?? '');

if (empty($tipo) || empty($endereco) || empty($codigo_produto) || empty($lote)) {
    echo json_encode(['success' => false, 'message' => 'Preencha todos os campos obrigatórios']);
    exit;
}

// Verifica duplicidade
$stmt = $pdo->prepare("SELECT id FROM enderecos WHERE tipo = ? AND endereco = ? AND codigo_produto = ? AND lote = ?");
$stmt->execute([$tipo, $endereco, $codigo_produto, $lote]);

if ($stmt->rowCount() > 0) {
    echo json_encode(['success' => false, 'message' => 'Este lote já foi cadastrado neste endereço']);
    exit;
}

// Salva
$stmt = $pdo->prepare("INSERT INTO enderecos (tipo, endereco, codigo_produto, lote, descricao) VALUES (?, ?, ?, ?, ?)");
$success = $stmt->execute([$tipo, $endereco, $codigo_produto, $lote, $descricao]);

if ($success) {
    echo json_encode(['success' => true, 'message' => 'Produto cadastrado com sucesso!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao salvar no banco']);
}
?>
