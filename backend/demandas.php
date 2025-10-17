<?php
/**
 * API de Demandas - Mock Temporário
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Inicia sessão
session_start();

$action = $_GET['action'] ?? 'listar';

switch ($action) {
    case 'listar':
        // Retorna array vazio por enquanto
        echo json_encode([
            'sucesso' => true,
            'dados' => [],
            'total' => 0,
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
        break;
    
    case 'estatisticas':
        // Retorna estatísticas zeradas
        echo json_encode([
            'sucesso' => true,
            'dados' => [
                'total' => 0,
                'pendentes' => 0,
                'em_andamento' => 0,
                'resolvidas' => 0,
                'ultimos_7_dias' => []
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_UNESCAPED_UNICODE);
        break;
    
    default:
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Ação inválida'
        ], JSON_UNESCAPED_UNICODE);
}