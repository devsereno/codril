<?php
// Carrega a configuração que você acabou de criar
require_once 'config.php'; 

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// O resto do seu código permanece igual, 
// o uso do $pdo aqui já virá do config.php
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    // Agora o $pdo está definido corretamente pelo config.php
    $stmt = $pdo->query("SELECT * FROM enderecos ORDER BY id DESC");
    $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(["success" => true, "itens" => $itens]);
}

if ($method === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    $sql = "INSERT INTO enderecos (tipo, endereco, codigo_produto, lote, quantidade, descricao, status_estoque) 
            VALUES (:tipo, :endereco, :codigo, :lote, :qtd, :desc, :status)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':tipo'     => $data['tipo'] ?? 'Pulmao',
        ':endereco' => $data['endereco'] ?? 0,
        ':codigo'   => $data['codigo_produto'] ?? 0,
        ':lote'     => $data['lote'] ?? '',
        ':qtd'      => $data['quantidade'] ?? 1,
        ':desc'     => $data['descricao'] ?? '',
        ':status'   => $data['status_estoque'] ?? 'Normal'
    ]);
    echo json_encode(["success" => true, "message" => "Guardado com sucesso!"]);
}
?>
