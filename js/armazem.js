// js/armazem.js - Lógica principal do Pulmão

import { salvarLocal, listarTodos, adicionarNaFilaSync } from './storage.js';
import { PulmaoAPI } from './api.js';
import { sincronizarPendentes } from './sync.js';

export async function cadastrarProduto(dados) {
  const { tipo, endereco, codigo_produto, lote, descricao } = dados;

  if (!tipo || !endereco || !codigo_produto || !lote) {
    throw new Error("Tipo, Endereço, Código e Lote são obrigatórios");
  }

  const registro = {
    id: `\( {tipo}- \){endereco}-\( {codigo_produto}- \){lote}`,
    tipo,
    endereco,
    codigo_produto,
    lote,
    descricao: descricao || "",
    data: new Date().toISOString(),
    user: JSON.parse(localStorage.getItem('currentUser'))?.email
  };

  // 1. Sempre salva local primeiro (funciona offline)
  await salvarLocal(registro);

  // 2. Tenta enviar para o servidor
  if (navigator.onLine) {
    try {
      const response = await PulmaoAPI.salvar(registro);
      if (response.success) {
        console.log("✅ Salvo no servidor");
      } else {
        console.warn("Servidor rejeitou:", response.message);
      }
    } catch (e) {
      console.warn("Falha ao salvar no servidor, ficará na fila");
      await adicionarNaFilaSync(registro);
    }
  } else {
    await adicionarNaFilaSync(registro);
    console.log("📴 Salvo localmente (offline)");
  }

  // Inicia sincronização em background
  setTimeout(sincronizarPendentes, 1000);

  return registro;
}

// Função para listar todos os itens
export async function listarProdutos() {
  return await listarTodos();
}

// Logout
export function logout() {
  localStorage.clear();
  window.location.href = 'login.html';
}
