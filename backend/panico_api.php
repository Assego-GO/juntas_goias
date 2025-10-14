<?php
/**
 * API de Alertas de Pânico com Geolocalização
 * Integrado com as tabelas alertas_socorro e alertas_localizacoes
 * 
 * Autor: Sistema Juntas Goiás
 * Data: 2025
 */

// Configuração de headers para CORS e JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Responde preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Inclui configuração do banco de dados
require_once 'config.php';

/**
 * Gera um hash anônimo baseado em dados do usuário
 * Isso permite rastrear alertas do mesmo usuário mantendo anonimato
 * 
 * @param array $data Dados para gerar hash
 * @return string Hash SHA256
 */
function gerarUsuarioHash($data) {
    // Combina IP, User Agent e um salt para criar identificador único mas anônimo
    $seed = ($data['ip'] ?? '') . 
            ($data['user_agent'] ?? '') . 
            date('Y-m-d'); // Muda diariamente para maior privacidade
    
    return hash('sha256', $seed);
}

/**
 * Registra log no sistema
 * 
 * @param PDO $pdo Conexão com banco
 * @param string $tipo Tipo do log
 * @param string $descricao Descrição do evento
 * @param string|null $usuarioHash Hash do usuário
 * @param string|null $ip IP de origem
 */
function registrarLog($pdo, $tipo, $descricao, $usuarioHash = null, $ip = null) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO logs_sistema (tipo, usuario_hash, descricao, ip_origem, user_agent)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $tipo,
            $usuarioHash,
            $descricao,
            $ip,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);
    } catch (PDOException $e) {
        // Log silencioso - não interrompe o fluxo principal
        error_log("Erro ao registrar log: " . $e->getMessage());
    }
}

/**
 * Envia notificação de emergência (implementar conforme necessário)
 * 
 * @param array $alertaData Dados do alerta
 */
function enviarNotificacaoEmergencia($alertaData) {
    // TODO: Implementar notificações
    // Exemplos:
    // - Email para autoridades
    // - SMS via API (Twilio, etc)
    // - Webhook para sistema de monitoramento
    // - WhatsApp Business API
    // - Integração com aplicativo de segurança pública
    
    // Por enquanto, apenas registra no log do servidor
    error_log("🆘 ALERTA DE PÂNICO RECEBIDO - ID: " . ($alertaData['id'] ?? 'N/A'));
}

