# 📋 Instruções para Testar Localmente - InventoX

**Data:** 2024-11-09  
**Status:** ✅ Ambiente Local Funcionando

---

## ✅ Ambiente Configurado com Sucesso!

### Status Atual
- ✅ Docker em execução
- ✅ Serviços iniciados (web na porta 8080, MySQL na porta 3307)
- ✅ Base de dados inicializada com todas as tabelas
- ✅ Todas as colunas criadas corretamente

---

## 🚀 Como Testar

### 1. **Acessar Aplicação**

Abra no navegador:
```
http://localhost:8080/frontend/
```

### 2. **Fazer Login**

- **Username:** `admin`
- **Password:** `admin123`

### 3. **Testar Funcionalidades**

Após login, testar cada funcionalidade:

#### ✅ **Criar Empresa**
1. Ir para aba "Empresas"
2. Clicar em "Criar Empresa"
3. Preencher:
   - Nome: "Empresa Teste"
   - Código: "TEST001"
   - Endereço: "Rua Teste"
   - Telefone: "123456789"
   - Email: "teste@teste.com"
   - NIF: "123456789"
4. Clicar em "Criar"
5. **Verificar:** Empresa aparece na lista

#### ✅ **Criar Armazém**
1. Ir para aba "Armazéns"
2. Clicar em "Criar Armazém"
3. Preencher:
   - Empresa: Selecionar empresa criada
   - Nome: "Armazém Teste"
   - Código: "AR001"
   - Endereço: "Rua Armazém"
   - Localização: "Zona Industrial"
4. Clicar em "Criar"
5. **Verificar:** Armazém aparece na lista

#### ✅ **Criar Artigo**
1. Ir para aba "Artigos"
2. Clicar em "Criar Artigo"
3. Preencher:
   - Código de Barras: "1234567890123"
   - Nome: "Artigo Teste"
   - Descrição: "Descrição do artigo"
   - Categoria: Selecionar categoria
   - Quantidade: 10
   - Preço Unitário: 5.50
4. Clicar em "Criar"
5. **Verificar:** Artigo aparece na lista

#### ✅ **Criar Sessão**
1. Ir para aba "Sessões"
2. Clicar em "Criar Sessão"
3. Preencher:
   - Nome: "Sessão Teste"
   - Descrição: "Descrição da sessão"
   - Empresa: Selecionar empresa
   - Armazém: Selecionar armazém
4. Clicar em "Criar"
5. **Verificar:** Sessão aparece na lista

#### ✅ **Criar Utilizador**
1. Ir para aba "Utilizadores"
2. Clicar em "Criar Utilizador"
3. Preencher:
   - Username: "teste"
   - Email: "teste@teste.com"
   - Password: "teste123"
   - Role: "operador"
4. Clicar em "Criar"
5. **Verificar:** Utilizador aparece na lista

---

## 🔍 Verificar Logs

Se encontrar erros, verificar logs:

```bash
docker-compose logs -f web
```

### Erros Comuns

#### 1. **Erro 401 (Unauthorized)**
**Causa:** Sessão não está sendo mantida  
**Solução:**
- Verificar se cookies estão sendo enviados (DevTools → Application → Cookies)
- Verificar se `credentials: 'include'` está presente no frontend
- Verificar logs do servidor

#### 2. **Erro 500 (Internal Server Error)**
**Causa:** Erro no servidor  
**Solução:**
- Verificar logs: `docker-compose logs web`
- Verificar se todas as tabelas existem
- Verificar se todas as colunas existem

#### 3. **Erro de Validação**
**Causa:** Dados inválidos  
**Solução:**
- Verificar se todos os campos obrigatórios estão preenchidos
- Verificar formato de email
- Verificar se empresa/armazém existem antes de criar sessão

---

## 📊 Verificar Base de Dados

### Verificar Registos Criados

```bash
# Ver empresas
docker-compose exec db mysql -u inventox -pinventox123 inventox -e "SELECT id, name, code FROM companies;"

# Ver armazéns
docker-compose exec db mysql -u inventox -pinventox123 inventox -e "SELECT id, name, code, company_id FROM warehouses;"

# Ver artigos
docker-compose exec db mysql -u inventox -pinventox123 inventox -e "SELECT id, barcode, name FROM items LIMIT 5;"

# Ver sessões
docker-compose exec db mysql -u inventox -pinventox123 inventox -e "SELECT id, name, company_id, warehouse_id FROM inventory_sessions;"

# Ver utilizadores
docker-compose exec db mysql -u inventox -pinventox123 inventox -e "SELECT id, username, role FROM users;"
```

---

## ✅ Checklist Completo

### Funcionalidades Principais
- [ ] Login funciona e sessão é mantida
- [ ] Criar empresa funciona
- [ ] Criar armazém funciona
- [ ] Criar artigo funciona
- [ ] Criar sessão funciona
- [ ] Criar utilizador funciona
- [ ] Listar registos funciona
- [ ] Editar registos funciona
- [ ] Eliminar registos funciona (se aplicável)

### Funcionalidades Secundárias
- [ ] Importar artigos (CSV/XLSX) funciona
- [ ] Exportar sessões funciona
- [ ] Estatísticas funcionam
- [ ] Histórico de movimentações funciona
- [ ] Scanner de código de barras funciona

---

## 🎯 Quando Tudo Estiver Funcionando

Após testar todas as funcionalidades e confirmar que tudo funciona:

1. **Documentar problemas encontrados** (se houver)
2. **Corrigir problemas** localmente
3. **Testar novamente** para confirmar correções
4. **Fazer commit e push:**
   ```bash
   git add .
   git commit -m "Correções finais após testes locais"
   git push origin main
   ```
5. **Fazer deploy** para produção

---

## 📝 Notas

- **Porta:** 8080 (para evitar conflito com outros serviços)
- **Hot Reload:** Volumes montados para api e frontend (alterações refletem imediatamente)
- **Base de Dados:** Inicializada automaticamente
- **Login:** admin / admin123

---

**Última Atualização:** 2024-11-09

