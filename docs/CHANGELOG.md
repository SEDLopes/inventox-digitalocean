# Changelog - InventoX

Todas as mudanças notáveis neste projeto serão documentadas neste ficheiro.

## [1.1.0] - 2024-11-02

### ✨ Adicionado

- **Endpoints de Logout**
  - Logout com destruição de sessão no servidor

- **Endpoints CRUD de Artigos**
  - Listar artigos com paginação e busca
  - Obter artigo por ID ou barcode
  - Criar, atualizar e deletar artigos
  - Filtros por categoria e busca

- **Endpoints CRUD de Categorias**
  - Listar categorias com contagem de artigos
  - Criar, atualizar e deletar categorias
  - Proteção contra deleção com artigos associados

- **Endpoint de Estatísticas**
  - Estatísticas gerais do sistema
  - Top categorias e artigos com stock baixo
  - Valor total do inventário
  - Movimentos recentes

- **Validações no Frontend**
  - Arquivo `validators.js` com funções de validação
  - Validação de código de barras, nomes, quantidades, preços
  - Validação de ficheiros de importação
  - Mensagens de erro claras e específicas

- **Melhorias de Tratamento de Erros**
  - Parse JSON seguro em todas as requisições
  - Verificação de `response.ok` antes do parse
  - Logs de erros mais detalhados
  - Toast notifications melhoradas (sucesso, erro, aviso)

### 🔧 Melhorias

- Tratamento de erros robusto em todas as requisições fetch
- Validação de tipos e formatos no cliente
- Mensagens de feedback mais informativas
- Logout melhorado com limpeza de sessão no servidor

### 🐛 Correções

- Erro de sintaxe do ZXing corrigido
- Erro de leitura de diretório .env corrigido
- Erro SQL de parâmetros duplicados corrigido
- Hash de senha atualizado
- CORS configurado corretamente para cookies
- Sessões PHP configuradas corretamente

## [1.0.0] - 2024-01-15

### ✨ Adicionado

- **Sistema de Autenticação**
  - Login com username/password
  - Gestão de sessões PHP
  - Controlo de permissões (admin/operador)

- **Gestão de Inventário**
  - CRUD completo de artigos
  - Gestão de categorias
  - Controlo de stock (quantidade atual e mínima)

- **Sessões de Inventário**
  - Criar, listar e gerir sessões
  - Adicionar contagens via código de barras
  - Calcular diferenças automaticamente
  - Exportar sessões (JSON/CSV)

- **Scanner de Código de Barras**
  - Integração com ZXing JS Library
  - Suporte para câmara do dispositivo
  - Entrada manual de códigos

- **Importação de Dados**
  - Importação de ficheiros CSV/XLSX
  - Script Python para processamento
  - Validação e tratamento de erros
  - Criação automática de categorias

- **Frontend Responsivo**
  - Design moderno com Tailwind CSS
  - Interface mobile-first
  - Cards e componentes reutilizáveis
  - Feedback visual (toasts, loading)

- **API RESTful**
  - Endpoints PHP bem estruturados
  - Respostas JSON consistentes
  - Tratamento de erros robusto
  - Sanitização de entradas

- **Base de Dados**
  - Schema MySQL completo
  - Relacionamentos bem definidos
  - Índices para performance
  - Dados de exemplo

- **Docker Compose**
  - Configuração para desenvolvimento
  - Serviços: MySQL, PHP-Apache, phpMyAdmin
  - Volumes persistentes
  - Health checks

- **Documentação**
  - README completo
  - Guia de instalação detalhado
  - Referência da API
  - Documentação da base de dados
  - Changelog
  - Contribuindo
  - Licença

### 🔒 Segurança

- Sanitização de todas as entradas
- Proteção contra SQL Injection (PDO Prepared Statements)
- Proteção contra XSS
- Armazenamento seguro de senhas (password_hash)
- Configurações de segurança no Apache (.htaccess)
- CORS configurado

### 🐛 Correções

- Nenhuma correção na versão inicial

### 📝 Notas

- Primeira versão funcional do projeto
- Sistema pronto para desenvolvimento e testes
- Preparado para extensões futuras

## [Planejado] - Futuro

### ✨ Funcionalidades Futuras

- **Dashboard**
  - Resumo de stock
  - Gráficos e estatísticas
  - Alertas de stock baixo

- **Autenticação Avançada**
  - JWT tokens
  - Refresh tokens
  - Recuperação de senha

- **Exportação Avançada**
  - Exportação para Excel
  - Relatórios PDF
  - Templates personalizáveis

- **Integrações**
  - API REST pública
  - Webhooks
  - Integração com sistemas externos

- **Multilíngua**
  - Suporte pt-PT e en
  - Sistema de traduções
  - Detecção automática de idioma

- **Melhorias de UX**
  - Filtros e pesquisa avançada
  - Ordenação e paginação
  - Modo escuro
  - Atalhos de teclado

- **Mobile App**
  - App nativo Android/iOS
  - Sincronização offline
  - Push notifications

---

## Formato de Versionamento

Este projeto segue [Semantic Versioning](https://semver.org/):
- **MAJOR**: Mudanças incompatíveis na API
- **MINOR**: Adição de funcionalidades compatíveis
- **PATCH**: Correções de bugs compatíveis

