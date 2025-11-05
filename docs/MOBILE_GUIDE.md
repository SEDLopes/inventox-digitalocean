# InventoX - Guia para Dispositivos Móveis

## 📱 Otimização para Android e iOS

O InventoX foi otimizado especificamente para funcionar em dispositivos móveis Android e iOS, proporcionando uma experiência nativa de digitalização de códigos de barras.

## 🚀 Funcionalidades Móveis

### ✅ **Detecção Automática de Dispositivo**
- Deteta automaticamente se está a usar Android, iOS ou outro dispositivo móvel
- Aplica otimizações específicas para cada plataforma
- Configura interface adaptada para ecrãs tácteis

### 📷 **Acesso Automático à Câmara**
- **Solicita permissão** automaticamente ao carregar a aplicação
- **Prioriza câmara traseira** (ideal para códigos de barras)
- **Configurações otimizadas**: 1280x720, 30fps
- **Feedback visual** quando a câmara está pronta

### 🎯 **Interface Otimizada**
- **Botões maiores** (mín. 44px) para facilitar o toque
- **Campos de entrada** com tamanho adequado (16px para evitar zoom)
- **Notificações adaptadas** para ecrãs pequenos
- **Feedback tátil** (vibração) quando digitaliza um código

## 📋 **Como Usar no Mobile**

### **1. Primeiro Acesso**
1. Abra o navegador (Chrome, Safari, Firefox)
2. Aceda a `http://seu-servidor:8080`
3. **Permita o acesso à câmara** quando solicitado
4. Faça login com as suas credenciais

### **2. Digitalização de Códigos**
1. Vá ao tab **"Scanner"**
2. Clique em **"Digitalizar Código"** (botão verde se a câmara estiver pronta)
3. **Aponte a câmara** para o código de barras
4. **Aguarde a vibração** - código foi digitalizado!
5. **Confirme a quantidade** e guarde

### **3. Códigos de Referência**
- Se não tiver código de barras, digite o **código de referência** manualmente
- O sistema procura automaticamente por códigos parciais
- Exemplo: digite "08750" para encontrar "8425998087505"

## 🔧 **Configurações Recomendadas**

### **Android (Chrome/Firefox)**
```
✅ Permitir câmara para este site
✅ Permitir notificações (opcional)
✅ Adicionar à tela inicial (PWA)
```

### **iOS (Safari)**
```
✅ Permitir câmara para este site
✅ Permitir notificações (opcional)  
✅ Adicionar ao ecrã inicial
```

## 🎨 **Características Visuais Móveis**

### **Indicadores de Estado da Câmara**
- 🟢 **Verde**: Câmara pronta e autorizada
- 🔵 **Azul**: Aguardando permissão
- 🔴 **Vermelho**: Permissão negada ou erro

### **Classes CSS Aplicadas**
- `.ios-device` - Dispositivos iOS
- `.android-device` - Dispositivos Android  
- `.mobile-device` - Outros dispositivos móveis
- `.camera-ready` - Câmara autorizada
- `.camera-denied` - Câmara negada

## ⚡ **Otimizações de Performance**

### **Câmara**
- **Resolução**: 1280x720 (ideal para códigos de barras)
- **Frame Rate**: 30fps (equilibrio entre qualidade e performance)
- **Facing Mode**: `environment` (câmara traseira)
- **Auto-focus**: Ativado automaticamente

### **Interface**
- **Touch targets**: Mínimo 44px (padrão Apple/Google)
- **Font size**: 16px (previne zoom automático no Android)
- **Scrolling**: Suave com `-webkit-overflow-scrolling: touch`

## 🐛 **Resolução de Problemas**

### **"Nenhuma câmara encontrada"**
1. Verifique se o dispositivo tem câmara
2. Certifique-se que está a usar HTTPS (obrigatório para câmara)
3. Tente recarregar a página
4. Verifique permissões do navegador

### **"Permissão de câmara negada"**
1. Vá às **definições do navegador**
2. Procure por **"Permissões de sites"**
3. Encontre o seu site e **ative a câmara**
4. Recarregue a página

### **Códigos não são reconhecidos**
1. **Aproxime-se** do código (15-30cm)
2. Certifique-se que há **boa iluminação**
3. **Mantenha o telemóvel estável**
4. Tente **diferentes ângulos**
5. Use o **campo manual** como alternativa

### **Interface muito pequena**
1. **Zoom do navegador**: Ajuste para 100-125%
2. **Orientação**: Use paisagem para mais espaço
3. **Modo ecrã inteiro**: Disponível em alguns navegadores

## 📊 **Compatibilidade**

### **Navegadores Suportados**
| Navegador | Android | iOS | Notas |
|-----------|---------|-----|-------|
| Chrome | ✅ | ✅ | Recomendado |
| Safari | ❌ | ✅ | Nativo iOS |
| Firefox | ✅ | ✅ | Boa alternativa |
| Edge | ✅ | ✅ | Suporte completo |

### **Versões Mínimas**
- **Android**: 7.0+ (API 24+)
- **iOS**: 12.0+
- **Chrome**: 60+
- **Safari**: 11+
- **Firefox**: 60+

## 🔒 **Segurança e Privacidade**

- **Câmara**: Acesso apenas durante digitalização
- **Dados**: Processamento local, não enviados para servidores externos
- **Permissões**: Solicitadas apenas quando necessárias
- **HTTPS**: Obrigatório para funcionalidades de câmara

## 💡 **Dicas de Utilização**

1. **Boa iluminação** melhora drasticamente o reconhecimento
2. **Câmara traseira** é mais precisa que a frontal
3. **Códigos limpos** são mais fáceis de digitalizar
4. **Mantenha distância** de 15-30cm do código
5. **Use códigos de referência** quando o barcode não funcionar

---

**Desenvolvido para proporcionar a melhor experiência móvel possível! 📱✨**
