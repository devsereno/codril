// js/storage.js - IndexedDB + Sincronização

let db;

export function initDB() {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open('PulmaoDB', 1);

    request.onupgradeneeded = (event) => {
      db = event.target.result;
      if (!db.objectStoreNames.contains('enderecos')) {
        db.createObjectStore('enderecos', { keyPath: 'id', autoIncrement: true });
      }
      if (!db.objectStoreNames.contains('syncQueue')) {
        db.createObjectStore('syncQueue', { keyPath: 'id', autoIncrement: true });
      }
    };

    request.onsuccess = (event) => {
      db = event.target.result;
      resolve(db);
    };

    request.onerror = (event) => reject(event.target.error);
  });
}

// Salva localmente e adiciona na fila de sincronização
export async function salvarLocal(dados) {
  await initDB();
  return new Promise((resolve, reject) => {
    const transaction = db.transaction(['enderecos', 'syncQueue'], 'readwrite');
    const store = transaction.objectStore('enderecos');
    const queue = transaction.objectStore('syncQueue');

    const request = store.add(dados);
    request.onsuccess = () => {
      queue.add({ ...dados, syncStatus: 'pending', createdAt: new Date() });
      resolve(true);
    };
    request.onerror = () => reject(request.error);
  });
}
