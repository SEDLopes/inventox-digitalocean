/**
 * InventoX - Validators
 * Funções de validação para formulários
 */

// Validar código de barras
function validateBarcode(barcode) {
    if (!barcode || typeof barcode !== 'string') {
        return { valid: false, message: 'Código de barras é obrigatório' };
    }
    
    const trimmed = barcode.trim();
    
    if (trimmed.length === 0) {
        return { valid: false, message: 'Código de barras não pode estar vazio' };
    }
    
    if (trimmed.length > 100) {
        return { valid: false, message: 'Código de barras muito longo (máx. 100 caracteres)' };
    }
    
    // Verificar se contém apenas caracteres alfanuméricos e alguns especiais
    if (!/^[a-zA-Z0-9\-_]+$/.test(trimmed)) {
        return { valid: false, message: 'Código de barras contém caracteres inválidos' };
    }
    
    return { valid: true };
}

// Validar nome
function validateName(name) {
    if (!name || typeof name !== 'string') {
        return { valid: false, message: 'Nome é obrigatório' };
    }
    
    const trimmed = name.trim();
    
    if (trimmed.length === 0) {
        return { valid: false, message: 'Nome não pode estar vazio' };
    }
    
    if (trimmed.length > 255) {
        return { valid: false, message: 'Nome muito longo (máx. 255 caracteres)' };
    }
    
    return { valid: true };
}

// Validar quantidade
function validateQuantity(quantity) {
    if (quantity === null || quantity === undefined || quantity === '') {
        return { valid: false, message: 'Quantidade é obrigatória' };
    }
    
    const qty = parseInt(quantity);
    
    if (isNaN(qty)) {
        return { valid: false, message: 'Quantidade deve ser um número' };
    }
    
    if (qty < 0) {
        return { valid: false, message: 'Quantidade não pode ser negativa' };
    }
    
    return { valid: true, value: qty };
}

// Validar preço
function validatePrice(price) {
    if (price === null || price === undefined || price === '') {
        return { valid: true, value: 0 }; // Preço pode ser opcional
    }
    
    const priceValue = parseFloat(price);
    
    if (isNaN(priceValue)) {
        return { valid: false, message: 'Preço deve ser um número' };
    }
    
    if (priceValue < 0) {
        return { valid: false, message: 'Preço não pode ser negativo' };
    }
    
    return { valid: true, value: priceValue };
}

// Validar email
function validateEmail(email) {
    if (!email || typeof email !== 'string') {
        return { valid: false, message: 'Email é obrigatório' };
    }
    
    const trimmed = email.trim();
    
    if (trimmed.length === 0) {
        return { valid: false, message: 'Email não pode estar vazio' };
    }
    
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    
    if (!emailRegex.test(trimmed)) {
        return { valid: false, message: 'Email inválido' };
    }
    
    return { valid: true };
}

// Validar sessão de inventário
function validateSession(sessionName, sessionDescription = '') {
    if (!sessionName || typeof sessionName !== 'string') {
        return { valid: false, message: 'Nome da sessão é obrigatório' };
    }
    
    const trimmed = sessionName.trim();
    
    if (trimmed.length === 0) {
        return { valid: false, message: 'Nome da sessão não pode estar vazio' };
    }
    
    if (trimmed.length > 255) {
        return { valid: false, message: 'Nome da sessão muito longo (máx. 255 caracteres)' };
    }
    
    return { valid: true };
}

// Validar arquivo de importação
function validateImportFile(file) {
    if (!file) {
        return { valid: false, message: 'Selecione um ficheiro' };
    }
    
    const allowedTypes = [
        'text/csv',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    ];
    
    const allowedExtensions = ['csv', 'xls', 'xlsx'];
    const fileExtension = file.name.split('.').pop()?.toLowerCase();
    
    if (!allowedExtensions.includes(fileExtension)) {
        return { valid: false, message: 'Tipo de ficheiro não permitido. Use CSV ou XLSX' };
    }
    
    const maxSize = 10 * 1024 * 1024; // 10MB
    if (file.size > maxSize) {
        return { valid: false, message: 'Ficheiro muito grande. Máximo: 10MB' };
    }
    
    return { valid: true };
}

