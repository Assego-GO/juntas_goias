-- =====================================================
-- Script de Atualização do Banco de Dados
-- Adiciona tabelas e campos necessários para o painel administrativo
-- =====================================================

-- Usar o banco de dados
USE juntas_goias;

-- =====================================================
-- 1. TABELA DE ADMINISTRADORES
-- Armazena os usuários que podem acessar o painel
-- =====================================================

CREATE TABLE IF NOT EXISTS administradores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario VARCHAR(50) UNIQUE NOT NULL COMMENT 'Nome de usuário para login',
    senha VARCHAR(255) NOT NULL COMMENT 'Senha criptografada com password_hash()',
    nome VARCHAR(100) NOT NULL COMMENT 'Nome completo do administrador',
    email VARCHAR(100) UNIQUE NOT NULL COMMENT 'Email do administrador',
    ativo TINYINT(1) DEFAULT 1 COMMENT '1 = Ativo, 0 = Inativo',
    ultimo_acesso DATETIME NULL COMMENT 'Data e hora do último login',
    data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Data de criação do usuário',
    INDEX idx_usuario (usuario),
    INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Usuários administradores do sistema';

-- Insere um administrador padrão (usuário: admin, senha: admin123)
-- IMPORTANTE: Altere esta senha após o primeiro acesso!
INSERT INTO administradores (usuario, senha, nome, email) 
VALUES (
    'admin',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', -- Senha: admin123
    'Administrador',
    'admin@juntasgoias.com'
) ON DUPLICATE KEY UPDATE usuario = usuario; -- Use ON DUPLICATE KEY UPDATE to avoid errors if the admin already exists

-- =====================================================
-- 2. TABELA DE IMAGENS
-- Armazena informações sobre imagens enviadas (banners, logos, etc.)
-- =====================================================

CREATE TABLE IF NOT EXISTS imagens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome_arquivo VARCHAR(255) NOT NULL COMMENT 'Nome do arquivo da imagem',
    tipo ENUM('banner', 'logo', 'geral') DEFAULT 'geral' COMMENT 'Tipo da imagem',
    caminho VARCHAR(500) NOT NULL COMMENT 'Caminho relativo da imagem',
    tamanho INT NOT NULL COMMENT 'Tamanho do arquivo em bytes',
    admin_id INT NULL COMMENT 'ID do administrador que fez o upload',
    data_upload DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Data do upload',
    FOREIGN KEY (admin_id) REFERENCES administradores(id) ON DELETE SET NULL,
    INDEX idx_tipo (tipo),
    INDEX idx_data_upload (data_upload)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Imagens do sistema';

-- =====================================================
-- 3. ATUALIZAR TABELA DE DEMANDAS
-- Adiciona campos para melhor gerenciamento
-- =====================================================

ALTER TABLE demandas 
ADD COLUMN IF NOT EXISTS `status` ENUM('pendente', 'em_andamento', 'resolvido', 'cancelado') 
DEFAULT 'pendente' 
COMMENT 'Status da demanda' 
AFTER mensagem;

ALTER TABLE demandas 
ADD COLUMN IF NOT EXISTS prioridade ENUM('baixa', 'media', 'alta', 'urgente') 
DEFAULT 'media' 
COMMENT 'Prioridade da demanda' 
AFTER `status`;

ALTER TABLE demandas 
ADD COLUMN IF NOT EXISTS lida TINYINT(1) DEFAULT 0 
COMMENT '0 = Não lida, 1 = Lida' 
AFTER prioridade;

ALTER TABLE demandas 
ADD COLUMN IF NOT EXISTS notas_internas TEXT NULL 
COMMENT 'Notas internas dos administradores' 
AFTER lida;

ALTER TABLE demandas 
ADD COLUMN IF NOT EXISTS data_atualizacao DATETIME NULL 
COMMENT 'Data da última atualização' 
AFTER data_envio;

CREATE INDEX IF NOT EXISTS idx_status ON demandas(`status`);
CREATE INDEX IF NOT EXISTS idx_lida ON demandas(lida);
CREATE INDEX IF NOT EXISTS idx_prioridade ON demandas(prioridade);
CREATE INDEX IF NOT EXISTS idx_data_envio ON demandas(data_envio);

-- =====================================================
-- 4. ATUALIZAR TABELA DE SERVIÇOS
-- Adiciona campos para melhor controle
-- =====================================================

ALTER TABLE servicos 
ADD COLUMN IF NOT EXISTS ordem INT DEFAULT 0 
COMMENT 'Ordem de exibição dos serviços' 
AFTER ativo;

