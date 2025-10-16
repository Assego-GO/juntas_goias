<?php
/**
 * API de Gerenciamento de Demandas (Admin)
 * 
 * Este arquivo permite gerenciar as demandas recebidas pelo formulário.
 * Operações: Visualizar, Atualizar Status, Marcar como Lida, Deletar, Adicionar Notas.
 * 
 * Endpoints:
 * - GET /demandas_admin_api.php?action=listar - Listar todas as demandas
 * - GET /demandas_admin_api.php?action=buscar&id=X - Buscar demanda específica
 * - POST /demandas_admin_api.php?action=atualizar_status - Atualizar status
 * - POST /demandas_admin_api.php?action=marcar_lida - Marcar como lida
 * - POST /demandas_admin_api.php?action=adicionar_nota - Adicionar nota interna
 * - POST /demandas_admin_api.php?action=deletar - Deletar demanda
 * - POST /demandas_admin_api.php?action=exportar - Exportar demandas (CSV)
 * 
 * SEGURANÇA: Requer autenticação de administrador
 */

// Inicia a sessão
session_start();

// Inclui o sistema de autenticação e conexão
require_once 'login.php';
require_once 'db_connect.php';

// Verifica se o usuário está autenticado
verificarAutenticacao();

// Configura cabeçalhos para API JSON
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Trata requisições OPTIONS (preflight CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

/**
 * Função para listar todas as demandas com filtros
 */
function listarDemandas($conn) {
    $status = isset($_GET['status']) ? $_GET['status'] : null;
    $lida = isset($_GET['lida']) ? intval($_GET['lida']) : null;
    $dataInicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : null;
    $dataFim = isset($_GET['data_fim']) ? $_GET['data_fim'] : null;
    $busca = isset($_GET['busca']) ? trim($_GET['busca']) : null;
    $limite = isset($_GET['limite']) ? intval($_GET['limite']) : 50;
    $pagina = isset($_GET['pagina']) ? intval($_GET['pagina']) : 1;
    
    $offset = ($pagina - 1) * $limite;
    
    // Monta a query base
    $sql = "SELECT * FROM demandas WHERE 1=1";
    $params = [];
    $types = '';
    
    // Adiciona filtros
    if ($status !== null) {
        $sql .= " AND status = ?";
        $params[] = $status;
        $types .= 's';
    }
    
    if ($lida !== null) {
        $sql .= " AND lida = ?";
        $params[] = $lida;
        $types .= 'i';
    }
    
    if ($dataInicio !== null) {
        $sql .= " AND DATE(data_envio) >= ?";
        $params[] = $dataInicio;
        $types .= 's';
    }
    
    if ($dataFim !== null) {
        $sql .= " AND DATE(data_envio) <= ?";
        $params[] = $dataFim;
        $types .= 's';
    }
    
    if ($busca !== null) {
        $sql .= " AND (nome LIKE ? OR email LIKE ? OR assunto LIKE ? OR mensagem LIKE ?)";
        $buscaLike = '%' . $busca . '%';
        $params[] = $buscaLike;
        $params[] = $buscaLike;
        $params[] = $buscaLike;
        $params[] = $buscaLike;
        $types .= 'ssss';
    }
    
    // Conta o total antes de aplicar LIMIT
    $sqlCount = str_replace("SELECT *", "SELECT COUNT(*) as total", $sql);
    $stmtCount = $conn->prepare($sqlCount);
    
    if (!empty($params)) {
        $stmtCount->bind_param($types, ...$params);
    }
    
    $stmtCount->execute();
    $totalRegistros = $stmtCount->get_result()->fetch_assoc()['total'];
    
    // Adiciona ordenação e paginação
    $sql .= " ORDER BY data_envio DESC LIMIT ? OFFSET ?";
    $params[] = $limite;
    $params[] = $offset;
    $types .= 'ii';
    
    // Executa a query principal
    $stmt = $conn->prepare($sql);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    $demandas = [];
    while ($row = $resultado->fetch_assoc()) {
        $demandas[] = [
            'id' => $row['id'],
            'nome' => $row['nome'],
            'email' => $row['email'],
            'telefone' => $row['telefone'],
            'assunto' => $row['assunto'],
            'mensagem' => $row['mensagem'],
            'status' => $row['status'],
            'prioridade' => $row['prioridade'],
            'lida' => $row['lida'],
            'notas_internas' => $row['notas_internas'],
            'data_envio' => $row['data_envio'],
            'data_atualizacao' => $row['data_atualizacao']
        ];
    }
    
    echo json_encode([
        'sucesso' => true,
        'demandas' => $demandas,
        'paginacao' => [
            'pagina_atual' => $pagina,
            'total_registros' => $totalRegistros,
            'registros_por_pagina' => $limite,
            'total_paginas' => ceil($totalRegistros / $limite)
        ]
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Função para buscar uma demanda específica
 */
function buscarDemanda($conn) {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'ID inválido'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    $sql = "SELECT * FROM demandas WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    if ($resultado->num_rows === 0) {
        http_response_code(404);
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Demanda não encontrada'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    $demanda = $resultado->fetch_assoc();
    
    // Marca automaticamente como lida ao visualizar
    if ($demanda['lida'] == 0) {
        $sqlUpdate = "UPDATE demandas SET lida = 1, data_atualizacao = NOW() WHERE id = ?";
        $stmtUpdate = $conn->prepare($sqlUpdate);
        $stmtUpdate->bind_param("i", $id);
        $stmtUpdate->execute();
        
        $demanda['lida'] = 1;
    }
    
    echo json_encode([
        'sucesso' => true,
        'demanda' => $demanda
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Função para atualizar o status de uma demanda
 */
function atualizarStatus($conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $id = isset($input['id']) ? intval($input['id']) : 0;
    $novoStatus = isset($input['status']) ? trim($input['status']) : '';
    
    if ($id <= 0 || empty($novoStatus)) {
        http_response_code(400);
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'ID e status são obrigatórios'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    // Valida o status
    $statusPermitidos = ['pendente', 'em_andamento', 'resolvido', 'cancelado'];
    if (!in_array($novoStatus, $statusPermitidos)) {
        http_response_code(400);
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Status inválido. Use: ' . implode(', ', $statusPermitidos)
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    // Atualiza o status
    $sql = "UPDATE demandas SET status = ?, data_atualizacao = NOW() WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $novoStatus, $id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'sucesso' => true,
            'mensagem' => 'Status atualizado com sucesso',
            'novo_status' => $novoStatus
        ], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(500);
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Erro ao atualizar status'
        ], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * Função para marcar demanda como lida/não lida
 */
function marcarLida($conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $id = isset($input['id']) ? intval($input['id']) : 0;
    $lida = isset($input['lida']) ? intval($input['lida']) : 1;
    
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'ID inválido'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    $sql = "UPDATE demandas SET lida = ?, data_atualizacao = NOW() WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $lida, $id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'sucesso' => true,
            'mensagem' => 'Demanda marcada como ' . ($lida ? 'lida' : 'não lida')
        ], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(500);
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Erro ao atualizar demanda'
        ], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * Função para adicionar nota interna a uma demanda
 */
function adicionarNota($conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $id = isset($input['id']) ? intval($input['id']) : 0;
    $nota = isset($input['nota']) ? trim($input['nota']) : '';
    
    if ($id <= 0 || empty($nota)) {
        http_response_code(400);
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'ID e nota são obrigatórios'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    // Busca as notas existentes
    $sqlBusca = "SELECT notas_internas FROM demandas WHERE id = ?";
    $stmtBusca = $conn->prepare($sqlBusca);
    $stmtBusca->bind_param("i", $id);
    $stmtBusca->execute();
    $resultado = $stmtBusca->get_result();
    
    if ($resultado->num_rows === 0) {
        http_response_code(404);
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Demanda não encontrada'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    $demanda = $resultado->fetch_assoc();
    $notasExistentes = $demanda['notas_internas'];
    
    // Adiciona a nova nota com timestamp e usuário
    $dataHora = date('Y-m-d H:i:s');
    $usuario = $_SESSION['admin_nome'];
    $novaNota = "\n[{$dataHora}] {$usuario}: {$nota}";
    $notasAtualizadas = $notasExistentes . $novaNota;
    
    // Atualiza no banco
    $sql = "UPDATE demandas SET notas_internas = ?, data_atualizacao = NOW() WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $notasAtualizadas, $id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'sucesso' => true,
            'mensagem' => 'Nota adicionada com sucesso',
            'notas' => $notasAtualizadas
        ], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(500);
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Erro ao adicionar nota'
        ], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * Função para deletar uma demanda
 */
function deletarDemanda($conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    $id = isset($input['id']) ? intval($input['id']) : 0;
    
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'ID inválido'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    $sql = "DELETE FROM demandas WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'sucesso' => true,
            'mensagem' => 'Demanda deletada com sucesso'
        ], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(500);
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Erro ao deletar demanda'
        ], JSON_UNESCAPED_UNICODE);
    }
}

// Roteador de ações
switch ($action) {
    case 'listar':
        listarDemandas($conn);
        break;
        
    case 'buscar':
        buscarDemanda($conn);
        break;
        
    case 'atualizar_status':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            atualizarStatus($conn);
        } else {
            http_response_code(405);
            echo json_encode([
                'sucesso' => false,
                'mensagem' => 'Método não permitido'
            ], JSON_UNESCAPED_UNICODE);
        }
        break;
        
    case 'marcar_lida':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            marcarLida($conn);
        } else {
            http_response_code(405);
            echo json_encode([
                'sucesso' => false,
                'mensagem' => 'Método não permitido'
            ], JSON_UNESCAPED_UNICODE);
        }
        break;
        
    case 'adicionar_nota':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            adicionarNota($conn);
        } else {
            http_response_code(405);
            echo json_encode([
                'sucesso' => false,
                'mensagem' => 'Método não permitido'
            ], JSON_UNESCAPED_UNICODE);
        }
        break;
        
    case 'deletar':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            deletarDemanda($conn);
        } else {
            http_response_code(405);
            echo json_encode([
                'sucesso' => false,
                'mensagem' => 'Método não permitido'
            ], JSON_UNESCAPED_UNICODE);
        }
        break;
        
    default:
        http_response_code(400);
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Ação inválida'
        ], JSON_UNESCAPED_UNICODE);
        break;
}

$conn->close();
?>
