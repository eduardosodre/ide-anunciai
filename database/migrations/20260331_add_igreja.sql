-- Migração opcional: bancos criados antes da Etapa 5 (igreja).
-- Seguro para reexecutar: usa IF NOT EXISTS.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS igreja (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id INT UNSIGNED NOT NULL,
    nome_igreja VARCHAR(255) NOT NULL,
    email_contato VARCHAR(255) NOT NULL,
    telefone VARCHAR(50) NULL,
    cep VARCHAR(20) NOT NULL,
    cidade VARCHAR(255) NOT NULL,
    cnpj VARCHAR(20) NULL,
    latitude DECIMAL(10, 8) NULL,
    longitude DECIMAL(11, 8) NULL,
    verificado TINYINT(1) NOT NULL DEFAULT 0,
    pendente_revisao TINYINT(1) NOT NULL DEFAULT 0,
    criado_em DATETIME NOT NULL,
    atualizado_em DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_igreja_usuario (usuario_id),
    KEY idx_igreja_cidade (cidade),
    KEY idx_igreja_verificado (verificado),
    KEY idx_igreja_pendente_revisao (pendente_revisao),
    CONSTRAINT fk_igreja_usuario FOREIGN KEY (usuario_id) REFERENCES usuario (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
