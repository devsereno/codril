
<?php
include 'config.php';

echo "<h1 style='color:white; background:#1e2937; padding:20px; border-radius:10px;'>🚀 Configuração do Banco de Dados</h1>";

function executarSQL($sql, $descricao) {
    global $pdo;
    try {
        $pdo->exec($sql);
        echo "<p style='color:green; font-weight:bold;'>✅ $descricao</p>";
        return true;
    } catch (Exception $e) {
        echo "<p style='color:red;'>❌ $descricao<br><small>" . $e->getMessage() . "</small></p>";
        return false;
    }
}

// ==================== CRIAÇÃO DAS TABELAS ====================

echo "<h2 style='color:#60a5fa;'>Criando tabelas...</h2>";

executarSQL("
    CREATE TABLE IF NOT EXISTS users (
        id SERIAL PRIMARY KEY,
        email VARCHAR(255) UNIQUE NOT NULL,
        nome VARCHAR(150) NOT NULL,
        senha_hash VARCHAR(255) NOT NULL,
        role VARCHAR(20) DEFAULT 'user',
        encryption_seed VARCHAR(255),
        autorizado BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
", "Tabela 'users'");

executarSQL("
    CREATE TABLE IF NOT EXISTS enderecos (
        id SERIAL PRIMARY KEY,
        tipo VARCHAR(20) NOT NULL,
        endereco VARCHAR(50) NOT NULL,
        codigo_produto VARCHAR(50) NOT NULL,
        lote VARCHAR(50) NOT NULL,
        descricao TEXT,
        data_cadastro TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE (tipo, endereco, codigo_produto, lote)
    )
", "Tabela 'enderecos'");

// ==================== USUÁRIO MASTER ====================

echo "<h2 style='color:#60a5fa; margin-top:20px;'>Criando usuário Master...</h2>";

$email = 'ricardomaster@gmail.com';
$nome = 'Ricardo Master';
$senha = '8486';
$senha_hash = password_hash($senha, PASSWORD_DEFAULT);

$stmt = $pdo->prepare("INSERT INTO users (email, nome, senha_hash, role, encryption_seed, autorizado) 
                       VALUES (?, ?, ?, 'master', 'chave-master-2026', TRUE)
                       ON CONFLICT (email) DO NOTHING");

if ($stmt->execute([$email, $nome, $senha_hash])) {
    echo "<p style='color:green; font-weight:bold;'>✅ Usuário Master criado ou já existia!</p>";
    echo "<p><strong>Email:</strong> $email</p>";
    echo "<p><strong>Senha:</strong> 8486</p>";
} else {
    echo "<p style='color:orange;'>Usuário Master já existia.</p>";
}

echo "<hr><h3 style='color:lime;'>✅ Configuração finalizada com sucesso!</h3>";
echo "<p><a href='login.html' style='color:#60a5fa;'>→ Ir para o Login</a></p>";
?>
