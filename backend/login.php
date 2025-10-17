<?php
/**
 * API de Autenticação - VERSÃO DE TESTE
 * SEM dependências externas
 */

// ============================================
// HEADERS - SEMPRE PRIMEIRO
// ============================================
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ============================================
// CONFIGURAÇÕES
// ============================================
error_reporting(E_ALL);
ini_set('display_errors', 0); // Não mostrar erros na tela

// Inicia sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// CONFIGURAÇÃO DO BANCO
// ============================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'juntas_goias');
define('DB_USER', 'layane');
define('DB_PASS', '92106115@Lore');

// ============================================
// FUNÇÃO DE CONEXÃO
// ============================================
function getConexao() {
    try {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ];
        
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        return $pdo;
        
    } catch (PDOException $e) {
        error_log("Erro de conexão: " . $e->getMessage());
        return null;
    }
}

// ============================================
// FUNÇÃO DE RESPOSTA
// ============================================
function enviarResposta($sucesso, $mensagem, $dados = []) {
    $resposta = [
        'sucesso' => $sucesso,
        'mensagem' => $mensagem,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    if (!empty($dados)) {
        $resposta = array_merge($resposta, $dados);
    }
    
    echo json_encode($resposta, JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================
// FUNÇÃO DE LOG
// ============================================
function registrarLog($pdo, $adminId, $acao, $modulo, $descricao) {
    try {
        $sql = "INSERT INTO logs_sistema (admin_id, acao, modulo, descricao, ip_address, user_agent) 
                VALUES (:admin_id, :acao, :modulo, :descricao, :ip_address, :user_agent)";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':admin_id' => $adminId,
            ':acao' => $acao,
            ':modulo' => $modulo,
            ':descricao' => $descricao,
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    } catch (PDOException $e) {
        error_log("Erro ao registrar log: " . $e->getMessage());
    }
}

// ============================================
// LOGIN
// ============================================
function fazerLogin($pdo) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        enviarResposta(false, 'JSON inválido');
    }
    
    $usuario = trim($input['usuario'] ?? '');
    $senha = $input['senha'] ?? '';
    
    if (empty($usuario) || empty($senha)) {
        enviarResposta(false, 'Usuário e senha são obrigatórios');
    }
    
    try {
        $sql = "SELECT id, usuario, senha, nome, email, ativo 
                FROM administradores 
                WHERE usuario = :usuario 
                LIMIT 1";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':usuario' => $usuario]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$admin) {
            registrarLog($pdo, null, 'login_falha', 'login', "Usuário inexistente: $usuario");
            sleep(1);
            enviarResposta(false, 'Usuário ou senha incorretos');
        }
        
        if ($admin['ativo'] != 1) {
            registrarLog($pdo, $admin['id'], 'login_falha', 'login', "Usuário inativo: $usuario");
            enviarResposta(false, 'Usuário inativo');
        }
        
        if (!password_verify($senha, $admin['senha'])) {
            registrarLog($pdo, $admin['id'], 'login_falha', 'login', "Senha incorreta: $usuario");
            sleep(1);
            enviarResposta(false, 'Usuário ou senha incorretos');
        }
        
        // Login OK
        session_regenerate_id(true);
        
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_usuario'] = $admin['usuario'];
        $_SESSION['admin_nome'] = $admin['nome'];
        $_SESSION['admin_email'] = $admin['email'];
        $_SESSION['admin_logado'] = true;
        $_SESSION['login_timestamp'] = time();
        
        $sqlUpdate = "UPDATE administradores SET ultimo_acesso = NOW() WHERE id = :id";
        $stmtUpdate = $pdo->prepare($sqlUpdate);
        $stmtUpdate->execute([':id' => $admin['id']]);
        
        registrarLog($pdo, $admin['id'], 'login_sucesso', 'login', "Login bem-sucedido");
        
        unset($admin['senha']);
        
        enviarResposta(true, 'Login realizado com sucesso', [
            'admin' => $admin
        ]);
        
    } catch (PDOException $e) {
        error_log("Erro no login: " . $e->getMessage());
        enviarResposta(false, 'Erro ao processar login');
    }
}

// ============================================
// LOGOUT
// ============================================
function fazerLogout($pdo) {
    if (isset($_SESSION['admin_id'])) {
        registrarLog($pdo, $_SESSION['admin_id'], 'logout', 'login', "Logout realizado");
    }
    
    $_SESSION = [];
    
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    session_destroy();
    
    enviarResposta(true, 'Logout realizado com sucesso');
}

// ============================================
// VERIFICAR SESSÃO
// ============================================
function verificarSessao($pdo) {
    if (!isset($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
        enviarResposta(true, 'Não há sessão ativa', ['logado' => false]);
    }
    
    $tempoMaximoInatividade = 2 * 60 * 60;
    
    if (isset($_SESSION['login_timestamp'])) {
        $tempoDecorrido = time() - $_SESSION['login_timestamp'];
        
        if ($tempoDecorrido > $tempoMaximoInatividade) {
            registrarLog($pdo, $_SESSION['admin_id'], 'sessao_expirada', 'login', "Sessão expirada");
            session_destroy();
            enviarResposta(true, 'Sessão expirada', ['logado' => false, 'expirada' => true]);
        }
    }
    
    $_SESSION['login_timestamp'] = time();
    
    try {
        $sql = "SELECT id, usuario, nome, email, ativo 
                FROM administradores 
                WHERE id = :id 
                LIMIT 1";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $_SESSION['admin_id']]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($admin) {
            if ($admin['ativo'] != 1) {
                session_destroy();
                enviarResposta(true, 'Usuário desativado', ['logado' => false]);
            }
            
            enviarResposta(true, 'Sessão ativa', ['logado' => true, 'admin' => $admin]);
        } else {
            session_destroy();
            enviarResposta(true, 'Usuário não encontrado', ['logado' => false]);
        }
        
    } catch (PDOException $e) {
        error_log("Erro ao verificar sessão: " . $e->getMessage());
        enviarResposta(false, 'Erro ao verificar sessão');
    }
}

// ============================================
// ROTEADOR
// ============================================
$pdo = getConexao();

if ($pdo === null) {
    enviarResposta(false, 'Erro ao conectar com banco de dados');
}

$action = $_GET['action'] ?? '';

switch($action) {
    case 'login':
        fazerLogin($pdo);
        break;
    
    case 'logout':
        fazerLogout($pdo);
        break;
    
    case 'verificar':
    case 'check':
        verificarSessao($pdo);
        break;
    
    default:
        enviarResposta(false, 'Ação inválida');
}

/**
 * Fim do arquivo login.php
 */