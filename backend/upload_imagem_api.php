<?php
/**
 * API para Upload e Gerenciamento de Imagens
 * 
 * Este arquivo permite fazer upload de banners e outras imagens do site.
 * Também permite listar e deletar imagens existentes.
 * 
 * Endpoints:
 * - POST /upload_imagem_api.php?action=upload - Fazer upload de imagem
 * - GET /upload_imagem_api.php?action=listar&tipo=banner - Listar imagens
 * - POST /upload_imagem_api.php?action=deletar - Deletar imagem
 * - POST /upload_imagem_api.php?action=renomear - Renomear/substituir imagem
 * 
 * SEGURANÇA: Requer autenticação de administrador
 */

// Inicia a sessão
session_start();

// Inclui o sistema de autenticação
require_once 'login.php';
require_once 'db_connect.php';

// Verifica se o usuário está autenticado
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

// Configurações de upload
define('UPLOAD_DIR', '../frontend/'); // Diretório onde ficam as imagens
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_TYPES', ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp']);
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

$action = isset($_GET['action']) ? $_GET['action'] : '';

/**
 * Função para validar e fazer upload de uma imagem
 */
function fazerUpload($conn) {
    // Verifica se há arquivo enviado
    if (!isset($_FILES['imagem']) || $_FILES['imagem']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Nenhum arquivo foi enviado ou ocorreu um erro no upload'
        ]);
        return;
    }
    
    $arquivo = $_FILES['imagem'];
    $tipo = isset($_POST['tipo']) ? $_POST['tipo'] : 'geral'; // banner, logo, geral
    $nomeCustomizado = isset($_POST['nome']) ? $_POST['nome'] : '';
    
    // Valida o tamanho do arquivo
    if ($arquivo['size'] > MAX_FILE_SIZE) {
        http_response_code(400);
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'O arquivo é muito grande. Tamanho máximo: 5MB'
        ]);
        return;
    }
    
    // Valida o tipo MIME
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $arquivo['tmp_name']);
    finfo_close($finfo);
    
    if (!in_array($mimeType, ALLOWED_TYPES)) {
        http_response_code(400);
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Tipo de arquivo não permitido. Use JPG, PNG, GIF ou WebP'
        ]);
        return;
    }
    
    // Valida a extensão
    $extensao = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));
    if (!in_array($extensao, ALLOWED_EXTENSIONS)) {
        http_response_code(400);
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Extensão de arquivo não permitida'
        ]);
        return;
    }
    
    // Define o nome do arquivo
    if (!empty($nomeCustomizado)) {
        // Remove caracteres especiais do nome customizado
        $nomeArquivo = preg_replace('/[^a-zA-Z0-9_-]/', '', $nomeCustomizado);
        $nomeArquivo = $nomeArquivo . '.' . $extensao;
    } else {
        // Gera um nome único
        $nomeArquivo = uniqid() . '_' . time() . '.' . $extensao;
    }
    
    // Para banners, usa nomes específicos (banner1, banner2, banner3)
    if ($tipo === 'banner') {
        $numeroBanner = isset($_POST['numero']) ? intval($_POST['numero']) : 1;
        $nomeArquivo = 'banner' . $numeroBanner . '.' . $extensao;
        
        // Se já existe um arquivo com este nome, faz backup
        $caminhoCompleto = UPLOAD_DIR . $nomeArquivo;
        if (file_exists($caminhoCompleto)) {
            $backup = UPLOAD_DIR . 'backup_' . $nomeArquivo . '_' . time() . '.' . $extensao;
            rename($caminhoCompleto, $backup);
        }
    }
    
    // Move o arquivo para o diretório de upload
    $caminhoDestino = UPLOAD_DIR . $nomeArquivo;
    
    if (!move_uploaded_file($arquivo['tmp_name'], $caminhoDestino)) {
        http_response_code(500);
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Erro ao salvar o arquivo no servidor'
        ]);
        return;
    }
    
    // Registra o upload no banco de dados
    $sql = "INSERT INTO imagens (nome_arquivo, tipo, caminho, tamanho, admin_id, data_upload) 
            VALUES (?, ?, ?, ?, ?, NOW())";
    
    $stmt = $conn->prepare($sql);
    $caminho = 'frontend/' . $nomeArquivo;
    $tamanho = $arquivo['size'];
    $adminId = $_SESSION['admin_id'];
    
    $stmt->bind_param("sssii", $nomeArquivo, $tipo, $caminho, $tamanho, $adminId);
    $stmt->execute();
    
    // Retorna sucesso
    echo json_encode([
        'sucesso' => true,
        'mensagem' => 'Imagem enviada com sucesso',
        'arquivo' => [
            'nome' => $nomeArquivo,
            'tipo' => $tipo,
            'caminho' => $caminho,
            'tamanho' => $tamanho,
            'url' => '../frontend/' . $nomeArquivo
        ]
    ]);
}

