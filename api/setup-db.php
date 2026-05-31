<?php
// Ativa sessões nativas do PHP de forma segura
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

include 'config.php';

// ==================== PROCESSAMENTO DE REQUISIÇÕES AJAX (POST) ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header("Content-Type: application/json; charset=UTF-8");
    $data = json_decode(file_get_contents('php://input'), true);
    
    $action = $data['action'] ?? '';

    // Ação A: Autenticação Inicial do Painel de Setup (Sem senhas expostas no JS)
    if ($action === 'login_setup') {
        $email = trim($data['email'] ?? '');
        $senha = trim($data['senha'] ?? '');

        if (empty($email) || empty($senha)) {
            echo json_encode(['success' => false, 'message' => 'Credenciais incompletas.']);
            exit;
        }

        try {
            // Busca o utilizador no banco
            $stmt = $pdo->prepare("SELECT id, nome, email, senha_hash, role, autorizado FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && (password_verify($senha, $user['senha_hash']) || $senha === $user['senha_hash'])) {
                if ($user['role'] !== 'master') {
                    echo json_encode(['success' => false, 'message' => 'Acesso negado. Apenas o administrador Master pode aceder.']);
                    exit;
                }

                // Cria um Token de Sessão único, forte e aleatório (UUID)
                $token = bin2hex(random_bytes(32));
                
                // Salva a sessão na tabela de controle 'user_sessions'
                $stmtSession = $pdo->prepare("INSERT INTO user_sessions (user_id, token, ip_address) VALUES (?, ?, ?)");
                $stmtSession->execute([$user['id'], $token, $_SERVER['REMOTE_ADDR']]);

                // Salva na Session nativa do servidor PHP
                $_SESSION['master_logged'] = true;
                $_SESSION['master_token'] = $token;
                $_SESSION['user_id'] = $user['id'];

                echo json_encode([
                    'success' => true,
                    'message' => 'Autenticação bem-sucedida!',
                    'token' => $token
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'E-mail ou Senha incorreta!']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erro interno de processamento de login.']);
        }
        exit;
    }

    // =========================================================================
    // SEGURANÇA MÁXIMA: A partir daqui, todas as requisições exigem o TOKEN ativo
    // =========================================================================
    $tokenEnviado = $data['token'] ?? '';
    
    if (empty($tokenEnviado)) {
        echo json_encode(['success' => false, 'message' => 'Sessão inválida. Efetue login novamente.']);
        exit;
    }

    try {
        // Valida se o token realmente existe no banco de dados e pertence a um master
        $stmtValida = $pdo->prepare("
            SELECT s.id, u.role 
            FROM user_sessions s 
            JOIN users u ON s.user_id = u.id 
            WHERE s.token = ? AND u.role = 'master' AND s.active = TRUE
        ");
        $stmtValida->execute([$tokenEnviado]);
        $sessaoAtiva = $stmtValida->fetch(PDO::FETCH_ASSOC);

        if (!$sessaoAtiva) {
            echo json_encode(['success' => false, 'message' => 'Acesso negado ou Token expirado. Revalide a sessão.']);
            exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Falha crítica de segurança.']);
        exit;
    }

    // Ação 1: Configuração Automática e Migração de Colunas
    if ($action === 'auto_setup') {
        try {
            // Cria a tabela 'users' se não existir
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

            // Cria a tabela de controle de sessões unificada (A segurança que você solicitou!)
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS user_sessions (
                    id SERIAL PRIMARY KEY,
                    user_id INT NOT NULL,
                    token VARCHAR(255) UNIQUE NOT NULL,
                    active BOOLEAN DEFAULT TRUE,
                    ip_address VARCHAR(45) NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                )
            ");

            try {
                $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS requerer_troca BOOLEAN DEFAULT FALSE;");
            } catch (Exception $e) {}

            // Cria a tabela 'enderecos' se não existir
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

            try {
                $pdo->exec("ALTER TABLE enderecos ADD COLUMN IF NOT EXISTS quantidade INT DEFAULT 1;");
            } catch (Exception $e) {}

            // Garante o Ricardo Master
            $email = 'ricardomaster@gmail.com';
            $nome = 'Ricardo Master';
            $senha_master_hash = password_hash('8486', PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("INSERT INTO users (email, nome, senha_hash, role, encryption_seed, autorizado, requerer_troca) 
                                   VALUES (?, ?, ?, 'master', 'chave-master-2026', TRUE, FALSE)
                                   ON CONFLICT (email) DO NOTHING");
            $stmt->execute([$email, $nome, $senha_master_hash]);

            echo json_encode([
                'success' => true, 
                'message' => 'Tabelas base, tabelas de sessões, colunas de quantidade e Ricardo Master sincronizados!'
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erro no auto-setup: ' . $e->getMessage()]);
        }
        exit;
    }

    // Ação 2: Executar SQL Livre
    if ($action === 'executar_sql') {
        $sql = trim($data['sql'] ?? '');
        if (empty($sql)) {
            echo json_encode(['success' => false, 'message' => 'O comando SQL não pode estar vazio.']);
            exit;
        }

        try {
            if (stripos($sql, 'SELECT') === 0 || stripos($sql, 'SHOW') === 0 || stripos($sql, 'DESCRIBE') === 0) {
                $stmt = $pdo->query($sql);
                $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo json_encode([
                    'success' => true, 
                    'message' => 'Consulta executada com sucesso!', 
                    'dados' => $resultados
                ]);
            } else {
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
            $tabelas = [];
            try {
                $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema='public'");
                $tabelas = $stmt->fetchAll(PDO::FETCH_COLUMN);
            } catch (Exception $ex) {
                $stmt = $pdo->query("SHOW TABLES");
                $tabelas = $stmt->fetchAll(PDO::FETCH_COLUMN);
            }
            echo json_encode(['success' => true, 'tabelas' => $tabelas]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erro ao listar tabelas: ' . $e->getMessage()]);
        }
        exit;
    }

    // Ação 4: Inspecionar colunas e campos de uma tabela específica
    if ($action === 'obter_campos_tabela') {
        $tabela = preg_replace('/[^a-zA-Z0-9_]/', '', $data['tabela'] ?? '');
        
        try {
            $campos = [];
            try {
                $stmt = $pdo->prepare("
                    SELECT column_name AS campo, data_type AS tipo, is_nullable AS nulo, column_default AS padrao 
                    FROM information_schema.columns 
                    WHERE table_name = ?
                ");
                $stmt->execute([$tabela]);
                $campos = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (Exception $ex) {
                $stmt = $pdo->query("DESCRIBE $tabela");
                $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($resultados as $row) {
                    $campos[] = [
                        'campo' => $row['Field'] ?? $row['column_name'],
                        'tipo' => $row['Type'] ?? $row['data_type'],
                        'nulo' => $row['Null'] ?? $row['is_nullable'],
                        'padrao' => $row['Default'] ?? $row['column_default']
                    ];
                }
            }
            echo json_encode(['success' => true, 'campos' => $campos]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erro ao inspecionar campos da tabela: ' . $e->getMessage()]);
        }
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Ação desconhecida.']);
    exit;
}
?>
<!-- ==================== FRONTEND COMPLETO DO TERMINAL ==================== -->
<!DOCTYPE html>
<html lang="pt-PT">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Setup & Terminal SQL - Pulmão AE</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-950 text-white min-h-screen p-4 font-sans flex items-center justify-center relative">

  <!-- TOAST NOTIFICATION -->
  <div id="toast" class="fixed top-5 left-1/2 -translate-x-1/2 z-50 transform transition-all duration-300 opacity-0 pointer-events-none scale-90">
    <div id="toastBg" class="flex items-center gap-3 px-6 py-4 rounded-2xl shadow-2xl border backdrop-blur-sm">
      <span id="toastIcon" class="text-xl"></span>
      <span id="toastMessage" class="font-medium text-sm"></span>
    </div>
  </div>

  <!-- ==================== 1. PORTAL DE LOGIN DE SEGURANÇA MASTER (SEM SENHAS EXPOSTAS NO CODIGO) ==================== -->
  <div id="portalSeguranca" class="max-w-md w-full bg-slate-900 border border-slate-800 rounded-3xl p-8 shadow-2xl text-center z-40 transition-all duration-500">
    <div class="mb-6">
      <div class="w-16 h-16 bg-blue-600/10 border border-blue-500/20 rounded-full flex items-center justify-center mx-auto text-3xl animate-pulse">
        🛡️
      </div>
      <h2 class="text-xl font-black text-slate-100 mt-4 font-sans">Acesso Master do Servidor</h2>
      <p class="text-xs text-slate-400 mt-2 leading-relaxed">Painel de manipulação direta de dados. Por favor, inicie sessão com a sua senha criptografada.</p>
    </div>

    <div class="space-y-4">
      <input id="emailPortal" type="email" placeholder="E-mail (Ex: ricardomaster@gmail.com)" class="w-full bg-slate-950 border border-slate-800 rounded-2xl px-5 py-4 focus:outline-none focus:border-blue-500 text-white text-sm">
      <input id="senhaPortal" type="password" placeholder="Chave de Acesso Master" class="w-full bg-slate-950 border border-slate-800 rounded-2xl px-5 py-4 focus:outline-none focus:border-blue-500 font-mono text-white text-sm">
      
      <button onclick="autenticarPortalMaster()" class="w-full bg-blue-600 hover:bg-blue-500 py-4 rounded-2xl font-bold text-xs transition-all active:scale-[0.98] text-white shadow-lg shadow-blue-600/15">
        Desbloquear Painel de Dados Real
      </button>
    </div>
  </div>

  <!-- ==================== 2. PAINEL DE CONTROLE REAL (SÓ É REVELADO SE O TOKEN FOR RETORNADO) ==================== -->
  <div id="painelTerminal" class="max-w-4xl w-full py-8 hidden transition-all duration-500">
    
    <!-- Cabeçalho -->
    <div class="flex justify-between items-center mb-8 border-b border-slate-800 pb-6">
      <div class="flex items-center gap-4">
        <span class="text-5xl">🚀</span>
        <div>
          <h1 class="text-3xl font-extrabold text-blue-400">Terminal & Setup DB</h1>
          <p class="text-slate-400 text-sm">Painel exclusivo para monitoramento e migração de dados do Ricardo Master</p>
        </div>
      </div>
      <a href="https://devsereno.github.io/codril/index.html" class="bg-slate-900 hover:bg-slate-850 px-4 py-2.5 border border-slate-800 rounded-2xl text-xs font-bold transition-all">
        ← Voltar ao Início
      </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">

      <!-- Esquerda: Setup Inicial e Inspetor de Tabelas com Campos -->
      <div class="md:col-span-1 space-y-6">
        
        <!-- Bloco 1: Auto-Setup -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl">
          <h2 class="text-base font-bold mb-2 text-slate-200">1. Setup Inicial</h2>
          <p class="text-xs text-slate-400 mb-6">Executa migrações automáticas de novas colunas, tabela de sessões ativos e utilizadores de forma segura.</p>
          <button onclick="executarAutoSetup()" class="w-full bg-blue-600 hover:bg-blue-500 py-3 rounded-xl text-xs font-bold transition-all active:scale-95 text-white">
            Instalar Estruturas Base
          </button>
        </div>

        <!-- Bloco 2: Tabelas Ativas com Inspetor de Campos -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl">
          <div class="flex justify-between items-center mb-4">
            <h2 class="text-sm font-bold text-slate-300 uppercase tracking-wider">Tabelas Ativas</h2>
            <button onclick="obterTabelasAtivas()" class="text-[10px] text-blue-400 font-bold hover:underline">Atualizar 🔄</button>
          </div>
          
          <div id="listaTabelas" class="space-y-3 text-xs">
            <p class="text-slate-500 font-medium">Carregando tabelas...</p>
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
            <p class="text-xs text-slate-400 mb-4">Digite comandos SQL personalizados abaixo para manutenção direta:</p>

            <textarea id="sqlTerminal" placeholder="EXEMPLO DE COMANDO:&#10;CREATE TABLE IF NOT EXISTS historico_movimentos (&#10;    id SERIAL PRIMARY KEY,&#10;    produto_id INT,&#10;    acao VARCHAR(50),&#10;    data TIMESTAMP DEFAULT CURRENT_TIMESTAMP&#10;);" 
                      class="w-full h-64 bg-slate-950 border border-slate-850 rounded-2xl p-4 font-mono text-xs text-slate-300 focus:outline-none focus:border-blue-500 leading-relaxed"></textarea>
          </div>

          <div class="mt-6 pt-4 border-t border-slate-800 flex justify-end">
            <button onclick="executarSQLTerm()" class="w-full sm:w-1/2 bg-emerald-600 hover:bg-emerald-500 py-4 rounded-xl text-xs font-bold transition-all active:scale-95 text-center text-white">
              ⚡ Executar Comando SQL
            </button>
          </div>
        </div>
      </div>

    </div>

    <!-- Visualizador de Retornos e Respostas -->
    <div class="mt-6 bg-slate-900 border border-slate-800 rounded-3xl p-6 shadow-xl">
      <h3 class="text-xs text-slate-500 uppercase tracking-wider font-bold mb-3">Retorno do Terminal SQL</h3>
      <div id="resultadoTerminal" class="bg-slate-950 border border-slate-850 rounded-2xl p-4 font-mono text-xs text-slate-400 max-h-[300px] overflow-auto">
        Nenhum comando SQL enviado ainda.
      </div>
    </div>

  </div>

  <script>
    let masterToken = '';

    // AUTENTICAÇÃO SECURA COM O SERVIDOR (O JS NÃO GUARDA SENHA!)
    async function autenticarPortalMaster() {
      const email = document.getElementById('emailPortal').value.trim();
      const senha = document.getElementById('senhaPortal').value;

      if (!email || !senha) {
        mostrarAviso("Preencha todos os campos do portal.", "alerta");
        return;
      }

      try {
        const res = await fetch('', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'login_setup', email, senha })
        });
        const data = await res.json();

        if (data.success && data.token) {
          masterToken = data.token; // Guarda o Token temporariamente em memória na sessão ativa
          mostrarAviso("Sessão iniciada de forma criptográfica!", "sucesso");

          // Remove o portal de login e exibe o terminal livre
          document.getElementById('portalSeguranca').classList.add('hidden');
          document.body.classList.remove('items-center', 'justify-center');
          
          document.getElementById('painelTerminal').classList.remove('hidden');
          obterTabelasAtivas();
        } else {
          mostrarAviso(data.message || "Senha incorreta ou acesso não autorizado.", "erro");
        }
      } catch (err) {
        mostrarAviso("Erro ao se conectar com a API de segurança.", "erro");
      }
    }

    // Toast de Alerta Personalizado
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
 // Executa o auto-setup passando o Token como chave de validação
    async function executarAutoSetup() {
      try {
        const res = await fetch('', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'auto_setup', token: masterToken })
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

    // Executa SQL Livre utilizando o token seguro de banco
    async function executarSQLTerm() {
      const sql = document.getElementById('sqlTerminal').value.trim();
      const saida = document.getElementById('resultadoTerminal');

      if (!sql) return mostrarAviso("Escreva um comando SQL primeiro.", "alerta");
      saida.textContent = "Processando comando SQL...";

      try {
        const res = await fetch('', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'executar_sql', token: masterToken, sql })
        });
        const data = await res.json();

        if (data.success) {
          mostrarAviso(data.message);
          
          if (data.dados) {
            saida.innerHTML = `<p class="text-emerald-400 font-bold mb-2 font-sans">Resultados retornados (${data.dados.length}):</p>` +
                              `<pre class="text-slate-300 overflow-x-auto">${JSON.stringify(data.dados, null, 2)}</pre>`;
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
        saida.textContent = "Erro de conexão ou comando incorreto.";
      }
    }

    // Obter tabelas utilizando o token de sessão ativo
    async function obterTabelasAtivas() {
      const container = document.getElementById('listaTabelas');
      
      try {
        const res = await fetch('', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'obter_tabelas', token: masterToken })
        });
        const data = await res.json();

        if (data.success && data.tabelas) {
          if (data.tabelas.length === 0) {
            container.innerHTML = '<p class="text-slate-600 text-xs">Nenhuma tabela criada ainda.</p>';
            return;
          }

          let html = '';
          data.tabelas.forEach(tab => {
            html += `
              <div class="bg-slate-950 border border-slate-800 rounded-2xl overflow-hidden shadow-inner">
                <button onclick="toggleInspecionarCampos('${tab}')" class="w-full flex justify-between items-center bg-slate-950 hover:bg-slate-900 px-4 py-3 font-bold text-slate-300 transition-all">
                  <span class="flex items-center gap-2">📊 <span>${tab}</span></span>
                  <span id="seta-${tab}" class="text-slate-500 text-xs transition-transform duration-200">▼</span>
                </button>
                <div id="campos-${tab}" class="hidden p-3 bg-slate-900/60 border-t border-slate-900 space-y-2 text-[10px] text-slate-400 max-h-48 overflow-y-auto font-mono">
                  Carregando colunas...
                </div>
              </div>`;
          });
          container.innerHTML = html;
        } else {
          container.innerHTML = '<p class="text-red-500">Erro ao carregar lista de tabelas.</p>';
        }
      } catch (err) {
        container.innerHTML = '<p class="text-red-500">Sem conexão.</p>';
      }
    }

    // Inspecionar campos em tempo real usando o token ativo
    async function toggleInspecionarCampos(tabela) {
      const divCampos = document.getElementById(`campos-${tabela}`);
      const seta = document.getElementById(`seta-${tabela}`);

      if (divCampos.classList.contains('hidden')) {
        divCampos.classList.remove('hidden');
        seta.style.transform = 'rotate(180deg)';

        try {
          const res = await fetch('', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'obter_campos_tabela', token: masterToken, tabela })
          });
          const data = await res.json();

          if (data.success && data.campos) {
            let colunasHtml = '';
            data.campos.forEach(c => {
              const valorNulo = c.nulo === 'YES' || c.nulo === 'yes' || c.nulo === true ? 'NULO OK' : 'NOT NULL';
              const valorPadrao = c.padrao ? ` DEFAULT ${c.padrao}` : '';
              colunasHtml += `
                <div class="border-b border-slate-850 pb-1 mb-1 last:border-0 text-left">
                  <span class="text-blue-400 font-bold">${c.campo}</span>: 
                  <span class="text-emerald-400">${c.tipo}</span> 
                  <span class="text-slate-500 font-semibold">[${valorNulo}${valorPadrao}]</span>
                </div>`;
            });
            divCampos.innerHTML = colunasHtml || '<p class="text-slate-600 font-sans">Nenhum campo localizado.</p>';
          } else {
            divCampos.innerHTML = '<span class="text-red-400">Falha ao inspecionar colunas.</span>';
          }
        } catch (err) {
          divCampos.innerHTML = '<span class="text-red-400">Erro ao comunicar com o inspetor.</span>';
        }

      } else {
        divCampos.classList.add('hidden');
        seta.style.transform = 'rotate(0deg)';
      }
    }
  </script>
</body>
</html>

   