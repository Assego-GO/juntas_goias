<?php
/**
 * API de Municípios
 * Endpoints para buscar informações sobre municípios de Goiás
 */

require_once '../config/cors.php';
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$metodo = $_SERVER['REQUEST_METHOD'];

try {
    if ($metodo === 'GET') {
        // Listar todos os municípios
        $sql = "SELECT id, nome, latitude, longitude, populacao 
                FROM municipios 
                ORDER BY nome ASC";
        
        $stmt = $db->query($sql);
        $municipios = $stmt->fetchAll();
        
        JsonResponse::success($municipios, "Municípios recuperados com sucesso");
    }
    
} catch(Exception $e) {
    error_log("Erro na API de municípios: " . $e->getMessage());
    JsonResponse::error("Erro ao buscar municípios: " . $e->getMessage(), 500);
}
?>
