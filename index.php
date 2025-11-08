<?php
// Railway entry point - redirect to frontend
$request_uri = $_SERVER['REQUEST_URI'] ?? '';

// Se for API, processar diretamente
if (strpos($request_uri, '/api/') === 0) {
    // Deixar processar normalmente
    return false;
}

// Redirecionar para frontend
if (file_exists(__DIR__ . '/frontend/index.html')) {
    header('Location: /frontend/index.html');
    exit;
}

// Fallback - mostrar info
echo "<!DOCTYPE html>
<html>
<head>
    <title>InventoX - Railway</title>
    <meta charset='utf-8'>
</head>
<body>
    <h1>InventoX no Railway</h1>
    <p><a href='/frontend/index.html'>Aceder à aplicação</a></p>
    <p><a href='/api/health.php'>Health Check</a></p>
    <hr>
    <small>PHP " . PHP_VERSION . " | " . date('Y-m-d H:i:s') . "</small>
</body>
</html>";
?>