ALTER TABLE servicos 
ADD COLUMN IF NOT EXISTS ativo TINYINT(1) DEFAULT 1 
COMMENT '1 = Ativo, 0 = Inativo';

ALTER TABLE servicos 
ADD COLUMN IF NOT EXISTS data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP 
COMMENT 'Data de criação do serviço';

ALTER TABLE servicos 
ADD COLUMN IF NOT EXISTS data_atualizacao DATETIME NULL 
COMMENT 'Data da última atualização' 
AFTER data_criacao;

CREATE INDEX IF NOT EXISTS idx_ordem ON servicos(ordem);
CREATE INDEX IF NOT EXISTS idx_ativo ON servicos(ativo);

-- =====================================================
-- 5. TABELA DE LOG DE ATIVIDADES (Opcional, mas recomendado)
-- Registra ações importantes no painel
-- =====================================================

CREATE TABLE IF NOT EXISTS log_atividades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NULL COMMENT 'ID do administrador que realizou a ação',
    acao VARCHAR(100) NOT NULL COMMENT 'Tipo de ação realizada',
    tabela VARCHAR(50) NULL COMMENT 'Tabela afetada',
    registro_id INT NULL COMMENT 'ID do registro afetado',
    descricao TEXT NULL COMMENT 'Descrição detalhada da ação',
    ip_address VARCHAR(45) NULL COMMENT 'Endereço IP do administrador',
    user_agent TEXT NULL COMMENT 'User agent do navegador',
    data_acao DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT 'Data e hora da ação',
    FOREIGN KEY (admin_id) REFERENCES administradores(id) ON DELETE SET NULL,
    INDEX idx_admin_id (admin_id),
    INDEX idx_data_acao (data_acao),
    INDEX idx_acao (acao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Log de atividades dos administradores';

-- =====================================================
-- 6. INSERIR DADOS DE EXEMPLO (Opcional)
-- =====================================================

-- Inserir alguns serviços de exemplo se a tabela estiver vazia
INSERT INTO servicos (titulo, descricao, icone, categoria, ordem, ativo) 
SELECT 
        'Delegacia Especializada (DEAM)' ,
        'Registre sua ocorrência e solicite medidas protetivas' ,
        'shield' ,
        'seguranca' ,
        1 ,
        1 
    FROM (SELECT 1) AS tmp
WHERE NOT EXISTS (SELECT 1 FROM servicos LIMIT 1);

-- =====================================================
-- 7. TRIGGER PARA ATUALIZAR data_atualizacao AUTOMATICAMENTE
-- =====================================================

-- Trigger para demandas
DELIMITER //
CREATE TRIGGER tr_demandas_update 
BEFORE UPDATE ON demandas
FOR EACH ROW
BEGIN
    SET NEW.data_atualizacao = NOW();
END//

-- Trigger para servicos
CREATE TRIGGER tr_servicos_update 
BEFORE UPDATE ON servicos
FOR EACH ROW
BEGIN
    SET NEW.data_atualizacao = NOW();
END//
DELIMITER ;

-- =====================================================
-- 8. VIEW PARA ESTATÍSTICAS RÁPIDAS (Opcional)
-- =====================================================

CREATE OR REPLACE VIEW vw_estatisticas_demandas AS
SELECT 
    COUNT(*) as total_demandas,
    SUM(CASE WHEN status = 'pendente' THEN 1 ELSE 0 END) as pendentes,
    SUM(CASE WHEN status = 'em_andamento' THEN 1 ELSE 0 END) as em_andamento,
    SUM(CASE WHEN status = 'resolvido' THEN 1 ELSE 0 END) as resolvidos,
    SUM(CASE WHEN lida = 0 THEN 1 ELSE 0 END) as nao_lidas,
    SUM(CASE WHEN DATE(data_envio) = CURDATE() THEN 1 ELSE 0 END) as hoje,
    SUM(CASE WHEN YEARWEEK(data_envio) = YEARWEEK(NOW()) THEN 1 ELSE 0 END) as esta_semana,
    SUM(CASE WHEN MONTH(data_envio) = MONTH(NOW()) AND YEAR(data_envio) = YEAR(NOW()) THEN 1 ELSE 0 END) as este_mes
FROM demandas;

-- =====================================================
-- FIM DO SCRIPT
-- =====================================================

-- Exibe mensagem de sucesso
SELECT 'Banco de dados atualizado com sucesso!' as Mensagem;

-- Exibe informações sobre o administrador padrão
SELECT 
    'ATENÇÃO: Usuário padrão criado' as Alerta,
    'Usuário: admin' as Usuario,
    'Senha: admin123' as Senha,
    'ALTERE A SENHA APÓS O PRIMEIRO ACESSO!' as Importante;
