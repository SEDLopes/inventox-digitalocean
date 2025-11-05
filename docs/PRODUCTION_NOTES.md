# Notas de Produção - InventoX

Este documento contém notas importantes para deploy em produção.

## ⚠️ Avisos Importantes

### Tailwind CSS CDN

**AVISO**: O frontend atual usa o CDN do Tailwind CSS (`cdn.tailwindcss.com`), que **não deve ser usado em produção**.

#### Para Produção:

1. **Instalar Tailwind CSS via npm**:
```bash
npm install -D tailwindcss
npx tailwindcss init
```

2. **Configurar `tailwind.config.js`**:
```js
module.exports = {
  content: ["./frontend/**/*.{html,js}"],
  theme: {
    extend: {},
  },
  plugins: [],
}
```

3. **Adicionar ao CSS** (`styles.css`):
```css
@tailwind base;
@tailwind components;
@tailwind utilities;
```

4. **Compilar CSS**:
```bash
npx tailwindcss -i ./frontend/styles.css -o ./frontend/dist/output.css --minify
```

5. **Substituir no HTML**:
```html
<!-- Remover -->
<script src="https://cdn.tailwindcss.com"></script>

<!-- Adicionar -->
<link rel="stylesheet" href="dist/output.css">
```

### ZXing Library

A biblioteca ZXing é carregada via CDN. Para produção, considere:

1. **Download local**: Baixar e servir localmente
2. **Bundle**: Incluir no processo de build
3. **Versão específica**: Usar versão fixa em vez de `@latest`

### HTTPS em Produção

O scanner de código de barras **requer HTTPS** em produção (requisito do navegador para acesso à câmara).

## 🔒 Segurança

### Variáveis de Ambiente

- Nunca commitar o ficheiro `.env`
- Usar senhas fortes em produção
- Rotacionar credenciais regularmente

### CORS

Ajustar configurações CORS no `api/.htaccess` conforme necessário:

```apache
Header set Access-Control-Allow-Origin "https://seu-dominio.com"
```

### Senhas

- Altere todas as senhas padrão
- Use `password_hash` para todas as senhas
- Implemente políticas de senha forte

## 🚀 Deploy

### Opções de Deploy

1. **Docker Compose** (Produção)
   - Usar docker-compose.prod.yml
   - Configurar volumes persistentes
   - Usar reverse proxy (nginx)

2. **Servidor Tradicional**
   - Apache/Nginx + PHP-FPM
   - MySQL em servidor separado
   - Configurar SSL/TLS

3. **Cloud (AWS/GCP/Azure)**
   - Containers em ECS/Kubernetes
   - RDS para MySQL
   - Load balancer com SSL

## 📝 Checklist de Produção

- [ ] Remover CDN do Tailwind CSS
- [ ] Compilar Tailwind CSS localmente
- [ ] Configurar HTTPS/SSL
- [ ] Alterar todas as senhas padrão
- [ ] Configurar CORS corretamente
- [ ] Habilitar logs de erro (desabilitar display_errors)
- [ ] Configurar backup automático da base de dados
- [ ] Implementar rate limiting
- [ ] Configurar monitoramento
- [ ] Testar scanner em HTTPS

## 🔧 Configurações Recomendadas

### PHP (produção)

```php
display_errors = Off
error_reporting = E_ALL
log_errors = On
error_log = /var/log/php_errors.log
```

### MySQL

- Configurar backups automáticos
- Usar replicação se necessário
- Monitorar performance

### Apache/Nginx

- Habilitar mod_rewrite
- Configurar SSL
- Limitar tamanho de upload
- Configurar cache de estáticos

