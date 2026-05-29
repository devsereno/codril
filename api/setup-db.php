<?php
// Permite acessos e requisições externas
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

include 'config.php';

// ==================== PROCESSAMENTO DE REQUISIÇÕES AJAX (POST) ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header("Content-Type: application/json; charset=UTF-8");
    $data = json_decode(file_get_contents('php://input'), true);
    
    $action = $data['action'] ?? '';
    $senha = $data['senha'] ?? '';

    // Validação de senha de segurança (sua senha Master)
    if ($senha !== '8486') {
        echo json_encode(['success' => false, 'message' => 'Senha de segurança incorreta!']);
        exit;
    }

    // Ação 1: Configuração Automática das Tabelas Base
    if ($action === 'auto_setup') {
        try {
            $pdo->exec("
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
            ");

            $pdo->exec("
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
            ");

            // Insere usuário Ricardo Master
            $email = 'ricardomaster@gmail.com';
            $nome = 'Ricardo Master';
            $senha_master_hash = password_hash('8486', PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("INSERT INTO users (email, nome, senha_hash, role, encryption_seed, autorizado) 
                                   VALUES (?, ?, ?, 'master', 'chave-master-2026', TRUE)
                                   ON CONFLICT (email) DO NOTHING");
            $stmt->execute([$email, $nome, $senha_master_hash]);

            echo json_encode([
                'success' => true, 
                'message' => 'Tabelas base "users", "enderecos" e o usuário Ricardo Master configurados com sucesso!'
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erro no auto-setup: ' . $e->getMessage()]);
        }
        exit;
    }

    // Ação 2: Executar SQL Livre digitado no painel
    if ($action === 'executar_sql') {
        $sql = trim($data['sql'] ?? '');
        if (empty($sql)) {
            echo json_encode(['success' => false, 'message' => 'O comando SQL não pode estar vazio.']);
            exit;
        }

        try {
            // Se for um comando SELECT, tenta retornar as linhas
            if (stripos($sql, 'SELECT') === 0 || stripos($sql, 'SHOW') === 0 || stripos($sql, 'DESCRIBE') === 0) {
                $stmt = $pdo->query($sql);
                $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode([
                    'success' => true, 
                    'message' => 'Query de consulta executada com sucesso!', 
                    'dados' => $resultados
                ]);
            } else {
                // Para comandos de edição (CREATE, ALTER, INSERT, UPDATE, DROP)
                $linhasAfetadas = $pdo->exec($sql);
                echo json_encode([
                    'success' => true, 
                    'message' => 'Comando executado com sucesso! Linhas afetadas: ' . ($linhasAfetadas === false ? 0 : $linhasAfetadas)
                ]);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erro na execução do SQL: ' . $e->getMessage()]);
        }
        exit;
    }

    // Ação 3: Obter tabelas existentes
    if ($action === 'obter_tabelas') {
        try {
            // Tenta obter as tabelas do banco de dados (funciona para PostgreSQL e MySQL)
            $tabelas = [];
            
            // Tenta para PostgreSQL/CockroachDB
            try {
                $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema='public'");
                $tabelas = $stmt->fetchAll(PDO::FETCH_COLUMN);
            } catch (Exception $ex) {
                // Tenta para MySQL/MariaDB
                $stmt = $pdo->query("SHOW TABLES");
                $tabelas = $stmt->fetchAll(PDO::FETCH_COLUMN);
            }

            echo json_encode(['success' => true, 'tabelas' => $tabelas]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Não foi possível ler as tabelas: ' . $e->getMessage()]);
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Ação não suportada.']);
    exit;
}
?>
<!-- ==================== INTERFACE GRÁFICA DO PAINEL (GET) ==================== -->
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Setup & Terminal SQL - Pulmão AE</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-950 text-white min-h-screen p-4 font-sans">

  <!-- TOAST NOTIFICATION -->
  <div id="toast" class="fixed top-5 left-1/2 -translate-x-1/2 z-50 transform transition-all duration-300 opacity-0 pointer-events-none scale-90">
    <div id="toastBg" class="flex items-center gap-3 px-6 py-4 rounded-2xl shadow-2xl border backdrop-blur-sm">
      <span id="toastIcon" class="text-xl"></span>
      <span id="toastMessage" class="font-medium text-sm"></span>
    </div>
  </div>

  <div class="max-w-4xl mx-auto py-8">
    
    <!-- Cabeçalho -->
    <div class="flex items-center gap-4 mb-8 border-b border-slate-800 pb-6">
      <span class="text-5xl">🚀</span>
      <div>
        <h1 class="text-3xl font-extrabold text-blue-400">Terminal & Setup DB</h1>
        <p class="text-slate-400 text-sm">Gerencie, crie e altere tabelas do seu banco Render em tempo real</p>
      </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

      <!-- Esquerda: Auto Setup e Tabelas Existentes -->
      <div class="md:col-span-1 space-y-6">
        
        <!-- Bloco 1: Auto-Setup -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl">
          <h2 class="text-lg font-bold mb-2 text-slate-200">1. Setup Inicial</h2>
          <p class="text-xs text-slate-400 mb-6">Cria as tabelas base e o login Master de forma automatizada no banco.</p>
          
          <div class="space-y-4">
            <input id="senhaAuto" type="password" placeholder="Digite a Senha Mestra (8486)" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-4 py-3 text-xs text-center focus:outline-none focus:border-blue-500 font-mono">
            <button onclick="executarAutoSetup()" class="w-full bg-blue-600 hover:bg-blue-500 py-3 rounded-xl text-xs font-bold transition-all active:scale-95">
              Instalar Estruturas Base
            </button>
          </div>
        </div>

        <!-- Bloco 2: Lista de Tabelas Ativas -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl">
          <div class="flex justify-between items-center mb-4">
            <h2 class="text-sm font-bold text-slate-300 uppercase tracking-wider">Tabelas Ativas</h2>
            <button onclick="obterTabelasAtivas()" class="text-[10px] text-blue-400 font-bold hover:underline">Atualizar 🔄</button>
          </div>
          
          <div id="listaTabelas" class="space-y-2 text-xs font-mono">
            <p class="text-slate-500">Clique em atualizar para verificar...</p>
          </div>
        </div>

      </div>

      <!-- Direita: Terminal SQL Livre -->
      <div class="md:col-span-2">
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl h-full flex flex-col justify-between">
          <div>
            <h2 class="text-lg font-bold mb-1 text-slate-200 flex items-center gap-2">
              <span>💻</span> 2. Terminal SQL Livre
            </h2>
            <p class="text-xs text-slate-400 mb-4">Deseja criar uma nova tabela, adicionar uma coluna ou deletar algo? Digite o comando SQL abaixo:</p>

            <textarea id="sqlTerminal" placeholder="EXEMPLO DE COMANDO:&#10;CREATE TABLE IF NOT EXISTS historico_movimentos (&#10;    id SERIAL PRIMARY KEY,&#10;    produto_id INT,&#10;    acao VARCHAR(50),&#10;    data TIMESTAMP DEFAULT CURRENT_TIMESTAMP&#10;);" 
                      class="w-full h-64 bg-slate-950 border border-slate-800 rounded-2xl p-4 font-mono text-xs text-slate-300 focus:outline-none focus:border-blue-500 leading-relaxed"></textarea>
          </div>

          <div class="mt-6 pt-4 border-t border-slate-800 flex flex-col sm:flex-row gap-3 items-center">
            <div class="w-full sm:w-1/2">
              <input id="senhaTerminal" type="password" placeholder="Chave de Segurança (8486)" class="w-full bg-slate-800 border border-slate-700 rounded-xl px-4 py-3 text-xs text-center focus:outline-none focus:border-blue-500 font-mono">
            </div>
            <button onclick="executarSQLTerm()" class="w-full sm:w-1/2 bg-emerald-600 hover:bg-emerald-500 py-3 rounded-xl text-xs font-bold transition-all active:scale-95 text-center">
              ⚡ Executar Comando SQL
            </button>
          </div>
        </div>
      </div>

    </div>

    <!-- Visualizador de Retornos e Respostas -->
    <div class="mt-6 bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl">
      <h3 class="text-xs text-slate-500 uppercase tracking-wider font-bold mb-3">Retorno do Terminal SQL</h3>
      <div id="resultadoTerminal" class="bg-slate-950 border border-slate-800 rounded-2xl p-4 font-mono text-xs text-slate-400 max-h-[300px] overflow-auto">
        Nenhum comando SQL enviado ainda.
      </div>
    </div>

  </div>

  <script>
    // Sistema Toast de Alerta
    function mostrarAviso(mensagem, tipo = 'sucesso') {
      const toast = document.getElementById('toast');
      const toastBg = document.getElementById('toastBg');
      const toastIcon = document.getElementById('toastIcon');
      const toastMessage = document.getElementById('toastMessage');

      if (tipo === 'sucesso') {
        toastBg.className = "flex items-center gap-3 px-6 py-4 rounded-2xl shadow-2xl border bg-emerald-500/90 border-emerald-400 text-white backdrop-blur-sm";
        toastIcon.textContent = "✅";
      } else if (tipo === 'erro') {
        toastBg.className = "flex items-center gap-3 px-6 py-4 rounded-2xl shadow-2xl border bg-red-500/90 border-red-400 text-white backdrop-blur-sm";
        toastIcon.textContent = "❌";
      } else {
        toastBg.className = "flex items-center gap-3 px-6 py-4 rounded-2xl shadow-2xl border bg-amber-500/90 border-amber-400 text-white backdrop-blur-sm";
        toastIcon.textContent = "⚠️";
      }

      toastMessage.textContent = mensagem;
      toast.classList.remove('opacity-0', 'pointer-events-none', 'scale-90', 'top-5');
      toast.classList.add('opacity-100', 'scale-100', 'top-8');

      setTimeout(() => {
        toast.classList.remove('opacity-100', 'scale-100', 'top-8');
        toast.classList.add('opacity-0', 'pointer-events-none', 'scale-90', 'top-5');
      }, 3000);
    }

    // Acionar a Instalação Inicial Base
    async function executarAutoSetup() {
      const senha = document.getElementById('senhaAuto').value;
      if (!senha) return mostrarAviso("Insira a chave de segurança para instalar.", "alerta");

      try {
        const res = await fetch('', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'auto_setup', senha })
        });
        const data = await res.json();

        if (data.success) {
          mostrarAviso(data.message);
          obterTabelasAtivas();
        } else {
          mostrarAviso(data.message, "erro");
        }
      } catch (err) {
        mostrarAviso("Erro na comunicação com o servidor.", "erro");
      }
    }

    // Executar comandos SQL digitados na área de texto
    async function executarSQLTerm() {
      const sql = document.getElementById('sqlTerminal').value.trim();
      const senha = document.getElementById('senhaTerminal').value;
      const saida = document.getElementById('resultadoTerminal');

      if (!sql) return mostrarAviso("Escreva um comando SQL primeiro.", "alerta");
      if (!senha) return mostrarAviso("Insira a chave para confirmar.", "alerta");

      saida.textContent = "Processando comando SQL...";

      try {
        const res = await fetch('', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'executar_sql', senha, sql })
        });
        const data = await res.json();

        if (data.success) {
          mostrarAviso(data.message);
          
          if (data.dados) {
            // Se retornar linhas do banco de dados (Query de SELECT), monta visualização bonita
            saida.innerHTML = `<p class="text-emerald-400 font-bold mb-2">Resultados retornados (${data.dados.length}):</p>` +
                              `<pre class="text-slate-300">${JSON.stringify(data.dados, null, 2)}</pre>`;
          } else {
            saida.innerHTML = `<span class="text-emerald-400">✅ ${data.message}</span>`;
          }
          obterTabelasAtivas();
        } else {
          mostrarAviso(data.message, "erro");
          saida.innerHTML = `<span class="text-red-400">❌ ${data.message}</span>`;
        }
      } catch (err) {
        mostrarAviso("Erro na execução.", "erro");
        saida.textContent = "Erro crítico de conexão com a API.";
      }
    }

    // Listar tabelas criadas no banco de dados ativo
    async function obterTabelasAtivas() {
      const container = document.getElementById('listaTabelas');
      
      try {
        const res = await fetch('', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'obter_tabelas', senha: '8486' }) // usa a chave mestre para obter
        });
        const data = await res.json();

        if (data.success && data.tabelas) {
          if (data.tabelas.length === 0) {
            container.innerHTML = '<p class="text-slate-600">Nenhuma tabela criada ainda.</p>';
            return;
          }

          let html = '';
          data.tabelas.forEach(tab => {
            html += `<div class="flex items-center gap-2 bg-slate-950 px-3 py-2 rounded-xl border border-slate-800/80">` +
                      `<span class="text-blue-400">📊</span>` +
                      `<span class="font-bold text-slate-300">${tab}</span>` +
                    `</div>`;
          });
          container.innerHTML = html;
        } else {
          container.innerHTML = '<p class="text-red-500">Erro ao carregar lista de tabelas.</p>';
        }
      } catch (err) {
        container.innerHTML = '<p class="text-red-500">Sem conexão.</p>';
      }
    }

    // Roda no carregamento inicial para atualizar as tabelas se já houver tabelas criadas
    obterTabelasAtivas();
  </script>
</body>
</html>
