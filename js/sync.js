
// js/sync.js - Sincronização automática (Offline → Online)

import { apiRequest } from './api.js';
import { openDB } from './storage.js';

export async function sincronizarPendentes() {
  if (!navigator.onLine) return;

  const db = await openDB();
  const queue = await db.getAll("syncQueue");

  console.log(`🔄 Sincronizando ${queue.length} itens pendentes...`);

  for (const item of queue) {
    try {
      const response = await apiRequest('salvar-produto.php', 'POST', item.dados);
      
      if (response.success) {
        // Remove da fila se deu certo
        const tx = db.transaction("syncQueue", "readwrite");
        await tx.store.delete(item.tempId);
        await tx.done;
        
        console.log(`✅ Item sincronizado: ${item.dados.codigo_produto}`);
      }
    } catch (error) {
      console.warn("Falha ao sincronizar item, tentará novamente depois", error);
      break; // Para não sobrecarregar
    }
  }
}

// Detecta quando voltar a ter internet
window.addEventListener('online', () => {
  console.log("🌐 Conexão restabelecida - Iniciando sincronização...");
  sincronizarPendentes();
});

// Verifica periodicamente
setInterval(() => {
  if (navigator.onLine) sincronizarPendentes();
}, 30000); // a cada 30 segundos
