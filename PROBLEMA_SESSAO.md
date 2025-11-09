# 🔍 Problema de Sessão - Diagnóstico

**Data:** 2024-11-09  
**Status:** ⚠️ Em Investigação

---

## 📊 Situação Atual

### ✅ O Que Está Funcionando

1. **Login funciona** - Retorna sucesso e cria cookie PHPSESSID
2. **Sessão é criada** - Arquivo de sessão existe em `/tmp/php_sessions/`
3. **Dados na sessão** - Sessão contém `user_id`, `username`, `email`, `role`
4. **Curl funciona** - Quando usamos curl com cookie, a sessão é lida corretamente

### ❌ O Que Não Está Funcionando

1. **Navegador não mantém sessão** - Após login, requisições subsequentes retornam 401
2. **Cookie não é enviado** - Navegador não está enviando cookie PHPSESSID nas requisições

---

## 🔍 Diagnóstico

### Teste com Curl (Funciona ✅)

```bash
# 1. Fazer login
curl -X POST "http://localhost:8080/api/login.php" \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123"}' \
  -c /tmp/cookies.txt

# 2. Verificar sessão
curl "http://localhost:8080/api/debug_session_local.php" \
  -b /tmp/cookies.txt

# Resultado: ✅ Sessão funciona, dados presentes
```

### Teste no Navegador (Não Funciona ❌)

1. Acessar: http://localhost:8080/frontend/
2. Fazer login: admin / admin123
3. Verificar cookies no DevTools (Application → Cookies)
4. Acessar: http://localhost:8080/api/debug_session_local.php
5. Resultado: ❌ Sessão vazia ou cookie não enviado

---

## 🎯 Possíveis Causas

### 1. **Cookie HttpOnly não é enviado pelo navegador**
- **Causa:** Cookie está marcado como HttpOnly, mas navegador não está enviando
- **Solução:** Verificar se `credentials: 'include'` está presente em todas as requisições fetch

### 2. **Cookie SameSite=Lax bloqueia requisições**
- **Causa:** SameSite=Lax pode bloquear cookies em algumas situações
- **Solução:** Verificar se requisições são "same-site"

### 3. **Cookie não está sendo salvo pelo navegador**
- **Causa:** Navegador não está salvando o cookie após login
- **Solução:** Verificar configurações de cookies do navegador

### 4. **Cookie está sendo criado mas não é enviado**
- **Causa:** Cookie está sendo criado, mas navegador não está enviando nas requisições subsequentes
- **Solução:** Verificar se `credentials: 'include'` está presente

---

## 🧪 Testes a Realizar

### 1. **Verificar Cookies no Navegador**

1. Abrir DevTools (F12)
2. Ir para Application → Cookies → http://localhost:8080
3. Verificar se cookie PHPSESSID existe
4. Verificar se cookie tem HttpOnly marcado
5. Verificar se cookie tem SameSite=Lax

### 2. **Verificar Requisições no Network**

1. Abrir DevTools → Network
2. Fazer login
3. Verificar requisição de login:
   - Ver se Set-Cookie está presente na resposta
   - Ver se cookie PHPSESSID está sendo criado
4. Verificar requisições subsequentes:
   - Ver se Cookie header está presente
   - Ver se PHPSESSID está sendo enviado

### 3. **Testar Endpoint de Debug**

1. Após login, acessar: http://localhost:8080/api/debug_session_local.php
2. Verificar resposta JSON:
   - `cookies_received` - Ver se PHPSESSID está presente
   - `session_data` - Ver se dados da sessão estão presentes
   - `session_id` - Ver se ID da sessão corresponde ao cookie

---

## 🔧 Soluções Possíveis

### Solução 1: Verificar `credentials: 'include'`

Garantir que todas as requisições fetch usam `credentials: 'include'`:

```javascript
fetch(url, {
    credentials: 'include'  // IMPORTANTE
})
```

### Solução 2: Verificar Configuração de Cookie

Garantir que cookie não está sendo bloqueado:

```php
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');  // Para HTTP
ini_set('session.cookie_secure', '0');  // Para HTTP
```

### Solução 3: Verificar CORS

Se houver problemas de CORS, adicionar headers:

```php
header('Access-Control-Allow-Origin: http://localhost:8080');
header('Access-Control-Allow-Credentials: true');
```

---

## 📝 Próximos Passos

1. **Testar no navegador** e verificar cookies no DevTools
2. **Verificar requisições** no Network tab
3. **Testar endpoint de debug** após login
4. **Comparar comportamento** entre curl e navegador
5. **Aplicar correções** baseadas nos resultados

---

**Última Atualização:** 2024-11-09

