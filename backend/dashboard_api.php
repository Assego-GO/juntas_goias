<?php
/**
 * API de Dashboard e Estatísticas
 * Arquivo: backend/dashboard_api.php
 * 
 * Fornece dados estatísticos para o painel administrativo
 */

// Headers
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Inicia sessão
session_start();

// Configuração do banco
define('DB_HOST', 'localhost');
define('DB_NAME', 'juntas_goias');
define('DB_USER', 'layane');
define('DB_PASS', '92106115@Lore');

// Função de conexão PDO
function getConexao() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        error_log("Erro de conexão: " . $e->getMessage());
        return null;
    }
}

// Função de resposta
function enviarResposta($sucesso, $mensagem = '', $dados = []) {
    $resposta = [
        'sucesso' => $sucesso,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    if ($mensagem) {
        $resposta['mensagem'] = $mensagem;
    }
    
    if (!empty($dados)) {
        $resposta = array_merge($resposta, $dados);
    }
    
    echo json_encode($resposta, JSON_UNESCAPED_UNICODE);
    exit;
}

// Função auxiliar para formatar tamanho
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

// Função auxiliar para calcular tempo decorrido
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

// Conecta ao banco
$pdo = getConexao();

if ($pdo === null) {
    enviarResposta(false, 'Erro ao conectar com banco de dados');
}

// Pega a ação
$action = $_GET['action'] ?? '';

switch ($action) {
    
    // ============================================
    // RESUMO GERAL
    // ============================================
    case 'resumo':
        try {
            $resumo = [
                'demandas' => [
                    'total' => 0,
                    'pendentes' => 0,
                    'em_andamento' => 0,
                    'resolvidos' => 0,
                    'hoje' => 0,
                    'esta_semana' => 0
                ],
                'servicos' => [
                    'total' => 0,
                    'ativos' => 0,
                    'inativos' => 0
                ],
                'imagens' => [
                    'total' => 0,
                    'banners' => 0,
                    'logos' => 0,
                    'tamanho_total' => 0,
                    'tamanho_total_formatado' => '0 B'
                ]
            ];
            
            // Tenta buscar demandas
            try {
                $sql = "SELECT 
                            COUNT(*) as total,
                            SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as pendentes,
                            SUM(CASE WHEN status = 'em_andamento' THEN 1 ELSE 0 END) as em_andamento,
                            SUM(CASE WHEN status = 'resolvido' THEN 1 ELSE 0 END) as resolvidos,
                            SUM(CASE WHEN DATE(data_envio) = CURDATE() THEN 1 ELSE 0 END) as hoje,
                            SUM(CASE WHEN YEARWEEK(data_envio) = YEARWEEK(NOW()) THEN 1 ELSE 0 END) as esta_semana
                        FROM demandas";
                
                $stmt = $pdo->query($sql);
                $dados = $stmt->fetch();
                
                if ($dados) {
                    $resumo['demandas'] = [
                        'total' => (int)$dados['total'],
                        'pendentes' => (int)$dados['pendentes'],
                        'em_andamento' => (int)$dados['em_andamento'],
                        'resolvidos' => (int)$dados['resolvidos'],
                        'hoje' => (int)$dados['hoje'],
                        'esta_semana' => (int)$dados['esta_semana']
                    ];
                }
            } catch (PDOException $e) {
                error_log("Tabela demandas não existe: " . $e->getMessage());
            }
            
            // Tenta buscar serviços
            try {
                $sql = "SELECT 
                            COUNT(*) as total,
                            SUM(CASE WHEN ativo = 1 THEN 1 ELSE 0 END) as ativos,
                            SUM(CASE WHEN ativo = 0 THEN 1 ELSE 0 END) as inativos
                        FROM servicos";
                
                $stmt = $pdo->query($sql);
                $dados = $stmt->fetch();
                
                if ($dados) {
                    $resumo['servicos'] = [
                        'total' => (int)$dados['total'],
                        'ativos' => (int)$dados['ativos'],
                        'inativos' => (int)$dados['inativos']
                    ];
                }
            } catch (PDOException $e) {
                error_log("Tabela servicos não existe: " . $e->getMessage());
            }
            
            // Tenta buscar imagens
            try {
                $sql = "SELECT 
                            COUNT(*) as total,
                            SUM(CASE WHEN tipo = 'banner' THEN 1 ELSE 0 END) as banners,
                            SUM(CASE WHEN tipo = 'logo' THEN 1 ELSE 0 END) as logos,
                            SUM(tamanho) as tamanho_total
                        FROM imagens";
                
                $stmt = $pdo->query($sql);
                $dados = $stmt->fetch();
                
                if ($dados) {
                    $resumo['imagens'] = [
                        'total' => (int)$dados['total'],
                        'banners' => (int)$dados['banners'],
                        'logos' => (int)$dados['logos'],
                        'tamanho_total' => (int)$dados['tamanho_total'],
                        'tamanho_total_formatado' => formatarTamanho($dados['tamanho_total'] ?? 0)
                    ];
                }
            } catch (PDOException $e) {
                error_log("Tabela imagens não existe: " . $e->getMessage());
            }
            
            enviarResposta(true, '', ['resumo' => $resumo]);
            
        } catch (Exception $e) {
            error_log("Erro ao buscar resumo: " . $e->getMessage());
            enviarResposta(false, 'Erro ao carregar resumo');
        }
        break;
    
    // ============================================
    // DEMANDAS RECENTES
    // ============================================
    case 'demandas_recentes':
        try {
            $limite = $_GET['limite'] ?? 10;
            $status = $_GET['status'] ?? null;
            $demandas = [];
            
            try {
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
                
                if ($status !== null) {
                    $sql .= " AND status = :status";
                    $params[':status'] = $status;
                }
                
                $sql .= " ORDER BY data_envio DESC LIMIT :limite";
                $params[':limite'] = (int)$limite;
                
                $stmt = $pdo->prepare($sql);
                
                foreach ($params as $key => $value) {
                    if ($key === ':limite') {
                        $stmt->bindValue($key, $value, PDO::PARAM_INT);
                    } else {
                        $stmt->bindValue($key, $value);
                    }
                }
                
                $stmt->execute();
                $resultado = $stmt->fetchAll();
                
                foreach ($resultado as $row) {
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
                
            } catch (PDOException $e) {
                error_log("Tabela demandas não existe: " . $e->getMessage());
            }
            
            enviarResposta(true, '', [
                'demandas' => $demandas,
                'total' => count($demandas)
            ]);
            
        } catch (Exception $e) {
            error_log("Erro ao buscar demandas recentes: " . $e->getMessage());
            enviarResposta(false, 'Erro ao carregar demandas');
        }
        break;
    
    // ============================================
    // ESTATÍSTICAS POR PERÍODO
    // ============================================
    case 'estatisticas_periodo':
        try {
            $periodo = $_GET['periodo'] ?? '30dias';
            
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
            
            $por_dia = [];
            $por_categoria = [];
            
            try {
                // Por dia
                $sql = "SELECT 
                            DATE(data_envio) as data,
                            COUNT(*) as total,
                            SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as pendentes,
                            SUM(CASE WHEN status = 'em_andamento' THEN 1 ELSE 0 END) as em_andamento,
                            SUM(CASE WHEN status = 'resolvido' THEN 1 ELSE 0 END) as resolvidos
                        FROM demandas
                        WHERE data_envio >= :data_inicio
                        GROUP BY DATE(data_envio)
                        ORDER BY data ASC";
                
                $stmt = $pdo->prepare($sql);
                $stmt->execute([':data_inicio' => $dataInicio]);
                $por_dia = $stmt->fetchAll();
                
                // Por categoria
                $sqlCat = "SELECT 
                                assunto,
                                COUNT(*) as total
                            FROM demandas
                            WHERE data_envio >= :data_inicio
                            GROUP BY assunto
                            ORDER BY total DESC";
                
                $stmtCat = $pdo->prepare($sqlCat);
                $stmtCat->execute([':data_inicio' => $dataInicio]);
                $por_categoria = $stmtCat->fetchAll();
                
            } catch (PDOException $e) {
                error_log("Erro ao buscar estatísticas: " . $e->getMessage());
            }
            
            enviarResposta(true, '', [
                'periodo' => $periodo,
                'data_inicio' => $dataInicio,
                'data_fim' => date('Y-m-d'),
                'por_dia' => $por_dia,
                'por_categoria' => $por_categoria
            ]);
            
        } catch (Exception $e) {
            error_log("Erro ao buscar estatísticas: " . $e->getMessage());
            enviarResposta(false, 'Erro ao carregar estatísticas');
        }
        break;
    
    // ============================================
    // GRÁFICO DE DEMANDAS
    // ============================================
    case 'grafico_demandas':
        try {
            $tipo = $_GET['tipo'] ?? 'mes';
            $dados = [];
            
            try {
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
                
                $stmt = $pdo->query($sql);
                $dados = $stmt->fetchAll();
                
            } catch (PDOException $e) {
                error_log("Erro ao buscar gráfico: " . $e->getMessage());
            }
            
            enviarResposta(true, '', [
                'tipo' => $tipo,
                'dados' => $dados
            ]);
            
        } catch (Exception $e) {
            error_log("Erro ao buscar gráfico: " . $e->getMessage());
            enviarResposta(false, 'Erro ao carregar gráfico');
        }
        break;
    
    // ============================================
    // AÇÃO INVÁLIDA
    // ============================================
    default:
        enviarResposta(false, 'Ação inválida. Use: resumo, demandas_recentes, estatisticas_periodo ou grafico_demandas');
}
