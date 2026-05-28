<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

include 'config.php';

$data = json_decode(file_get_contents('php://input'), true);

$email = trim($data['email'] ?? '');
$senha = trim($data['senha'] ?? '');

if (empty($email) || empty($senha)) {
    echo json_encode(['success' => false, 'message' => 'Email e senha são obrigatórios']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user && password_verify($senha, $user['senha_hash']) && $user['autorizado']) {
    echo json_encode([
        'success' => true,
        'user' => [
            'email' => $user['email'],
            'nome' => $user['nome'],
            'role' => $user['role']
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Login inválido ou não autorizado']);
}
?>
