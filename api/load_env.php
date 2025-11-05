<?php
/**
 * InventoX - Environment Variables Loader
 * Carrega variáveis de ambiente do ficheiro .env
 */

function loadEnv($envPath = null) {
    if ($envPath === null) {
        // Tentar vários caminhos possíveis
        $possiblePaths = [
            __DIR__ . '/../.env',
            __DIR__ . '/.env',
            '/var/www/html/.env'
        ];
        
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                $envPath = $path;
                break;
            }
        }
    }
    
    if ($envPath && file_exists($envPath) && is_file($envPath)) {
        $lines = @file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        if ($lines === false) {
            return; // Erro ao ler arquivo, sair silenciosamente
        }
        
        foreach ($lines as $line) {
            // Ignorar comentários
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            
            // Processar linha KEY=VALUE
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Remover aspas se existirem
                $value = trim($value, '"\'');
                
                // Definir variável de ambiente se não existir
                if (!array_key_exists($key, $_SERVER) && !array_key_exists($key, $_ENV)) {
                    $_ENV[$key] = $value;
                    $_SERVER[$key] = $value;
                    putenv("$key=$value");
                }
            }
        }
    }
}

// Carregar automaticamente ao incluir este ficheiro
loadEnv();

