
// js/api.js - Comunicação com o servidor PHP
const API_BASE = 'https://codril.xo.je/api/';   // ← Mude se seu domínio mudar

export async function apiRequest(endpoint, method = 'GET', body = null) {
  const options = {
    method: method,
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json'
    }
  };

  if (body) {
    options.body = JSON.stringify(body);
  }

  try {
    const response = await fetch(API_BASE + endpoint, options);
    
    if (!response.ok) {
      const errorData = await response.json().catch(() => ({}));
      throw new Error(errorData.message || `Erro ${response.status}`);
    }

    return await response.json();
  } catch (error) {
    console.error('API Error:', error);
    throw error;
  }
}

// Funções específicas
export const AuthAPI = {
  login: (email, senha) => apiRequest('login.php', 'POST', { email, senha }),
};

export const PulmaoAPI = {
  salvar: (dados) => apiRequest('salvar-produto.php', 'POST', dados),
};
