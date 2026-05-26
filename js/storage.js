// js/storage.js - Banco Local (IndexedDB) + Criptografia
import { encryptData, decryptData } from './crypto.js';

const DB_NAME = "PulmaoDB";
const DB_VERSION = 1;

let dbInstance = null;

export async function openDB() {
  return new Promise((resolve, reject) => {
    if (dbInstance) return resolve(dbInstance);

    const request = indexedDB.open(DB_NAME, DB_VERSION);

    request.onupgradeneeded = (event) => {
      const db = event.target.result;
      
      if (!db.objectStoreNames.contains("enderecos")) {
        db.createObjectStore("enderecos", { keyPath: "id" });
      }
      
      if (!db.objectStoreNames.contains("syncQueue")) {
        db.createObjectStore("syncQueue", { keyPath: "tempId" });
      }
    };

    request.onsuccess = () => {
      dbInstance = request.result;
      resolve(dbInstance);
    };

    request.onerror = () => reject(request.error);
  });
}

// Salvar no banco local (criptografado)
export async function salvarLocal(registro) {
  const db = await openDB();
  const encrypted = await encryptData(registro);

  const tx = db.transaction("enderecos", "readwrite");
  await tx.store.put({ id: registro.id, encrypted });
  await tx.done;

  return registro;
}

// Buscar do banco local
export async function buscarLocal(id) {
  const db = await openDB();
  const record = await db.get("enderecos", id);
  if (!record) return null;
  return await decryptData(record.encrypted);
}

// Listar todos
export async function listarTodos() {
  const db = await openDB();
  const records = await db.getAll("enderecos");
  const result = [];

  for (const record of records) {
    try {
      const data = await decryptData(record.encrypted);
      result.push(data);
    } catch (e) {
      console.warn("Erro ao descriptografar registro", record.id);
    }
  }
  return result;
}

// Adicionar na fila de sincronização
export async function adicionarNaFilaSync(dados) {
  const db = await openDB();
  const item = {
    tempId: 'sync_' + Date.now(),
    dados: dados,
    data: new Date().toISOString()
  };

  const tx = db.transaction("syncQueue", "readwrite");
  await tx.store.put(item);
  await tx.done;
}
