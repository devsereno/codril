<!DOCTYPE html>
<html lang="pt" class="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gestão de Divergências</title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 dark:bg-[#020617] text-slate-900 dark:text-slate-100 min-h-screen transition-colors">

  <!-- Barra Superior -->
  <nav class="w-full bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 p-4 mb-6 sticky top-0 z-50 no-print">
    <div class="max-w-2xl mx-auto flex justify-between items-center">
      <div class="flex items-center gap-3">
        <div id="avatarUser" class="w-9 h-9 bg-blue-600 rounded-full flex items-center justify-center text-white font-bold text-lg"></div>
        <div>
          <h1 class="text-[10px] uppercase font-bold text-slate-400">Logado como:</h1>
          <p id="nomeExibicao" class="font-semibold text-slate-900 dark:text-white">Carregando...</p>
        </div>
      </div>
      <button onclick="logout()" class="text-red-500 text-xs font-bold hover:underline">Sair</button>
    </div>
  </nav>

  <div class="max-w-2xl mx-auto p-4 no-print">
    <!-- ... seu header e filtros ... -->

    <div id="gridCards" class="grid grid-cols-1 gap-4"></div>
    <div id="paginacao" class="flex justify-center gap-2 mt-6"></div>
  </div>

  <!-- Modal de Confirmação -->
  <div id="modalConfirmacao" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
    <div class="bg-white dark:bg-slate-900 p-6 rounded-3xl w-full max-w-sm text-center shadow-2xl">
      <h3 class="text-lg font-black mb-4">Deseja realmente deletar esse produto?</h3>
      <div class="flex gap-4 justify-center">
        <button onclick="confirmarDeletarBackend()" id="btnConfirmarDelete" 
                class="bg-red-600 text-white px-8 py-2 rounded-xl font-bold">SIM, DELETAR</button>
        <button onclick="document.getElementById('modalConfirmacao').classList.add('hidden')" 
                class="bg-slate-200 dark:bg-slate-800 px-8 py-2 rounded-xl font-bold">CANCELAR</button>
      </div>
    </div>
  </div>

  <script>
    let todosOsDados = [];
    let paginaAtual = 1;
    let idParaRemover = null;

    function verificarLogin() {
      if (!localStorage.getItem('currentUser')) window.location.href = 'login.html';
    }

    async function carregarDados() {
      try {
        const res = await fetch("https://codril.onrender.com/api/salvar-produto.php");
        const data = await res.json();
        todosOsDados = data.itens || [];
        renderizar();
      } catch (err) {
        document.getElementById('infoStatus').innerText = "Erro ao carregar.";
      }
    }

    function renderizar() {
      // ... seu código de renderizar cards (mantido) ...
      // Apenas certifique-se de ter o onclick correto:
      // onclick="confirmarDelete(${item.id})"
    }

    function confirmarDelete(id) {
      idParaRemover = id;
      document.getElementById('modalConfirmacao').classList.remove('hidden');
    }

    async function confirmarDeletarBackend() {
      const btn = document.getElementById('btnConfirmarDelete');
      const textoOriginal = btn.textContent;

      btn.disabled = true;
      btn.innerHTML = `<span class="animate-spin inline-block w-4 h-4 border-2 border-white border-t-transparent rounded-full mr-2"></span> Deletando...`;

      try {
        const res = await fetch('https://codril.onrender.com/api/deletar-produto.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id: idParaRemover })
        });

        const data = await res.json();

        if (data.success) {
          alert("✅ Produto deletado com sucesso!");
          todosOsDados = todosOsDados.filter(i => i.id != idParaRemover);
          renderizar();
        } else {
          alert("❌ " + (data.message || "Não foi possível deletar"));
        }
      } catch (e) {
        alert("❌ Erro de conexão com o servidor.");
      } finally {
        btn.disabled = false;
        btn.innerHTML = textoOriginal;
        document.getElementById('modalConfirmacao').classList.add('hidden');
      }
    }

    function logout() {
      localStorage.clear();
      window.location.href = 'login.html';
    }

    window.onload = function() {
      verificarLogin();
      carregarDados();
    };
  </script>
</body>
</html>
