<?php
/**
 * API de Alertas de Socorro (Botão de Pânico)
 * Gerencia alertas de emergência e rastreamento de localização
 */

require_once '../config/cors.php';
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$metodo = $_SERVER['REQUEST_METHOD'];
$acao = isset($_GET['acao']) ? $_GET['acao'] : '';

try {
    if ($metodo === 'POST') {
        $json = file_get_contents("php://input");
        $dados = json_decode($json);
        
        if ($acao === 'criar') {
            // Criar novo alerta de socorro
            if (!isset($dados->usuario_hash) || !isset($dados->latitude) || !isset($dados->longitude)) {
                JsonResponse::error("Dados obrigatórios ausentes", 400);
            }
            
            $db->beginTransaction();
            
            try {
                // Inserir alerta
                $sql = "INSERT INTO alertas_socorro (usuario_hash, latitude, longitude, precisao) 
                        VALUES (:usuario_hash, :latitude, :longitude, :precisao)";
                
                $stmt = $db->prepare($sql);
                $stmt->bindParam(':usuario_hash', $dados->usuario_hash);
                $stmt->bindParam(':latitude', $dados->latitude);
                $stmt->bindParam(':longitude', $dados->longitude);
                $stmt->bindParam(':precisao', $dados->precisao);
                $stmt->execute();
                
                $alerta_id = $db->lastInsertId();
                
                // Inserir primeira localização
                $sql_loc = "INSERT INTO alertas_localizacoes (alerta_id, latitude, longitude, precisao) 
                            VALUES (:alerta_id, :latitude, :longitude, :precisao)";
                $stmt_loc = $db->prepare($sql_loc);
                $stmt_loc->bindParam(':alerta_id', $alerta_id);
                $stmt_loc->bindParam(':latitude', $dados->latitude);
                $stmt_loc->bindParam(':longitude', $dados->longitude);
                $stmt_loc->bindParam(':precisao', $dados->precisao);
                $stmt_loc->execute();
                
                // Buscar contatos de emergência
                $sql_contatos = "SELECT * FROM contatos_emergencia 
                                WHERE usuario_hash = :usuario_hash AND ativo = 1 
                                ORDER BY ordem ASC";
                $stmt_contatos = $db->prepare($sql_contatos);
                $stmt_contatos->bindParam(':usuario_hash', $dados->usuario_hash);
                $stmt_contatos->execute();
                $contatos = $stmt_contatos->fetchAll();
                
                // Log do sistema
                $ip = $_SERVER['REMOTE_ADDR'];
                $log_sql = "INSERT INTO logs_sistema (tipo, usuario_hash, descricao, ip_origem) 
                            VALUES ('alerta', :usuario_hash, :descricao, :ip)";
                $log_stmt = $db->prepare($log_sql);
                $log_desc = "Alerta de socorro ativado - ID: {$alerta_id}";
                $log_stmt->bindParam(':usuario_hash', $dados->usuario_hash);
                $log_stmt->bindParam(':descricao', $log_desc);
                $log_stmt->bindParam(':ip', $ip);
                $log_stmt->execute();
                
                $db->commit();
                
                // TODO: Enviar notificações via SMS/WhatsApp para os contatos
                // Implementar integração com Twilio ou similar
                
                JsonResponse::success([
                    'alerta_id' => $alerta_id,
                    'contatos_notificados' => count($contatos),
                    'localizacao' => [
                        'latitude' => $dados->latitude,
                        'longitude' => $dados->longitude
                    ]
                ], "Alerta criado com sucesso", 201);
                
            } catch(Exception $e) {
                $db->rollBack();
                throw $e;
            }
            
        } elseif ($acao === 'atualizar-localizacao') {
            // Atualizar localização de um alerta ativo
            if (!isset($dados->alerta_id) || !isset($dados->latitude) || !isset($dados->longitude)) {
                JsonResponse::error("Dados obrigatórios ausentes", 400);
            }
            
            // Verificar se o alerta ainda está ativo
            $sql_check = "SELECT ativo FROM alertas_socorro WHERE id = :alerta_id";
            $stmt_check = $db->prepare($sql_check);
            $stmt_check->bindParam(':alerta_id', $dados->alerta_id);
            $stmt_check->execute();
            $alerta = $stmt_check->fetch();
            
            if (!$alerta) {
                JsonResponse::error("Alerta não encontrado", 404);
            }
            
            if (!$alerta['ativo']) {
                JsonResponse::error("Alerta já foi cancelado", 400);
            }
            
            // Inserir nova localização
            $sql = "INSERT INTO alertas_localizacoes (alerta_id, latitude, longitude, precisao, velocidade) 
                    VALUES (:alerta_id, :latitude, :longitude, :precisao, :velocidade)";
            
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':alerta_id', $dados->alerta_id);
            $stmt->bindParam(':latitude', $dados->latitude);
            $stmt->bindParam(':longitude', $dados->longitude);
            $stmt->bindParam(':precisao', $dados->precisao);
            $velocidade = isset($dados->velocidade) ? $dados->velocidade : 0;
            $stmt->bindParam(':velocidade', $velocidade);
            $stmt->execute();
            
            // Atualizar timestamp do alerta
            $sql_update = "UPDATE alertas_socorro SET updated_at = CURRENT_TIMESTAMP WHERE id = :alerta_id";
            $stmt_update = $db->prepare($sql_update);
            $stmt_update->bindParam(':alerta_id', $dados->alerta_id);
            $stmt_update->execute();
            
            JsonResponse::success([
                'alerta_id' => $dados->alerta_id,
                'localizacao_atualizada' => true
            ], "Localização atualizada com sucesso");
        }
        
    } elseif ($metodo === 'PUT') {
        if ($acao === 'cancelar') {
            // Cancelar alerta
            $json = file_get_contents("php://input");
            $dados = json_decode($json);
            
            if (!isset($dados->alerta_id)) {
                JsonResponse::error("ID do alerta é obrigatório", 400);
            }
            
            $sql = "UPDATE alertas_socorro SET ativo = 0 WHERE id = :alerta_id";
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':alerta_id', $dados->alerta_id);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                JsonResponse::success([
                    'alerta_id' => $dados->alerta_id,
                    'cancelado' => true
                ], "Alerta cancelado com sucesso");
            } else {
                JsonResponse::error("Alerta não encontrado", 404);
            }
        }
        
    } elseif ($metodo === 'GET') {
        // Buscar alertas ativos (para painel admin)
        $sql = "SELECT a.*, 
                       COUNT(al.id) as total_atualizacoes,
                       MAX(al.created_at) as ultima_atualizacao,
                       (SELECT latitude FROM alertas_localizacoes WHERE alerta_id = a.id ORDER BY created_at DESC LIMIT 1) as ultima_latitude,
                       (SELECT longitude FROM alertas_localizacoes WHERE alerta_id = a.id ORDER BY created_at DESC LIMIT 1) as ultima_longitude
                FROM alertas_socorro a
                LEFT JOIN alertas_localizacoes al ON a.id = al.alerta_id
                WHERE a.ativo = 1
                GROUP BY a.id
                ORDER BY a.created_at DESC";
        
        $stmt = $db->query($sql);
        $alertas = $stmt->fetchAll();
        
        JsonResponse::success($alertas, "Alertas ativos recuperados");
    }
    
} catch(Exception $e) {
    error_log("Erro na API de alertas: " . $e->getMessage());
    JsonResponse::error("Erro ao processar alerta: " . $e->getMessage(), 500);
}
?>
