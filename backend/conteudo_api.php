<?php
/**
 * API para Gerenciar Conteúdo do Site
 *
 * Esta API permite obter e atualizar o conteúdo dinâmico do site,
 * como textos, imagens e outros elementos.
 *
 * Endpoints:
 * - GET /conteudo_api.php?pagina={pagina} - Obter conteúdo de uma página específica
 * - POST /conteudo_api.php?pagina={pagina} - Atualizar conteúdo de uma página específica
 */

// Inicia a sessão
session_start();

// Inclui o arquivo de conexão com o banco de dados
require_once 'db_connect.php';

// Inclui a função de verificação de autenticação
require_once 'login.php';
verificarAutenticacao();

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

// Obtém o método da requisição
$method = $_SERVER['REQUEST_METHOD'];

// Obtém o nome da página
$pagina = isset($_GET['pagina']) ? $_GET['pagina'] : null;

// Verifica se o nome da página foi fornecido
if (!$pagina) {
    http_response_code(400);
    echo json_encode([
        'sucesso' => false,
        'mensagem' => 'Nome da página é obrigatório'
    ]);
    exit();
}

// Roteador de ações
switch ($method) {
    case 'GET':
        obterConteudo($conn, $pagina);
        break;

    case 'POST':
        atualizarConteudo($conn, $pagina);
        break;

    default:
        http_response_code(405);
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Método não permitido'
        ]);
        break;
}

/**
 * Função para obter o conteúdo de uma página
 */
function obterConteudo($conn, $pagina) {
    $sql = "SELECT * FROM conteudo WHERE pagina = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $pagina);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        http_response_code(404);
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Conteúdo não encontrado para a página ' . $pagina
        ]);
        return;
    }

    $conteudo = $result->fetch_assoc();
    echo json_encode([
        'sucesso' => true,
        'conteudo' => $conteudo
    ]);
}

/**
 * Função para atualizar o conteúdo de uma página
 */
function atualizarConteudo($conn, $pagina) {
    $input = json_decode(file_get_contents('php://input'), true);

    $texto = isset($input['texto']) ? trim($input['texto']) : '';
    $imagem = isset($input['imagem']) ? trim($input['imagem']) : ''; // Caminho da imagem

    // Outros campos de conteúdo podem ser adicionados aqui

    $sql = "UPDATE conteudo SET texto = ?, imagem = ? WHERE pagina = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $texto, $imagem, $pagina);

    if ($stmt->execute()) {
        echo json_encode([
            'sucesso' => true,
            'mensagem' => 'Conteúdo da página ' . $pagina . ' atualizado com sucesso'
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Erro ao atualizar o conteúdo da página ' . $pagina
        ]);
    }
}

// Fecha a conexão com o banco de dados
$conn->close();
?>
