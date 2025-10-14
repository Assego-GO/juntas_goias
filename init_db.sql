CREATE DATABASE IF NOT EXISTS juntas_goias;

USE juntas_goias;

CREATE TABLE IF NOT EXISTS demandas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    faixa_idade VARCHAR(50),
    municipio_form VARCHAR(255),
    moradia VARCHAR(50),
    violencia TEXT,
    descricao TEXT,
    perigo_agora VARCHAR(50),
    nivel_urgencia VARCHAR(50),
    criancas_idosas VARCHAR(50),
    data_envio TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS servicos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome_servico VARCHAR(255) NOT NULL,
    descricao TEXT
);

-- Inserir alguns serviços de exemplo (opcional)
INSERT INTO servicos (nome_servico, descricao) VALUES
('Serviço de Consultoria', 'Oferecemos consultoria especializada em diversas áreas.'),
('Desenvolvimento Web', 'Criação de websites e aplicações web personalizadas.'),
('Marketing Digital', 'Estratégias de marketing para impulsionar sua presença online.');
