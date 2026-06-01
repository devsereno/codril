<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

try {
    $dsn = "pgsql:host=SEU_HOST;dbname=SEU_DB";
    $pdo = new PDO($dsn, "SEU_USUARIO", "SUA_SENHA");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $method = $_SERVER['REQUEST_METHOD'];

    if ($method === 'GET') {
        // SELECT seguro: tente selecionar sem o status_estoque primeiro se der erro, 
        // mas aqui vamos garantir a query.
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
        echo json_encode(["success" => true]);
    }
} catch (Exception $e) {
    // ISSO VAI MOSTRAR O ERRO REAL NO SEU NAVEGADOR
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
