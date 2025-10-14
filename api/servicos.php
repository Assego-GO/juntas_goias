<?php
/**
 * API de Serviços
 * Endpoints para buscar DEAMs, Defensorias, CRAS, CAPS, etc.
 */

require_once '../config/cors.php';
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$metodo = $_SERVER['REQUEST_METHOD'];
$acao = isset($_GET['acao']) ? $_GET['acao'] : 'listar';

try {
    if ($metodo === 'GET') {
        
        switch($acao) {
            case 'categorias':
                // Buscar todas as categorias de serviços
                $sql = "SELECT * FROM categorias_servicos ORDER BY ordem ASC";
                $stmt = $db->query($sql);
                $categorias = $stmt->fetchAll();
                
                JsonResponse::success($categorias, "Categorias recuperadas com sucesso");
                break;
                
            case 'por-municipio':
                // Buscar serviços de um município específico
                $municipio_id = isset($_GET['municipio_id']) ? intval($_GET['municipio_id']) : 0;
                $categoria_id = isset($_GET['categoria_id']) ? intval($_GET['categoria_id']) : null;
                
                if ($municipio_id <= 0) {
                    JsonResponse::error("ID do município inválido", 400);
                }
                
                $sql = "SELECT s.*, 
                               c.nome as categoria_nome, 
                               c.cor as categoria_cor,
                               c.icone as categoria_icone,
                               m.nome as municipio_nome
                        FROM servicos s
                        INNER JOIN categorias_servicos c ON s.categoria_id = c.id
                        INNER JOIN municipios m ON s.municipio_id = m.id
                        WHERE s.ativo = 1 AND s.municipio_id = :municipio_id";
                
                if ($categoria_id) {
                    $sql .= " AND s.categoria_id = :categoria_id";
                }
                
                $sql .= " ORDER BY s.atendimento_24h DESC, c.ordem ASC, s.nome ASC";
                
                $stmt = $db->prepare($sql);
                $stmt->bindParam(':municipio_id', $municipio_id, PDO::PARAM_INT);
                
                if ($categoria_id) {
                    $stmt->bindParam(':categoria_id', $categoria_id, PDO::PARAM_INT);
                }
                
                $stmt->execute();
                $servicos = $stmt->fetchAll();
                
                JsonResponse::success($servicos, "Serviços recuperados com sucesso");
                break;
                
            case 'proximos':
                // Buscar serviços próximos a uma localização
                $lat = isset($_GET['lat']) ? floatval($_GET['lat']) : 0;
                $lng = isset($_GET['lng']) ? floatval($_GET['lng']) : 0;
                $raio = isset($_GET['raio']) ? floatval($_GET['raio']) : 10; // km padrão
                
                if ($lat == 0 || $lng == 0) {
                    JsonResponse::error("Coordenadas inválidas", 400);
                }
                
                $sql = "SELECT s.*, 
                               c.nome as categoria_nome, 
                               c.cor as categoria_cor,
                               c.icone as categoria_icone,
                               m.nome as municipio_nome,
                               (6371 * acos(
                                   cos(radians(:lat)) * cos(radians(s.latitude)) * 
                                   cos(radians(s.longitude) - radians(:lng)) + 
                                   sin(radians(:lat)) * sin(radians(s.latitude))
                               )) AS distancia_km
                        FROM servicos s
                        INNER JOIN categorias_servicos c ON s.categoria_id = c.id
                        INNER JOIN municipios m ON s.municipio_id = m.id
                        WHERE s.ativo = 1 
                        AND s.latitude IS NOT NULL 
                        AND s.longitude IS NOT NULL
                        HAVING distancia_km < :raio
                        ORDER BY distancia_km ASC
                        LIMIT 30";
                
                $stmt = $db->prepare($sql);
                $stmt->bindParam(':lat', $lat);
                $stmt->bindParam(':lng', $lng);
                $stmt->bindParam(':raio', $raio);
                $stmt->execute();
                
                $servicos = $stmt->fetchAll();
                
                JsonResponse::success($servicos, "Serviços próximos encontrados");
                break;
                
            case 'deams':
                // Buscar apenas DEAMs (Categoria Segurança e Justiça - ID 1)
                $sql = "SELECT s.*, m.nome as municipio_nome
                        FROM servicos s
                        INNER JOIN municipios m ON s.municipio_id = m.id
                        WHERE s.ativo = 1 
                        AND s.categoria_id = 1
                        AND s.nome LIKE '%DEAM%'
                        ORDER BY m.nome ASC, s.nome ASC";
                
                $stmt = $db->query($sql);
                $deams = $stmt->fetchAll();
                
                JsonResponse::success($deams, "DEAMs recuperadas com sucesso");
                break;
                
            default:
                // Listar todos os serviços
                $sql = "SELECT s.*, 
                               c.nome as categoria_nome,
                               c.cor as categoria_cor,
                               m.nome as municipio_nome
                        FROM servicos s
                        INNER JOIN categorias_servicos c ON s.categoria_id = c.id
                        INNER JOIN municipios m ON s.municipio_id = m.id
                        WHERE s.ativo = 1
                        ORDER BY m.nome ASC, c.ordem ASC, s.nome ASC";
                
                $stmt = $db->query($sql);
                $servicos = $stmt->fetchAll();
                
                JsonResponse::success($servicos, "Todos os serviços recuperados");
                break;
        }
    }
    
} catch(Exception $e) {
    error_log("Erro na API de serviços: " . $e->getMessage());
    JsonResponse::error("Erro ao buscar serviços: " . $e->getMessage(), 500);
}
?>
