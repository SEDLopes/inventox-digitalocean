<?php
/**
 * Teste simples para verificar se o PHP está funcionando
 * 
 * ⚠️ ATENÇÃO: Este endpoint é apenas para debug/teste
 * Em produção, requer autenticação admin
 */

require_once __DIR__ . '/protect_debug_endpoints.php';

header('Content-Type: text/plain; charset=utf-8');

echo "✅ PHP está funcionando!\n";
echo "📅 Data/Hora: " . date('Y-m-d H:i:s') . "\n";
echo "🐘 Versão PHP: " . PHP_VERSION . "\n";
echo "🌐 Server: " . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "\n";
echo "📂 Document Root: " . ($_SERVER['DOCUMENT_ROOT'] ?? 'N/A') . "\n";
echo "🔧 Script Name: " . ($_SERVER['SCRIPT_NAME'] ?? 'N/A') . "\n";

// Testar extensões
echo "\n📦 Extensões PHP:\n";
echo "- PDO: " . (extension_loaded('pdo') ? '✅' : '❌') . "\n";
echo "- PDO MySQL: " . (extension_loaded('pdo_mysql') ? '✅' : '❌') . "\n";
echo "- JSON: " . (extension_loaded('json') ? '✅' : '❌') . "\n";
echo "- cURL: " . (extension_loaded('curl') ? '✅' : '❌') . "\n";

echo "\n🎯 Teste concluído com sucesso!";
?>