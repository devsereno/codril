// js/api.js
const API_BASE = 'https://codril.onrender.com/api/';

export async function apiRequest(endpoint, method = 'POST', body = null) {
  try {
    const options = {
      method: method,
      headers: {
        'Content-Type': 'application/json',
      }
    };

    if (body) {
      options.body = JSON.stringify(body);
    }

    const response = await fetch(API_BASE + endpoint, options);
    return await response.json();
  } catch (error) {
    console.error('Erro na API:', error);
    throw new Error('Erro de conexão com o servidor');
  }
}
