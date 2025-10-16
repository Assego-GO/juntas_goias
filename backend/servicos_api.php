<?php
/**
 * API CRUD Completa para Serviços
 * 
 * Este arquivo permite gerenciar os serviços exibidos no site.
 * Operações: Criar, Ler, Atualizar e Deletar serviços.
 * 
 * Endpoints:
 * - GET /servicos_api.php?action=listar - Listar todos os serviços
 * - GET /servicos_api.php?action=buscar&id=X - Buscar um serviço específico
 * - POST /servicos_api.php?action=criar - Criar novo serviço
 * - POST /servicos_api.php?action=atualizar - Atualizar serviço existente
 * - POST /servicos_api.php?action=deletar - Deletar serviço
 * - POST /servicos_api.php?action=reordenar - Reordenar serviços
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
 * Função para listar todos os serviços
 */
function listarServicos($conn) {
    $categoria = isset($_GET['categoria']) ? $_GET['categoria'] : null;
    $ativo = isset($_GET['ativo']) ? intval($_GET['ativo']) : null;
    
    // Monta a query base
    $sql = "SELECT * FROM servicos WHERE 1=1";
    $params = [];
    $types = '';
    
    // Adiciona filtros se fornecidos
    if ($categoria !== null) {
        $sql .= " AND categoria = ?";
        $params[] = $categoria;
        $types .= 's';
    }
    
    if ($ativo !== null) {
        $sql .= " AND ativo = ?";
        $params[] = $ativo;
        $types .= 'i';
    }
    
    $sql .= " ORDER BY ordem ASC, id ASC";
    
    // Prepara e executa a query
    $stmt = $conn->prepare($sql);
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    $servicos = [];
    while ($row = $resultado->fetch_assoc()) {
        $servicos[] = [
            'id' => $row['id'],
            'titulo' => $row['titulo'],
            'descricao' => $row['descricao'],
            'icone' => $row['icone'],
            'categoria' => $row['categoria'],
            'link' => $row['link'],
            'telefone' => $row['telefone'],
            'email' => $row['email'],
            'horario' => $row['horario'],
            'endereco' => $row['endereco'],
            'ativo' => $row['ativo'],
            'ordem' => $row['ordem'],
            'data_criacao' => $row['data_criacao'],
            'data_atualizacao' => $row['data_atualizacao']
        ];
    }
    
    echo json_encode([
        'sucesso' => true,
        'servicos' => $servicos,
        'total' => count($servicos)
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Função para buscar um serviço específico
 */
function buscarServico($conn) {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'ID inválido'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    $sql = "SELECT * FROM servicos WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    if ($resultado->num_rows === 0) {
        http_response_code(404);
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Serviço não encontrado'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    $servico = $resultado->fetch_assoc();
    
    echo json_encode([
        'sucesso' => true,
        'servico' => $servico
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Função para criar um novo serviço
 */
function criarServico($conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validação dos campos obrigatórios
    $camposObrigatorios = ['titulo', 'descricao', 'categoria'];
    foreach ($camposObrigatorios as $campo) {
        if (!isset($input[$campo]) || trim($input[$campo]) === '') {
            http_response_code(400);
            echo json_encode([
                'sucesso' => false,
                'mensagem' => "O campo '$campo' é obrigatório"
            ], JSON_UNESCAPED_UNICODE);
            return;
        }
    }
    
    // Extrai os dados do input
    $titulo = trim($input['titulo']);
    $descricao = trim($input['descricao']);
    $icone = isset($input['icone']) ? trim($input['icone']) : null;
    $categoria = trim($input['categoria']);
    $link = isset($input['link']) ? trim($input['link']) : null;
    $telefone = isset($input['telefone']) ? trim($input['telefone']) : null;
    $email = isset($input['email']) ? trim($input['email']) : null;
    $horario = isset($input['horario']) ? trim($input['horario']) : null;
    $endereco = isset($input['endereco']) ? trim($input['endereco']) : null;
    $ativo = isset($input['ativo']) ? intval($input['ativo']) : 1;
    
    // Busca a próxima ordem disponível
    $sqlOrdem = "SELECT MAX(ordem) as max_ordem FROM servicos";
    $resultOrdem = $conn->query($sqlOrdem);
    $rowOrdem = $resultOrdem->fetch_assoc();
    $ordem = ($rowOrdem['max_ordem'] ?? 0) + 1;
    
    // Insere o novo serviço
    $sql = "INSERT INTO servicos 
            (titulo, descricao, icone, categoria, link, telefone, email, horario, endereco, ativo, ordem, data_criacao) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "sssssssssii",
        $titulo, $descricao, $icone, $categoria, $link, 
        $telefone, $email, $horario, $endereco, $ativo, $ordem
    );
    
    if ($stmt->execute()) {
        $novoId = $conn->insert_id;
        
        echo json_encode([
            'sucesso' => true,
            'mensagem' => 'Serviço criado com sucesso',
            'id' => $novoId
        ], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(500);
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Erro ao criar serviço: ' . $conn->error
        ], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * Função para atualizar um serviço existente
 */
function atualizarServico($conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Valida o ID
    if (!isset($input['id']) || intval($input['id']) <= 0) {
        http_response_code(400);
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'ID inválido'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    $id = intval($input['id']);
    
    // Verifica se o serviço existe
    $sqlCheck = "SELECT id FROM servicos WHERE id = ?";
    $stmtCheck = $conn->prepare($sqlCheck);
    $stmtCheck->bind_param("i", $id);
    $stmtCheck->execute();
    
    if ($stmtCheck->get_result()->num_rows === 0) {
        http_response_code(404);
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Serviço não encontrado'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    // Extrai os dados do input
    $titulo = isset($input['titulo']) ? trim($input['titulo']) : null;
    $descricao = isset($input['descricao']) ? trim($input['descricao']) : null;
    $icone = isset($input['icone']) ? trim($input['icone']) : null;
    $categoria = isset($input['categoria']) ? trim($input['categoria']) : null;
    $link = isset($input['link']) ? trim($input['link']) : null;
    $telefone = isset($input['telefone']) ? trim($input['telefone']) : null;
    $email = isset($input['email']) ? trim($input['email']) : null;
    $horario = isset($input['horario']) ? trim($input['horario']) : null;
    $endereco = isset($input['endereco']) ? trim($input['endereco']) : null;
    $ativo = isset($input['ativo']) ? intval($input['ativo']) : null;
    $ordem = isset($input['ordem']) ? intval($input['ordem']) : null;
    
    // Atualiza o serviço
    $sql = "UPDATE servicos SET 
            titulo = COALESCE(?, titulo),
            descricao = COALESCE(?, descricao),
            icone = COALESCE(?, icone),
            categoria = COALESCE(?, categoria),
            link = COALESCE(?, link),
            telefone = COALESCE(?, telefone),
            email = COALESCE(?, email),
            horario = COALESCE(?, horario),
            endereco = COALESCE(?, endereco),
            ativo = COALESCE(?, ativo),
            ordem = COALESCE(?, ordem),
            data_atualizacao = NOW()
            WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "sssssssssiiii",
        $titulo, $descricao, $icone, $categoria, $link,
        $telefone, $email, $horario, $endereco, $ativo, $ordem, $id
    );
    
    if ($stmt->execute()) {
        echo json_encode([
            'sucesso' => true,
            'mensagem' => 'Serviço atualizado com sucesso'
        ], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(500);
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Erro ao atualizar serviço: ' . $conn->error
        ], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * Função para deletar um serviço
 */
function deletarServico($conn) {
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
    
    // Verifica se o serviço existe
    $sqlCheck = "SELECT id FROM servicos WHERE id = ?";
    $stmtCheck = $conn->prepare($sqlCheck);
    $stmtCheck->bind_param("i", $id);
    $stmtCheck->execute();
    
    if ($stmtCheck->get_result()->num_rows === 0) {
        http_response_code(404);
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Serviço não encontrado'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    // Deleta o serviço
    $sql = "DELETE FROM servicos WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        echo json_encode([
            'sucesso' => true,
            'mensagem' => 'Serviço deletado com sucesso'
        ], JSON_UNESCAPED_UNICODE);
    } else {
        http_response_code(500);
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Erro ao deletar serviço: ' . $conn->error
        ], JSON_UNESCAPED_UNICODE);
    }
}

/**
 * Função para reordenar serviços
 */
function reordenarServicos($conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['ordem']) || !is_array($input['ordem'])) {
        http_response_code(400);
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Formato inválido. Envie um array com a nova ordem'
        ], JSON_UNESCAPED_UNICODE);
        return;
    }
    
    // Inicia uma transação
    $conn->begin_transaction();
    
    try {
        $sql = "UPDATE servicos SET ordem = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        
        foreach ($input['ordem'] as $index => $servicoId) {
            $novaOrdem = $index + 1;
            $stmt->bind_param("ii", $novaOrdem, $servicoId);
            $stmt->execute();
        }
        
        // Confirma a transação
        $conn->commit();
        
        echo json_encode([
            'sucesso' => true,
            'mensagem' => 'Ordem dos serviços atualizada com sucesso'
        ], JSON_UNESCAPED_UNICODE);
        
    } catch (Exception $e) {
        // Reverte a transação em caso de erro
        $conn->rollback();
        
        http_response_code(500);
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Erro ao reordenar serviços: ' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
}

// Roteador de ações
switch ($action) {
    case 'listar':
        listarServicos($conn);
        break;
        
    case 'buscar':
        buscarServico($conn);
        break;
        
    case 'criar':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            criarServico($conn);
        } else {
            http_response_code(405);
            echo json_encode([
                'sucesso' => false,
                'mensagem' => 'Método não permitido'
            ], JSON_UNESCAPED_UNICODE);
        }
        break;
        
    case 'atualizar':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            atualizarServico($conn);
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
            deletarServico($conn);
        } else {
            http_response_code(405);
            echo json_encode([
                'sucesso' => false,
                'mensagem' => 'Método não permitido'
            ], JSON_UNESCAPED_UNICODE);
        }
        break;
        
    case 'reordenar':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            reordenarServicos($conn);
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
            'mensagem' => 'Ação inválida. Use: listar, buscar, criar, atualizar, deletar ou reordenar'
        ], JSON_UNESCAPED_UNICODE);
        break;
}

$conn->close();
?>
