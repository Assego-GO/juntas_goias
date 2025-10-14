<?php
/**
 * API de Demandas
 * Recebe e processa formulários de pedidos de ajuda
 */

require_once '../config/cors.php';
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$metodo = $_SERVER['REQUEST_METHOD'];

try {
    if ($metodo === 'POST') {
        // Receber dados JSON
        $json = file_get_contents("php://input");
        $dados = json_decode($json);
        
        // Validações básicas
        if (!isset($dados->nivel_urgencia)) {
            JsonResponse::error("Nível de urgência é obrigatório", 400);
        }
        
        // Iniciar transação
        $db->beginTransaction();
        
        try {
            // Inserir demanda
            $sql = "INSERT INTO demandas (
                        municipio_id, nome, telefone, email, faixa_etaria, 
                        situacao_moradia, descricao, nivel_urgencia, perigo_iminente, 
                        criancas_envolvidas, idosos_envolvidos, anonimo, ip_origem
                    ) VALUES (
                        :municipio_id, :nome, :telefone, :email, :faixa_etaria, 
                        :situacao_moradia, :descricao, :nivel_urgencia, :perigo_iminente, 
                        :criancas_envolvidas, :idosos_envolvidos, :anonimo, :ip_origem
                    )";
            
            $stmt = $db->prepare($sql);
            
            // Obter IP do cliente
            $ip_origem = $_SERVER['REMOTE_ADDR'];
            if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $ip_origem = $_SERVER['HTTP_X_FORWARDED_FOR'];
            }
            
            // Bind dos parâmetros
            $stmt->bindParam(':municipio_id', $dados->municipio_id);
            $stmt->bindParam(':nome', $dados->nome);
            $stmt->bindParam(':telefone', $dados->telefone);
            $stmt->bindParam(':email', $dados->email);
            $stmt->bindParam(':faixa_etaria', $dados->faixa_etaria);
            $stmt->bindParam(':situacao_moradia', $dados->situacao_moradia);
            $stmt->bindParam(':descricao', $dados->descricao);
            $stmt->bindParam(':nivel_urgencia', $dados->nivel_urgencia);
            $stmt->bindParam(':perigo_iminente', $dados->perigo_iminente, PDO::PARAM_BOOL);
            $stmt->bindParam(':criancas_envolvidas', $dados->criancas_envolvidas, PDO::PARAM_BOOL);
            $stmt->bindParam(':idosos_envolvidos', $dados->idosos_envolvidos, PDO::PARAM_BOOL);
            $stmt->bindParam(':anonimo', $dados->anonimo, PDO::PARAM_BOOL);
            $stmt->bindParam(':ip_origem', $ip_origem);
            
            $stmt->execute();
            $demanda_id = $db->lastInsertId();
            
            // Inserir tipos de violência relacionados
            if (isset($dados->tipos_violencia) && is_array($dados->tipos_violencia) && count($dados->tipos_violencia) > 0) {
                $sql_tipo = "INSERT INTO demandas_tipos_violencia (demanda_id, tipo_violencia_id) 
                             VALUES (:demanda_id, :tipo_id)";
                $stmt_tipo = $db->prepare($sql_tipo);
                
                foreach ($dados->tipos_violencia as $tipo_id) {
                    $tipo_id_int = intval($tipo_id);
                    $stmt_tipo->bindParam(':demanda_id', $demanda_id, PDO::PARAM_INT);
                    $stmt_tipo->bindParam(':tipo_id', $tipo_id_int, PDO::PARAM_INT);
                    $stmt_tipo->execute();
                }
            }
            
            // Registrar log
            $log_sql = "INSERT INTO logs_sistema (tipo, descricao, ip_origem) 
                        VALUES ('demanda', :descricao, :ip)";
            $log_stmt = $db->prepare($log_sql);
            $log_desc = "Nova demanda criada - ID: {$demanda_id} - Urgência: {$dados->nivel_urgencia}";
            $log_stmt->bindParam(':descricao', $log_desc);
            $log_stmt->bindParam(':ip', $ip_origem);
            $log_stmt->execute();
            
            // Commit da transação
            $db->commit();
            
            // Se urgência alta, enviar notificação (implementar depois)
            if ($dados->nivel_urgencia === 'alto' || $dados->perigo_iminente) {
                // TODO: Enviar notificação para equipe de emergência
                error_log("URGENTE: Demanda de alta prioridade - ID: {$demanda_id}");
            }
            
            JsonResponse::success([
                'demanda_id' => $demanda_id,
                'protocolo' => 'JG-' . str_pad($demanda_id, 6, '0', STR_PAD_LEFT)
            ], "Demanda registrada com sucesso", 201);
            
        } catch(Exception $e) {
            // Rollback em caso de erro
            $db->rollBack();
            throw $e;
        }
        
    } elseif ($metodo === 'GET') {
        // Listar demandas (apenas para admin)
        $status = isset($_GET['status']) ? $_GET['status'] : null;
        $urgencia = isset($_GET['urgencia']) ? $_GET['urgencia'] : null;
        
        $sql = "SELECT d.*, m.nome as municipio_nome,
                       GROUP_CONCAT(tv.nome SEPARATOR ', ') as tipos_violencia
                FROM demandas d
                LEFT JOIN municipios m ON d.municipio_id = m.id
                LEFT JOIN demandas_tipos_violencia dtv ON d.id = dtv.demanda_id
                LEFT JOIN tipos_violencia tv ON dtv.tipo_violencia_id = tv.id
                WHERE 1=1";
        
        if ($status) {
            $sql .= " AND d.status = :status";
        }
        if ($urgencia) {
            $sql .= " AND d.nivel_urgencia = :urgencia";
        }
        
        $sql .= " GROUP BY d.id ORDER BY d.created_at DESC LIMIT 100";
        
        $stmt = $db->prepare($sql);
        
        if ($status) {
            $stmt->bindParam(':status', $status);
        }
        if ($urgencia) {
            $stmt->bindParam(':urgencia', $urgencia);
        }
        
        $stmt->execute();
        $demandas = $stmt->fetchAll();
        
        JsonResponse::success($demandas, "Demandas recuperadas com sucesso");
    }
    
} catch(Exception $e) {
    error_log("Erro na API de demandas: " . $e->getMessage());
    JsonResponse::error("Erro ao processar demanda: " . $e->getMessage(), 500);
}
?>
