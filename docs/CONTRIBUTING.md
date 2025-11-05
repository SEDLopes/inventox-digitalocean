# Guia de Contribuição - InventoX

Obrigado por considerar contribuir para o InventoX! Este documento fornece diretrizes para contribuições.

## 🤝 Como Contribuir

### Reportar Bugs

1. Verifique se o bug já foi reportado nas [Issues](../../issues)
2. Se não existir, crie uma nova issue com:
   - Título descritivo
   - Descrição detalhada do problema
   - Passos para reproduzir
   - Comportamento esperado vs. atual
   - Screenshots (se aplicável)
   - Ambiente (OS, versão Docker, navegador, etc.)

### Sugerir Funcionalidades

1. Verifique se a funcionalidade já foi sugerida
2. Crie uma issue com:
   - Descrição clara da funcionalidade
   - Caso de uso (por que é útil?)
   - Exemplos de implementação (se possível)

### Contribuir com Código

#### Processo

1. **Fork** o repositório
2. **Clone** o seu fork:
   ```bash
   git clone https://github.com/seu-usuario/InventoX.git
   cd InventoX
   ```

3. **Crie uma branch** para a sua funcionalidade/correção:
   ```bash
   git checkout -b feature/nova-funcionalidade
   # ou
   git checkout -b fix/correcao-bug
   ```

4. **Faça as alterações** seguindo as diretrizes abaixo

5. **Teste** as suas alterações:
   ```bash
   docker-compose up -d
   # Testar funcionalidade
   ```

6. **Commit** com mensagens claras:
   ```bash
   git commit -m "Adiciona funcionalidade X"
   ```

7. **Push** para o seu fork:
   ```bash
   git push origin feature/nova-funcionalidade
   ```

8. **Abra um Pull Request** no repositório original

## 📝 Diretrizes de Código

### PHP

- Siga [PSR-12](https://www.php-fig.org/psr/psr-12/) coding standard
- Use PDO para todas as queries de base de dados
- Sempre use prepared statements
- Sanitize todas as entradas
- Documente funções complexas
- Adicione comentários onde necessário

**Exemplo**:
```php
/**
 * Função que processa dados
 * @param string $input Dados de entrada
 * @return array Dados processados
 */
function processData(string $input): array {
    $sanitized = sanitizeInput($input);
    // Processar...
    return $result;
}
```

### JavaScript

- Use ES6+ features
- Siga convenções de nomenclatura:
  - `camelCase` para variáveis/funções
  - `PascalCase` para classes
  - `UPPER_SNAKE_CASE` para constantes
- Documente funções complexas com JSDoc
- Mantenha funções pequenas e focadas

**Exemplo**:
```javascript
/**
 * Processa código de barras
 * @param {string} barcode - Código de barras
 * @returns {Promise<Object>} Informações do artigo
 */
async function processBarcode(barcode) {
    // Implementação...
}
```

### Python

- Siga [PEP 8](https://www.python.org/dev/peps/pep-0008/)
- Use type hints quando possível
- Documente funções e classes
- Mantenha funções pequenas (< 50 linhas)

**Exemplo**:
```python
def import_items(file_path: str) -> dict:
    """
    Importa artigos de um ficheiro
    
    Args:
        file_path: Caminho do ficheiro CSV/XLSX
        
    Returns:
        dict: Resultado da importação
    """
    # Implementação...
```

### HTML/CSS

- Use HTML5 semântico
- Mantenha estrutura clara e acessível
- Use Tailwind CSS para estilos
- Mantenha CSS customizado mínimo
- Comente seções complexas

### Base de Dados

- Use nomes descritivos para tabelas/colunas
- Adicione índices para queries frequentes
- Documente relacionamentos complexos
- Inclua migrações SQL para mudanças

## ✅ Checklist de Pull Request

Antes de submeter um PR, verifique:

- [ ] Código segue as diretrizes acima
- [ ] Funcionalidade testada localmente
- [ ] Documentação atualizada (se necessário)
- [ ] CHANGELOG.md atualizado (se mudança significativa)
- [ ] Sem erros de lint/validação
- [ ] Commits com mensagens claras
- [ ] Branch atualizada com `main`/`master`

## 🧪 Testes

Ao adicionar novas funcionalidades:

1. Teste manualmente no navegador
2. Teste em dispositivos móveis (se aplicável)
3. Verifique compatibilidade de navegadores
4. Teste casos extremos e erros
5. Valide entrada de dados

## 📚 Documentação

### Atualizar Documentação

Se adicionar/modificar funcionalidades:

- Atualize `README.md` se necessário
- Atualize `API_REFERENCE.md` para mudanças na API
- Atualize `DB_STRUCTURE.md` para mudanças no schema
- Atualize `CHANGELOG.md` com mudanças significativas
- Adicione exemplos de uso quando apropriado

### Formato de Comentários

```php
/**
 * Descrição breve da função
 *
 * Descrição mais detalhada se necessário.
 *
 * @param type $param Descrição do parâmetro
 * @return type Descrição do retorno
 * @throws Exception Quando algo falha
 */
```

## 🎯 Prioridades

### Alta Prioridade

- Correções de bugs críticos
- Vulnerabilidades de segurança
- Melhorias de performance

### Média Prioridade

- Novas funcionalidades solicitadas
- Melhorias de UX
- Otimizações de código

### Baixa Prioridade

- Refatorações
- Melhorias de documentação
- Ajustes cosméticos

## 🚫 O Que Não Fazer

- Não faça mudanças sem discutir funcionalidades grandes primeiro
- Não remova funcionalidades sem justificação
- Não adicione dependências desnecessárias
- Não commite ficheiros sensíveis (.env, etc.)
- Não force push na branch main/master

## 💬 Comunicação

- Seja respeitoso e construtivo
- Seja claro e direto
- Forneça contexto quando necessário
- Responda a perguntas/feedback de forma atempada

## 📄 Licença

Ao contribuir, você concorda que as suas contribuições serão licenciadas sob a mesma licença do projeto (MIT License).

## 🙏 Reconhecimento

Contribuidores serão reconhecidos no README e/ou em releases.

---

Obrigado por ajudar a tornar o InventoX melhor! 🎉

