// js/armazem.js

// Função principal de cadastro
export async function cadastrarProduto(dados) {
  try {
    const res = await fetch('https://codril.onrender.com/api/salvar-produto.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(dados)
    });

    const resultado = await res.json();
    return resultado;
  } catch (error) {
    console.error("Erro ao salvar:", error);
    throw new Error("Erro de conexão com o servidor");
  }
}

// Função para listar (por enquanto local)
export async function listarProdutos() {
  // Podemos melhorar depois com IndexedDB + sincronização
  return [];
}

// Função de logout
export function logout() {
  localStorage.clear();
  window.location.href = 'login.html';
}