// ============================================
// ENDPOINT: CRIAR NOVO ALERTA DE PÂNICO
// ============================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Recebe dados JSON
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        
        // Validação básica
        if (!isset($data['tipo']) || $data['tipo'] !== 'panico') {
            throw new Exception('Tipo de alerta inválido');
        }
        
        // Captura IP de origem
        $ipOrigem = $_SERVER['REMOTE_ADDR'] ?? null;
        
        // Gera hash anônimo do usuário
        $usuarioHash = gerarUsuarioHash([
            'ip' => $ipOrigem,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
        
        // Extrai dados de localização
        $localizacao = $data['localizacao'] ?? null;
        $latitude = $localizacao['latitude'] ?? null;
        $longitude = $localizacao['longitude'] ?? null;
        $precisao = $localizacao['accuracy'] ?? null;
        
        // Inicia transação
        $pdo->beginTransaction();
        
        // 1. Insere alerta principal na tabela alertas_socorro
        $stmtAlerta = $pdo->prepare("
            INSERT INTO alertas_socorro 
            (usuario_hash, latitude, longitude, precisao, ativo)
            VALUES (?, ?, ?, ?, 1)
        ");
        
        $stmtAlerta->execute([
            $usuarioHash,
            $latitude,
            $longitude,
            $precisao
        ]);
        
        $alertaId = $pdo->lastInsertId();
        
        // 2. Se há localização, registra primeira entrada em alertas_localizacoes
        if ($latitude && $longitude) {
            $stmtLoc = $pdo->prepare("
                INSERT INTO alertas_localizacoes
                (alerta_id, latitude, longitude, precisao, velocidade)
                VALUES (?, ?, ?, ?, NULL)
            ");
            
            $stmtLoc->execute([
                $alertaId,
                $latitude,
                $longitude,
                $precisao
            ]);
        }
        
        // 3. Registra log do sistema
        registrarLog(
            $pdo,
            'alerta',
            "🆘 ALERTA DE PÂNICO criado - ID: {$alertaId} - " . 
            ($latitude ? "Localização: {$latitude}, {$longitude}" : "Sem localização"),
            $usuarioHash,
            $ipOrigem
        );
        
        // Commit da transação
        $pdo->commit();
        
        // Prepara resposta de sucesso
        $resposta = [
            'success' => true,
            'message' => 'Alerta de pânico registrado com sucesso',
            'alerta_id' => $alertaId,
            'usuario_hash' => $usuarioHash,
            'com_localizacao' => ($latitude && $longitude) ? true : false
        ];
        
        // Adiciona links úteis se houver localização
        if ($latitude && $longitude) {
            $resposta['links'] = [
                'google_maps' => "https://www.google.com/maps?q={$latitude},{$longitude}",
                'waze' => "https://www.waze.com/ul?ll={$latitude},{$longitude}&navigate=yes"
            ];
        }
        
        // Envia notificações (implementar conforme necessário)
        enviarNotificacaoEmergencia([
            'id' => $alertaId,
            'usuario_hash' => $usuarioHash,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'precisao' => $precisao,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
        // Retorna sucesso
        echo json_encode($resposta, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        
    } catch (Exception $e) {
        // Rollback em caso de erro
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        // Registra erro
        error_log("Erro ao criar alerta de pânico: " . $e->getMessage());
        
        // Retorna erro
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao registrar alerta: ' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
}

// ============================================
// ENDPOINT: ATUALIZAR LOCALIZAÇÃO DE ALERTA ATIVO
// ============================================

elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    try {
        // Recebe dados JSON
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        
        // Validações
        if (!isset($data['alerta_id']) || !isset($data['localizacao'])) {
            throw new Exception('Dados incompletos para atualização');
        }
        
        $alertaId = (int)$data['alerta_id'];
        $localizacao = $data['localizacao'];
        $latitude = $localizacao['latitude'] ?? null;
        $longitude = $localizacao['longitude'] ?? null;
        $precisao = $localizacao['accuracy'] ?? null;
        $velocidade = $localizacao['speed'] ?? null;
        
        if (!$latitude || !$longitude) {
            throw new Exception('Localização não fornecida');
        }
        
        // Verifica se o alerta existe e está ativo
        $stmtCheck = $pdo->prepare("
            SELECT id, usuario_hash, ativo 
            FROM alertas_socorro 
            WHERE id = ? AND ativo = 1
        ");
        $stmtCheck->execute([$alertaId]);
        $alerta = $stmtCheck->fetch(PDO::FETCH_ASSOC);
        
        if (!$alerta) {
            throw new Exception('Alerta não encontrado ou já foi desativado');
        }
        
        // Inicia transação
        $pdo->beginTransaction();
        
        // 1. Adiciona nova localização ao histórico
        $stmtLoc = $pdo->prepare("
            INSERT INTO alertas_localizacoes
            (alerta_id, latitude, longitude, precisao, velocidade)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmtLoc->execute([
            $alertaId,
            $latitude,
            $longitude,
            $precisao,
            $velocidade
        ]);
        
        // 2. Atualiza última localização no alerta principal
        $stmtUpdate = $pdo->prepare("
            UPDATE alertas_socorro 
            SET latitude = ?, longitude = ?, precisao = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmtUpdate->execute([
            $latitude,
            $longitude,
            $precisao,
            $alertaId
        ]);
        
        // 3. Registra log
        registrarLog(
            $pdo,
            'alerta',
            "📍 Localização atualizada - Alerta ID: {$alertaId} - Coords: {$latitude}, {$longitude}",
            $alerta['usuario_hash'],
            $_SERVER['REMOTE_ADDR'] ?? null
        );
        
        // Commit
        $pdo->commit();
        
        // Retorna sucesso
        echo json_encode([
            'success' => true,
            'message' => 'Localização atualizada com sucesso',
            'alerta_id' => $alertaId
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        error_log("Erro ao atualizar localização: " . $e->getMessage());
        
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Erro ao atualizar localização: ' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
}

// Método não permitido
else {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Método não permitido'
    ], JSON_UNESCAPED_UNICODE);
}
?>
