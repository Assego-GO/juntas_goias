<?php
/**
 * API de Tipos de Violência
 * Endpoint para listar os tipos de violência cadastrados
 */

require_once '../config/cors.php';
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$metodo = $_SERVER['REQUEST_METHOD'];

try {
    if ($metodo === 'GET') {
        // Buscar todos os tipos de violência
        $sql = "SELECT * FROM tipos_violencia ORDER BY id ASC";
        $stmt = $db->query($sql);
        $tipos = $stmt->fetchAll();
        
        JsonResponse::success($tipos, "Tipos de violência recuperados com sucesso");
    }
    
} catch(Exception $e) {
    error_log("Erro na API de tipos de violência: " . $e->getMessage());
    JsonResponse::error("Erro ao buscar tipos de violência: " . $e->getMessage(), 500);
}
?>
