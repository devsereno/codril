<!DOCTYPE html>
<html lang="pt-PT" class="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Gestão de Divergências</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <style>
    @media print {
      .no-print { display: none !important; }
      #tabelaImpressao { display: table !important; width: 100%; border-collapse: collapse; }
      #tabelaImpressao th, #tabelaImpressao td { border: 1px solid #000; padding: 8px; text-align: center; }
      #tabelaImpressao tr:nth-child(even) { background-color: #f2f2f2 !important; }
      body { background: white !important; color: black !important; }
    }
  </style>
</head>
<body class="bg-slate-50 dark:bg-[#020617] text-slate-900 dark:text-slate-100 min-h-screen transition-colors">

  <!-- Barra de Perfil -->
  <nav class="w-full bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 px-4 py-2 mb-4 flex justify-between items-center no-print">
    <div class="flex items-center gap-2">
      <div id="avatarUser" class="w-8 h-8 rounded-full bg-blue-600 flex items-center justify-center text-white font-bold text-xs">U</div>
      <span id="nomeExibicao" class="text-xs font-semibold">Carregando...</span>
    </div>
    <button onclick="logout()" class="text-[10px] text-red-500 font-bold uppercase hover:underline">Sair</button>
  </nav>

  <div class="max-w-2xl mx-auto p-4 no-print">
    <header class="flex justify-between items-center mb-6">
      <div>
        <h1 class="text-xl font-black text-blue-600 dark:text-blue-500">Divergências</h1>
        <p id="infoStatus" class="text-xs opacity-60">Carregando...</p>
      </div>
      <div class="flex gap-2">
        <button onclick="document.documentElement.classList.toggle('dark')" class="p-2 bg-slate-200 dark:bg-slate-800 rounded-lg">🌓</button>
        <a href="index.html" class="bg-slate-800 dark:bg-slate-200 text-white dark:text-black px-4 py-2 rounded-lg font-bold text-xs">Nova</a>
        <button onclick="window.print()" class="bg-blue-600 text-white px-4 py-2 rounded-lg font-bold text-xs">Imprimir</button>
      </div>
    </header>

    <div class="space-y-4 mb-6">
      <input type="text" id="filtro" oninput="paginaAtual=1; renderizar()" placeholder="🔍 Buscar SKU, Lote ou Endereço..." class="w-full p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-sm">
      <select id="itensPorPagina" onchange="paginaAtual=1; renderizar()" class="w-full p-4 rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 text-sm">
        <option value="5">5 por pág.</option>
        <option value="10" selected>10 por pág.</option>
        <option value="20">20 por pág.</option>
      </select>
    </div>

    <div id="gridCards" class="grid grid-cols-1 gap-4"></div>
    <div id="paginacao" class="flex justify-center gap-2 mt-6"></div>
  </div>

  <div id="modalConfirmacao" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4">
    <div class="bg-white dark:bg-slate-900 p-6 rounded-3xl w-full max-w-sm text-center shadow-2xl">
      <h3 class="text-lg font-black mb-4">Deseja realmente deletar?</h3>
      <div class="flex gap-4 justify-center">
        <button id="btnSim" class="bg-red-600 text-white px-8 py-2 rounded-xl font-bold">SIM</button>
        <button onclick="document.getElementById('modalConfirmacao').classList.add('hidden')" class="bg-slate-200 dark:bg-slate-800 px-8 py-2 rounded-xl font-bold">NÃO</button>
      </div>
    </div>
  </div>

  <table id="tabelaImpressao" class="hidden">
    <thead><tr class="bg-gray-200"><th>Endereço</th><th>SKU</th><th>Lote</th><th>Qtd</th><th>Status</th></tr></thead>
    <tbody id="corpoTabelaImpressao"></tbody>
  </table>

  <script>
    // Recupera o nome do usuário salvo no localStorage durante o Login
    const nomeUser = localStorage.getItem('nomeUsuario') || 'Usuário';
    document.getElementById('nomeExibicao').innerText = nomeUser;
    document.getElementById('avatarUser').innerText = nomeUser.charAt(0).toUpperCase();

    function logout() {
      // Limpa os dados de sessão
      localStorage.removeItem('nomeUsuario');
      localStorage.removeItem('token');
      window.location.href = 'login.html';
    }

    let todosOsDados = [];
    let paginaAtual = 1;
    let idParaRemover = null;

    async function carregarDados() {
      try {
        const response = await fetch("https://codril.onrender.com/api/salvar-produto.php");
        const data = await response.json();
        todosOsDados = data.itens || [];
        renderizar();
      } catch (err) { document.getElementById('infoStatus').innerText = "Erro ao carregar dados."; }
    }

    function renderizar() {
      const filtro = document.getElementById('filtro').value.toLowerCase();
      const limite = parseInt(document.getElementById('itensPorPagina').value);
      const filtrados = todosOsDados.filter(i => 
        (i.endereco?.toLowerCase().includes(filtro)) || (i.codigo_produto?.toLowerCase().includes(filtro)) || (i.lote?.toLowerCase().includes(filtro))
      );
      
      const totalPaginas = Math.ceil(filtrados.length / limite);
      const paginados = filtrados.slice((paginaAtual - 1) * limite, paginaAtual * limite);

      document.getElementById('gridCards').innerHTML = paginados.map(item => {
        const status = (item.status_estoque || '').toUpperCase();
        const isFaltando = status.includes('FALTANDO');
        const colorClass = isFaltando ? 'bg-red-100 dark:bg-red-900/30 text-red-600 dark:text-red-400' : 'bg-green-100 dark:bg-green-900/30 text-green-600 dark:text-green-500';

        return `
          <div class="bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-5 rounded-3xl shadow-sm transition-all" id="card-${item.id}">
            <div class="flex justify-between items-start mb-4">
              <h2 class="text-lg font-black dark:text-white">Endereço: ${item.endereco}</h2>
              <button onclick="idParaRemover='${item.id}'; document.getElementById('modalConfirmacao').classList.remove('hidden')" class="text-slate-400 hover:text-red-500 text-xl">🗑️</button>
            </div>
            <div class="flex justify-between items-center mb-2">
              <p class="text-xs opacity-70">SKU: <span class="font-bold text-slate-900 dark:text-white">${item.codigo_produto}</span></p>
              <span class="text-[10px] uppercase font-bold px-2 py-0.5 rounded-full ${colorClass}">${status || 'NORMAL'}</span>
            </div>
            <div class="grid grid-cols-2 gap-2 text-xs opacity-80 mt-2">
              <p>Lote: <span class="font-bold text-slate-900 dark:text-white">${item.lote}</span></p>
              <p>Qtd: <span class="font-bold text-slate-900 dark:text-white">${item.quantidade}</span></p>
            </div>
          </div>
        `;
      }).join('');

      document.getElementById('corpoTabelaImpressao').innerHTML = filtrados.map(item => `
        <tr><td>${item.endereco}</td><td>${item.codigo_produto}</td><td>${item.lote}</td><td>${item.quantidade}</td><td>${item.status_estoque}</td></tr>
      `).join('');

      document.getElementById('infoStatus').innerText = `Total: ${filtrados.length} itens`;
      document.getElementById('paginacao').innerHTML = Array.from({length: totalPaginas}, (_, i) => i + 1).map(p => `
        <button onclick="paginaAtual=${p}; renderizar()" class="px-4 py-2 rounded-xl text-xs font-bold ${paginaAtual === p ? 'bg-blue-600 text-white' : 'bg-slate-200 dark:bg-slate-800'}">${p}</button>
      `).join('');
    }

    document.getElementById('btnSim').onclick = () => {
      const card = document.getElementById(`card-${idParaRemover}`);
      card.classList.add('opacity-0', 'scale-90', 'transition-all', 'duration-500');
      setTimeout(() => {
        todosOsDados = todosOsDados.filter(i => i.id !== idParaRemover);
        renderizar();
        document.getElementById('modalConfirmacao').classList.add('hidden');
      }, 500);
    };

    carregarDados();
  </script>
</body>
</html>
