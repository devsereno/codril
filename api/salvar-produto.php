<?php
// Desativa a exibição de erros que podem quebrar o JSON
ini_set('display_errors', 0);
error_reporting(0);

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// Configuração da base de dados (PDO)
$dsn = "pgsql:host=SEU_HOST;dbname=SEU_DB";
$pdo = new PDO($dsn, "SEU_USUARIO", "SUA_SENHA");

$method = $_SERVER['REQUEST_METHOD'];

// Lógica de Leitura (GET)
if ($method === 'GET') {
    $stmt = $pdo->query("SELECT id, tipo, endereco, codigo_produto, lote, quantidade, descricao, status_estoque FROM enderecos ORDER BY id DESC");
    $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(["success" => true, "itens" => $itens]);
}

// Lógica de Salvamento (POST)
if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);

    // Validação básica
    if (!isset($data['endereco'], $data['codigo_produto'])) {
        echo json_encode(["success" => false, "message" => "Dados incompletos"]);
        exit;
    }

    // QUERY DE INSERT ATUALIZADA COM O status_estoque
    $sql = "INSERT INTO enderecos (tipo, endereco, codigo_produto, lote, quantidade, descricao, status_estoque) 
            VALUES (:tipo, :endereco, :codigo, :lote, :qtd, :desc, :status)";
    
    $stmt = $pdo->prepare($sql);
    $resultado = $stmt->execute([
        ':tipo'    => $data['tipo'] ?? 'Pulmao',
        ':endereco'=> $data['endereco'],
        ':codigo'  => $data['codigo_produto'],
        ':lote'    => $data['lote'],
        ':qtd'     => $data['quantidade'] ?? 1,
        ':desc'    => $data['descricao'] ?? '',
        ':status'  => $data['status_estoque'] ?? 'Normal' // Recebendo o novo valor
    ]);

    if ($resultado) {
        echo json_encode(["success" => true, "message" => "Guardado com sucesso!"]);
    } else {
        echo json_encode(["success" => false, "message" => "Erro ao guardar na base de dados"]);
    }
}
?>
