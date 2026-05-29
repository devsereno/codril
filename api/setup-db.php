<?php
// Permite conexões do seu GitHub Pages para o Render
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
    <title>Migração e Setup de Banco - Pulmão AE</title>
    <script src='https://cdn.tailwindcss.com'></script>
</head>
<body class='bg-slate-950 text-white min-h-screen flex items-center justify-center p-4 font-sans'>";

echo "<div class='max-w-xl w-full bg-slate-900 border border-slate-800 rounded-3xl p-8 shadow-2xl'>";
echo "<div class='text-center mb-6'>
        <span class='text-5xl block mb-2'>🚀</span>
        <h1 class='text-2xl font-black text-blue-400'>Sincronização de Banco de Dados</h1>
        <p class='text-xs text-slate-400 mt-1'>Executando atualizações e migrações automáticas...</p>
      </div>";

echo "<div class='space-y-3 font-mono text-xs bg-slate-950/80 p-4 rounded-2xl border border-slate-800 max-h-96 overflow-y-auto'>";

function executarSQL($sql, $descricao) {
    global $pdo;
    try {
        $pdo->exec($sql);
        echo "<p class='text-emerald-400 flex items-center gap-2'><span>✅</span> <strong>Sucesso:</strong> $descricao</p>";
        return true;
    } catch (Exception $e) {
        // Se der erro de coluna duplicada, tudo bem, apenas avisamos de forma limpa
        if (strpos($e->getMessage(), 'already exists') !== false || strpos($e->getMessage(), 'duplicate column') !== false) {
            echo "<p class='text-slate-400 flex items-center gap-2'><span>ℹ️</span> <strong>Existente:</strong> $descricao (Já configurada)</p>";
            return true;
        }
        echo "<p class='text-red-400 flex items-center gap-2'><span>❌</span> <strong>Erro em $descricao:</strong> <br><span class='text-red-300/80 pl-4'>" . htmlspecialchars($e->getMessage()) . "</span></p>";
        return false;
    }
}

// 1. CRIAÇÃO DAS TABELAS BASE
echo "<p class='text-blue-400 font-bold border-b border-slate-800 pb-1 mb-2 text-sm'>1. Verificando Tabelas Base...</p>";

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


// 2. MIGRAÇÃO DE COLUNAS NOVAS
echo "<p class='text-blue-400 font-bold border-b border-slate-800 pb-1 mt-4 mb-2 text-sm'>2. Executando Migração de Campos...</p>";

// Adiciona coluna requerer_troca na tabela users se não existir
executarSQL("ALTER TABLE users ADD COLUMN IF NOT EXISTS requerer_troca BOOLEAN DEFAULT FALSE;", "Adicionar campo 'requerer_troca' à tabela de Usuários");

// Adiciona coluna quantidade na tabela de endereços se não existir
executarSQL("ALTER TABLE enderecos ADD COLUMN IF NOT EXISTS quantidade INT DEFAULT 1;", "Adicionar campo 'quantidade' à tabela de Endereços");


// 3. CRIAÇÃO E AJUSTE DO USUÁRIO MASTER
echo "<p class='text-blue-400 font-bold border-b border-slate-800 pb-1 mt-4 mb-2 text-sm'>3. Configurando Ricardo Master...</p>";

try {
    $emailMaster = 'ricardomaster@gmail.com';
    $nomeMaster = 'Ricardo Master';
    $senha_master_hash = password_hash('8486', PASSWORD_DEFAULT);

    // Verifica se já existe o master
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$emailMaster]);
    
    if ($stmt->rowCount() === 0) {
        $insere = $pdo->prepare("INSERT INTO users (email, nome, senha_hash, role, encryption_seed, autorizado, requerer_troca) 
                               VALUES (?, ?, ?, 'master', 'chave-master-2026', TRUE, FALSE)");
        $insere->execute([$emailMaster, $nomeMaster, $senha_master_hash]);
        echo "<p class='text-emerald-400 flex items-center gap-2'><span>✅</span> Ricardo Master cadastrado do zero com sucesso!</p>";
    } else {
        // Se já existe, apenas garante as permissões corretas dele
        $atualiza = $pdo->prepare("UPDATE users SET role = 'master', autorizado = TRUE, requerer_troca = FALSE WHERE email = ?");
        $atualiza->execute([$emailMaster]);
        echo "<p class='text-emerald-400 flex items-center gap-2'><span>✅</span> Ricardo Master verificado e atualizado como Master!</p>";
    }
} catch (Exception $e) {
    echo "<p class='text-red-400 flex items-center gap-2'><span>❌</span> Erro ao ajustar usuário Master: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</div>"; // Fecha console

echo "<div class='mt-6 pt-4 border-t border-slate-800 text-center'>
        <p class='text-sm text-slate-300 font-semibold mb-3'>Sincronização Finalizada com Sucesso!</p>
        <a href='https://devsereno.github.io/codril/login.html' class='inline-block bg-blue-600 hover:bg-blue-500 text-white font-bold px-6 py-3 rounded-2xl text-xs transition-all shadow-lg shadow-blue-600/25 active:scale-95'>
            Ir para a Tela de Login →
        </a>
      </div>";

echo "</div>"; // Fecha container
echo "</body></html>";
?>
