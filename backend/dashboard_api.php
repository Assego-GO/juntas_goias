<?php
/**
 * API de Dashboard e Estatísticas
 * 
 * Este arquivo fornece dados estatísticos para o painel administrativo.
 * Inclui métricas sobre demandas, serviços, imagens e atividades.
 * 
 * Endpoints:
 * - GET /dashboard_api.php?action=resumo - Resumo geral do sistema
 * - GET /dashboard_api.php?action=demandas_recentes - Últimas demandas recebidas
 * - GET /dashboard_api.php?action=estatisticas_periodo - Estatísticas por período
 * - GET /dashboard_api.php?action=grafico_demandas - Dados para gráfico de demandas
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
 * Função para obter resumo geral do sistema
 */
function obterResumo($conn) {
    $resumo = [];
    
    // Total de demandas
    $sqlDemandas = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as pendentes,
                        SUM(CASE WHEN status = 'em_andamento' THEN 1 ELSE 0 END) as em_andamento,
                        SUM(CASE WHEN status = 'resolvido' THEN 1 ELSE 0 END) as resolvidos,
                        SUM(CASE WHEN DATE(data_envio) = CURDATE() THEN 1 ELSE 0 END) as hoje,
                        SUM(CASE WHEN YEARWEEK(data_envio) = YEARWEEK(NOW()) THEN 1 ELSE 0 END) as esta_semana
                    FROM demandas";
    
    $resultDemandas = $conn->query($sqlDemandas);
    $resumo['demandas'] = $resultDemandas->fetch_assoc();
    
    // Total de serviços
    $sqlServicos = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN ativo = 1 THEN 1 ELSE 0 END) as ativos,
                        SUM(CASE WHEN ativo = 0 THEN 1 ELSE 0 END) as inativos
                    FROM servicos";
    
    $resultServicos = $conn->query($sqlServicos);
    $resumo['servicos'] = $resultServicos->fetch_assoc();
    
    // Total de imagens
    $sqlImagens = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN tipo = 'banner' THEN 1 ELSE 0 END) as banners,
                        SUM(CASE WHEN tipo = 'logo' THEN 1 ELSE 0 END) as logos,
                        SUM(tamanho) as tamanho_total
                    FROM imagens";
    
    $resultImagens = $conn->query($sqlImagens);
    $dadosImagens = $resultImagens->fetch_assoc();
    $dadosImagens['tamanho_total_formatado'] = formatarTamanho($dadosImagens['tamanho_total'] ?? 0);
    $resumo['imagens'] = $dadosImagens;
    
    // Última atividade
    $sqlUltimaAtividade = "SELECT 
                            'demanda' as tipo,
                            CONCAT(nome, ' - ', assunto) as descricao,
                            data_envio as data
                        FROM demandas
                        ORDER BY data_envio DESC
                        LIMIT 1";
    
    $resultAtividade = $conn->query($sqlUltimaAtividade);
    $resumo['ultima_atividade'] = $resultAtividade->fetch_assoc();
    
    echo json_encode([
        'sucesso' => true,
        'resumo' => $resumo,
        'data_hora' => date('Y-m-d H:i:s')
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Função para obter demandas recentes
 */
function obterDemandasRecentes($conn) {
    $limite = isset($_GET['limite']) ? intval($_GET['limite']) : 10;
    $status = isset($_GET['status']) ? $_GET['status'] : null;
    
    $sql = "SELECT 
                id,
                nome,
                email,
                telefone,
                assunto,
                mensagem,
                status,
                prioridade,
                data_envio,
                data_atualizacao
            FROM demandas
            WHERE 1=1";
    
    $params = [];
    $types = '';
    
    // Adiciona filtro de status se fornecido
    if ($status !== null) {
        $sql .= " AND status = ?";
        $params[] = $status;
        $types .= 's';
    }
    
    $sql .= " ORDER BY data_envio DESC LIMIT ?";
    $params[] = $limite;
    $types .= 'i';
    
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
            'data_envio' => $row['data_envio'],
            'data_atualizacao' => $row['data_atualizacao'],
            'tempo_decorrido' => calcularTempoDecorrido($row['data_envio'])
        ];
    }
    
    echo json_encode([
        'sucesso' => true,
        'demandas' => $demandas,
        'total' => count($demandas)
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Função para obter estatísticas por período
 */
function obterEstatisticasPeriodo($conn) {
    $periodo = isset($_GET['periodo']) ? $_GET['periodo'] : '30dias';
    
    // Define o período de análise
    switch ($periodo) {
        case '7dias':
            $dataInicio = date('Y-m-d', strtotime('-7 days'));
            break;
        case '30dias':
            $dataInicio = date('Y-m-d', strtotime('-30 days'));
            break;
        case '90dias':
            $dataInicio = date('Y-m-d', strtotime('-90 days'));
            break;
        case 'ano':
            $dataInicio = date('Y-m-d', strtotime('-1 year'));
            break;
        default:
            $dataInicio = date('Y-m-d', strtotime('-30 days'));
    }
    
    // Estatísticas de demandas por período
    $sql = "SELECT 
                DATE(data_envio) as data,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as pendentes,
                SUM(CASE WHEN status = 'em_andamento' THEN 1 ELSE 0 END) as em_andamento,
                SUM(CASE WHEN status = 'resolvido' THEN 1 ELSE 0 END) as resolvidos
            FROM demandas
            WHERE data_envio >= ?
            GROUP BY DATE(data_envio)
            ORDER BY data ASC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $dataInicio);
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    $estatisticas = [];
    while ($row = $resultado->fetch_assoc()) {
        $estatisticas[] = $row;
    }
    
    // Demandas por categoria/tipo
    $sqlCategorias = "SELECT 
                        assunto,
                        COUNT(*) as total
                    FROM demandas
                    WHERE data_envio >= ?
                    GROUP BY assunto
                    ORDER BY total DESC";
    
    $stmtCat = $conn->prepare($sqlCategorias);
    $stmtCat->bind_param("s", $dataInicio);
    $stmtCat->execute();
    $resultCat = $stmtCat->get_result();
    
    $categorias = [];
    while ($row = $resultCat->fetch_assoc()) {
        $categorias[] = $row;
    }
    
    echo json_encode([
        'sucesso' => true,
        'periodo' => $periodo,
        'data_inicio' => $dataInicio,
        'data_fim' => date('Y-m-d'),
        'por_dia' => $estatisticas,
        'por_categoria' => $categorias
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Função para obter dados para gráfico de demandas
 */
function obterGraficoDemandas($conn) {
    $tipo = isset($_GET['tipo']) ? $_GET['tipo'] : 'mes'; // mes, semana, ano
    
    switch ($tipo) {
        case 'semana':
            $sql = "SELECT 
                        DATE(data_envio) as data,
                        DAYNAME(data_envio) as dia_semana,
                        COUNT(*) as total
                    FROM demandas
                    WHERE data_envio >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                    GROUP BY DATE(data_envio), DAYNAME(data_envio)
                    ORDER BY data ASC";
            break;
            
        case 'ano':
            $sql = "SELECT 
                        DATE_FORMAT(data_envio, '%Y-%m') as mes,
                        COUNT(*) as total
                    FROM demandas
                    WHERE data_envio >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                    GROUP BY DATE_FORMAT(data_envio, '%Y-%m')
                    ORDER BY mes ASC";
            break;
            
        case 'mes':
        default:
            $sql = "SELECT 
                        DATE(data_envio) as data,
                        COUNT(*) as total
                    FROM demandas
                    WHERE data_envio >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    GROUP BY DATE(data_envio)
                    ORDER BY data ASC";
            break;
    }
    
    $resultado = $conn->query($sql);
    
    $dados = [];
    while ($row = $resultado->fetch_assoc()) {
        $dados[] = $row;
    }
    
    echo json_encode([
        'sucesso' => true,
        'tipo' => $tipo,
        'dados' => $dados
    ], JSON_UNESCAPED_UNICODE);
}

/**
 * Função auxiliar para formatar tamanho em bytes
 */
function formatarTamanho($bytes) {
    if ($bytes == 0) return '0 B';
    
    $unidades = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    
    while ($bytes >= 1024 && $i < count($unidades) - 1) {
        $bytes /= 1024;
        $i++;
    }
    
    return round($bytes, 2) . ' ' . $unidades[$i];
}

/**
 * Função auxiliar para calcular tempo decorrido
 */
function calcularTempoDecorrido($dataHora) {
    $agora = new DateTime();
    $data = new DateTime($dataHora);
    $diferenca = $agora->diff($data);
    
    if ($diferenca->d > 0) {
        return $diferenca->d . ' dia' . ($diferenca->d > 1 ? 's' : '');
    } elseif ($diferenca->h > 0) {
        return $diferenca->h . ' hora' . ($diferenca->h > 1 ? 's' : '');
    } elseif ($diferenca->i > 0) {
        return $diferenca->i . ' minuto' . ($diferenca->i > 1 ? 's' : '');
    } else {
        return 'Agora mesmo';
    }
}

// Roteador de ações
switch ($action) {
    case 'resumo':
        obterResumo($conn);
        break;
        
    case 'demandas_recentes':
        obterDemandasRecentes($conn);
        break;
        
    case 'estatisticas_periodo':
        obterEstatisticasPeriodo($conn);
        break;
        
    case 'grafico_demandas':
        obterGraficoDemandas($conn);
        break;
        
    default:
        http_response_code(400);
        echo json_encode([
            'sucesso' => false,
            'mensagem' => 'Ação inválida. Use: resumo, demandas_recentes, estatisticas_periodo ou grafico_demandas'
        ], JSON_UNESCAPED_UNICODE);
        break;
}

$conn->close();
?>
