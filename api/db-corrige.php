<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: text/html; charset=UTF-8");

include 'config.php';

echo "<!DOCTYPE html>
<html lang='pt-PT'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Correção de Banco de Dados - Pulmão AE</title>
    <script src='https://cdn.tailwindcss.com'></script>
</head>
<body class='bg-slate-950 text-white min-h-screen flex items-center justify-center p-4 font-sans'>";

echo "<div class='max-w-xl w-full bg-slate-900 border border-slate-800 rounded-3xl p-8 shadow-2xl'>";
echo "<div class='text-center mb-6'>
        <span class='text-5xl block mb-2'>🔧</span>
        <h1 class='text-2xl font-black text-emerald-400'>Migração do Banco de Dados</h1>
        <p class='text-xs text-slate-400 mt-1'>Sincronizando novas tabelas de convite e controle</p>
      </div>";

echo "<div class='space-y-3 font-mono text-xs bg-slate-950/80 p-4 rounded-2xl border border-slate-800 max-h-96 overflow-y-auto'>";

function executarMigracao($sql, $descricao) {
    global $pdo;
    try {
        $pdo->exec($sql);
        echo "<p class='text-emerald-400 flex items-center gap-2'><span>✅</span> <strong>$descricao:</strong> Sincronizado com sucesso!</p>";
        return true;
    } catch (Exception $e) {
        if (strpos($e->getMessage(), 'already exists') !== false || strpos($e->getMessage(), 'duplicate column') !== false) {
            echo "<p class='text-slate-400 flex items-center gap-2'><span>ℹ️</span> <strong>$descricao:</strong> Já configurado anteriormente.</p>";
            return true;
        }
        echo "<p class='text-red-400 flex items-center gap-2'><span>❌</span> <strong>Erro em $descricao:</strong> <br><span class='text-red-300/80 pl-4'>" . htmlspecialchars($e->getMessage()) . "</span></p>";
        return false;
    }
}

echo "<p class='text-blue-400 font-bold border-b border-slate-800 pb-1 mb-2 text-sm'>1. Verificando Tabelas do Sistema...</p>";

executarMigracao("
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
", "Tabela de Utilizadores ('users')");

executarMigracao("
    CREATE TABLE IF NOT EXISTS user_sessions (
        id SERIAL PRIMARY KEY,
        user_id INT NOT NULL,
        token VARCHAR(255) UNIQUE NOT NULL,
        active BOOLEAN DEFAULT TRUE,
        ip_address VARCHAR(45) NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
", "Tabela de Sessões ('user_sessions')");

// NOVA TABELA: user_invites
executarMigracao("
    CREATE TABLE IF NOT EXISTS user_invites (
        id SERIAL PRIMARY KEY,
        codigo VARCHAR(100) UNIQUE NOT NULL,
        role VARCHAR(20) DEFAULT 'user',
        usado BOOLEAN DEFAULT FALSE,
        criado_por INT NULL,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
", "Tabela de Convites de Adesão ('user_invites')");

executarMigracao("
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
", "Tabela de Estoque ('enderecos')");

echo "<p class='text-blue-400 font-bold border-b border-slate-800 pb-1 mt-4 mb-2 text-sm'>2. Atualizando Campos e Parâmetros...</p>";
executarMigracao("ALTER TABLE users ADD COLUMN IF NOT EXISTS requerer_troca BOOLEAN DEFAULT FALSE;", "Campo 'requerer_troca' em 'users'");
executarMigracao("ALTER TABLE enderecos ADD COLUMN IF NOT EXISTS quantidade INT DEFAULT 1;", "Campo 'quantidade' em 'enderecos'");

echo "<p class='text-blue-400 font-bold border-b border-slate-800 pb-1 mt-4 mb-2 text-sm'>3. Validando Administrador Master...</p>";

try {
    $emailMaster = 'ricardomaster@gmail.com';
    $nomeMaster = 'Ricardo Master';
    $senha_master_hash = password_hash('8486', PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$emailMaster]);
    
    if ($stmt->rowCount() === 0) {
        $insere = $pdo->prepare("INSERT INTO users (email, nome, senha_hash, role, encryption_seed, autorizado, requerer_troca) 
                               VALUES (?, ?, ?, 'master', 'chave-master-2026', TRUE, FALSE)");
        $insere->execute([$emailMaster, $nomeMaster, $senha_master_hash]);
        echo "<p class='text-emerald-400 flex items-center gap-2'><span>✅</span> Ricardo Master criado com sucesso!</p>";
    } else {
        $atualiza = $pdo->prepare("UPDATE users SET role = 'master', autorizado = TRUE, requerer_troca = FALSE WHERE email = ?");
        $atualiza->execute([$emailMaster]);
        echo "<p class='text-emerald-400 flex items-center gap-2'><span>✅</span> Ricardo Master verificado e atualizado com privilégios master.</p>";
    }
} catch (Exception $e) {
    echo "<p class='text-red-400 flex items-center gap-2'><span>❌</span> Falha no Ricardo Master: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</div>";

echo "<div class='mt-6 pt-4 border-t border-slate-800 text-center'>
        <p class='text-sm text-slate-300 font-semibold mb-3'>Migração de tabelas de segurança concluída!</p>
        <a href='https://devsereno.github.io/codril/login.html' class='inline-block bg-blue-600 hover:bg-blue-500 text-white font-bold px-6 py-3 rounded-2xl text-xs transition-all shadow-lg shadow-blue-600/25 active:scale-95'>
            Ir para a Tela de Login →
        </a>
      </div>";

echo "</div>";
echo "</body></html>";
?>
