<?php
/**
 * Configuração de Conexão com o Banco de Dados
 * Juntas Goiás - Sistema de Apoio a Mulheres Vítimas de Violência
 */

class Database {
    // Configurações do banco de dados
    private $host = "localhost";
    private $db_name = "juntas_goias";
    private $username = "layane";
    private $password = "92106115@Lore";
    private $charset = "utf8mb4";
    private $port = "3306";
    
    public $conn;

    /**
     * Obtém a conexão PDO com o banco de dados
     * @return PDO|null Conexão PDO ou null em caso de erro
     */
    public function getConnection() {
        $this->conn = null;

        try {
            // String de conexão DSN
            $dsn = "mysql:host=" . $this->host . 
                   ";port=" . $this->port .
                   ";dbname=" . $this->db_name . 
                   ";charset=" . $this->charset;

            // Opções de configuração do PDO
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];

            // Criar conexão PDO
            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
            
            // Log de conexão bem-sucedida (apenas em desenvolvimento)
            if ($_SERVER['SERVER_NAME'] === 'localhost') {
                error_log("Conexão com banco de dados estabelecida com sucesso");
            }
            
        } catch(PDOException $e) {
            // Log do erro
            error_log("Erro de conexão PDO: " . $e->getMessage());
            
            // Em produção, não expor detalhes do erro
            if ($_SERVER['SERVER_NAME'] === 'localhost') {
                throw new Exception("Erro de conexão: " . $e->getMessage());
            } else {
                throw new Exception("Erro ao conectar com o banco de dados");
            }
        }

        return $this->conn;
    }

    /**
     * Fecha a conexão com o banco de dados
     */
    public function closeConnection() {
        $this->conn = null;
    }

    /**
     * Testa a conexão com o banco
     * @return bool True se conectado, False caso contrário
     */
    public function testConnection() {
        try {
            $this->getConnection();
            return $this->conn !== null;
        } catch (Exception $e) {
            return false;
        }
    }
}

/**
 * Classe para respostas JSON padronizadas
 */
class JsonResponse {
    /**
     * Envia resposta de sucesso
     */
    public static function success($data = [], $message = "Operação realizada com sucesso", $statusCode = 200) {
        http_response_code($statusCode);
        echo json_encode([
            'status' => 'success',
            'message' => $message,
            'data' => $data
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Envia resposta de erro
     */
    public static function error($message = "Ocorreu um erro", $statusCode = 400, $errors = []) {
        http_response_code($statusCode);
        echo json_encode([
            'status' => 'error',
            'message' => $message,
            'errors' => $errors
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Envia resposta não autorizada
     */
    public static function unauthorized($message = "Não autorizado") {
        self::error($message, 401);
    }

    /**
     * Envia resposta não encontrado
     */
    public static function notFound($message = "Recurso não encontrado") {
        self::error($message, 404);
    }
}
?>
