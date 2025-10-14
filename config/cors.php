<?php
/**
 * Configuração CORS (Cross-Origin Resource Sharing)
 * Permite que o frontend faça requisições para a API
 */

// Permitir requisições de qualquer origem (em produção, especifique o domínio)
header("Access-Control-Allow-Origin: *");

// Permitir os métodos HTTP necessários
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

// Permitir headers personalizados
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Definir tipo de conteúdo como JSON
header("Content-Type: application/json; charset=UTF-8");

// Tempo de cache para requisições OPTIONS (preflight)
header("Access-Control-Max-Age: 3600");

// Tratar requisições OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}
?>
