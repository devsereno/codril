// js/crypto.js - Criptografia no navegador
let encryptionKey = null;

export function setEncryptionKey(seed) {
    encryptionKey = seed;
    console.log("🔑 Chave de criptografia configurada");
}

export async function encryptData(data) {
    if (!encryptionKey) throw new Error("Chave de criptografia não definida");
    
    const encoder = new TextEncoder();
    const iv = crypto.getRandomValues(new Uint8Array(12));
    
    const encodedData = encoder.encode(JSON.stringify(data));
    
    const keyMaterial = await crypto.subtle.importKey(
        "raw", 
        encoder.encode(encryptionKey),
        { name: "PBKDF2" },
        false,
        ["deriveBits", "deriveKey"]
    );

    const key = await crypto.subtle.deriveKey(
        { name: "PBKDF2", salt: encoder.encode("pulmao-salt"), iterations: 100000, hash: "SHA-256" },
        keyMaterial,
        { name: "AES-GCM", length: 256 },
        false, );

    const encrypted = await
