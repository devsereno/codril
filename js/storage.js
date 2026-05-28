// js/storage.js - Banco Local + Sincronização

let dbInstance;

async function initDB() {
  return new Promise((resolve, reject) => {
    const request = indexedDB.open('PulmaoDB', 1);

    request.onupgradeneeded = (e) => {
      const db = e.target.result;
      if (!db.objectStoreNames.contains('itens')) {
        db.createObjectStore('itens', { keyPath: 'id', autoIncrement: true });
      }
      if (!db.objectStoreNames.contains('syncQueue')) {
        db.createObjectStore('syncQueue', { keyPath: 'id', autoIncrement: true });
      }
    };

    request.onsuccess = (e) => {
      dbInstance = e.target.result;
      resolve(dbInstance);
    };
    request.onerror = (e) => reject(e.target.error);
  });
}

// Salvar localmente (funciona offline)
export async function salvarLocal(dados) {
  await initDB();
  return new Promise((resolve, reject) => {
    const tx = dbInstance.transaction(['itens', 'syncQueue'], 'readwrite');
    tx.objectStore('itens').add(dados);
    tx.objectStore('syncQueue').add({ ...dados, status: 'pending' });
    tx.oncomplete = () => resolve(true);
    tx.onerror = () => reject(tx.error);
  });
}

// Listar itens locais
export async function listarLocais() {
  await initDB();
  return new Promise((resolve) => {
    const tx = dbInstance.transaction('itens', 'readonly');
    const store = tx.objectStore('itens');
    const request = store.getAll();
    request.onsuccess = () => resolve(request.result);
  });
}