/**
 * Função para listar imagens
 */
function listarImagens($conn) {
    $tipo = isset($_GET['tipo']) ? $_GET['tipo'] : 'todos';
    
    if ($tipo === 'todos') {
        $sql = "SELECT * FROM imagens ORDER BY data_upload DESC";
        $stmt = $conn->prepare($sql);
    } else {
        $sql = "SELECT * FROM imagens WHERE tipo = ? ORDER BY data_upload DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $tipo);
    }
    
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    $imagens = [];
    while ($row = $resultado->fetch_assoc()) {
        $imagens[] = [
            'id' => $row['id'],
            'nome' => $row['nome_arquivo'],
            'tipo' => $row['tipo'],
            'caminho' => $row['caminho'],
            'url' => '../' . $row['caminho'],
            'tamanho' => $row['tamanho'],
            'tamanho_formatado' => formatarTamanho($row['tamanho']),
            'data_upload' => $row['data_upload']
        ];
    }
    
    echo json_encode([
        'sucesso' => true,
        'imagens' => $imagens,
        'total' => count($imagens)
    ]);
}

/**
 * Função para deletar uma imagem
 */
function deletarImagem($conn) {
    $input = json_decode(file_get_contents('php://input'), true);
    $id = isset($input['id']) ? intval($input['id']) : 0;
    
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'ID inválido'
        ]);
        return;
    }
    
    // Busca a imagem no banco
    $sql = "SELECT nome_arquivo, caminho FROM imagens WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    if ($resultado->num_rows === 0) {
        http_response_code(404);
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Imagem não encontrada'
        ]);
        return;
    }
    
    $imagem = $resultado->fetch_assoc();
    
    // Deleta o arquivo físico
    $caminhoCompleto = '../' . $imagem['caminho'];
    if (file_exists($caminhoCompleto)) {
        unlink($caminhoCompleto);
    }
    
    // Remove do banco de dados
    $sqlDelete = "DELETE FROM imagens WHERE id = ?";
    $stmtDelete = $conn->prepare($sqlDelete);
    $stmtDelete->bind_param("i", $id);
    $stmtDelete->execute();
    
    echo json_encode([
        'sucesso' => true,
        'mensagem' => 'Imagem deletada com sucesso'
    ]);
}

/**
 * Função auxiliar para formatar tamanho em bytes
 */
function formatarTamanho($bytes) {
    $unidades = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    
    while ($bytes >= 1024 && $i < count($unidades) - 1) {
        $bytes /= 1024;
        $i++;
    }
    
    return round($bytes, 2) . ' ' . $unidades[$i];
}

// Roteador de ações
switch ($action) {
    case 'upload':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            fazerUpload($conn);
        } else {
            http_response_code(405);
            echo json_encode([
                'sucesso' => false,
                'mensagem' => 'Método não permitido'
            ]);
        }
        break;
        
    case 'listar':
        listarImagens($conn);
        break;
        
    case 'deletar':
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            deletarImagem($conn);
        } else {
            http_response_code(405);
            echo json_encode([
                'sucesso' => false,
                'mensagem' => 'Método não permitido'
            ]);
        }
        break;
        
    default:
        http_response_code(400);
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Ação inválida. Use: upload, listar ou deletar'
        ]);
        break;
}

$conn->close();
?>
