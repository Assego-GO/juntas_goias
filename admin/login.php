<?php
/**
 * Sistema de Autenticação do Painel Administrativo
 * 
 * Este arquivo gerencia o login e logout dos administradores.
 * Utiliza sessões PHP para manter o usuário autenticado.
 * 
 * Endpoints:
 * - POST /login.php?action=login - Fazer login
 * - POST /login.php?action=logout - Fazer logout
 * - GET /login.php?action=check - Verificar se está logado
 */

// Inicia a sessão
session_start();

// Inclui o arquivo de conexão com o banco de dados
require_once 'db_connect.php';

// Configura cabeçalhos para API JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Trata requisições OPTIONS (preflight CORS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Obtém a ação solicitada (login, logout, check)
$action = isset($_GET['action']) ? $_GET['action'] : '';

/**
 * Função para fazer login
 * Verifica usuário e senha no banco de dados
 */
function fazerLogin($conn) {
    // Pega os dados enviados via POST
    $input = json_decode(file_get_contents('php://input'), true);
    
    $usuario = isset($input['usuario']) ? trim($input['usuario']) : '';
    $senha = isset($input['senha']) ? trim($input['senha']) : '';
    
    // Valida se usuário e senha foram preenchidos
    if (empty($usuario) || empty($senha)) {
        http_response_code(400);
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Usuário e senha são obrigatórios'
        ]);
        return;
    }
    
    // Busca o administrador no banco de dados
    $sql = "SELECT id, usuario, senha, nome, email 
            FROM administradores 
            WHERE usuario = ? AND ativo = 1 
            LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $usuario);
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    // Verifica se encontrou o usuário
    if ($resultado->num_rows === 0) {
        http_response_code(401);
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Usuário ou senha incorretos'
        ]);
        return;
    }
    
    $admin = $resultado->fetch_assoc();
    
    // Verifica se a senha está correta
    // IMPORTANTE: As senhas devem estar criptografadas com password_hash() no banco
    if (!password_verify($senha, $admin['senha'])) {
        http_response_code(401);
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Usuário ou senha incorretos'
        ]);
        return;
    }
    
    // Login bem-sucedido! Salva os dados na sessão
    $_SESSION['admin_logado'] = true;
    $_SESSION['admin_id'] = $admin['id'];
    $_SESSION['admin_usuario'] = $admin['usuario'];
    $_SESSION['admin_nome'] = $admin['nome'];
    $_SESSION['admin_email'] = $admin['email'];
    
    // Atualiza o último acesso no banco
    $sqlUpdate = "UPDATE administradores SET ultimo_acesso = NOW() WHERE id = ?";
    $stmtUpdate = $conn->prepare($sqlUpdate);
    $stmtUpdate->bind_param("i", $admin['id']);
    $stmtUpdate->execute();
    
    // Retorna sucesso com os dados do administrador
    echo json_encode([
        'sucesso' => true,
        'mensagem' => 'Login realizado com sucesso',
        'admin' => [
            'id' => $admin['id'],
            'usuario' => $admin['usuario'],
            'nome' => $admin['nome'],
            'email' => $admin['email']
        ]
    ]);
}

/**
 * Função para fazer logout
 * Destroi a sessão do usuário
 */
function fazerLogout() {
    // Destroi todas as variáveis de sessão
    $_SESSION = array();
    
    // Destroi a sessão
    session_destroy();
    
    echo json_encode([
        'sucesso' => true,
        'mensagem' => 'Logout realizado com sucesso'
    ]);
}

/**
 * Função para verificar se o usuário está logado
 * Retorna os dados do admin se estiver autenticado
 */
function verificarLogin() {
    if (isset($_SESSION['admin_logado']) && $_SESSION['admin_logado'] === true) {
        echo json_encode([
            'logado' => true,
            'admin' => [
                'id' => $_SESSION['admin_id'],
                'usuario' => $_SESSION['admin_usuario'],
                'nome' => $_SESSION['admin_nome'],
                'email' => $_SESSION['admin_email']
            ]
        ]);
    } else {
        http_response_code(401);
        echo json_encode([
            'logado' => false,
            'mensagem' => 'Não autenticado'
        ]);
    }
}

/**
 * Função auxiliar para verificar autenticação em outras APIs
 * Outras APIs devem incluir este arquivo e chamar esta função
 */
function verificarAutenticacao() {
    if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
        http_response_code(401);
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Acesso não autorizado. Faça login primeiro.'
        ]);
        exit();
    }
}

// Roteador de ações
switch ($action) {
    case 'login':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            fazerLogin($conn);
        } else {
            http_response_code(405);
            echo json_encode([
                'sucesso' => false,
                'mensagem' => 'Método não permitido'
            ]);
        }
        break;
        
    case 'logout':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            fazerLogout();
        } else {
            http_response_code(405);
            echo json_encode([
                'sucesso' => false,
                'mensagem' => 'Método não permitido'
            ]);
        }
        break;
        
    case 'check':
        verificarLogin();
        break;
        
    default:
        http_response_code(400);
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Ação inválida. Use: login, logout ou check'
        ]);
        break;
}

// Fecha a conexão com o banco de dados
$conn->close();
?>
