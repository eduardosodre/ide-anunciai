-- ide-anunciai — schema base (utf8mb4)
-- Execute após criar o banco: CREATE DATABASE ide_anunciai CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS usuario (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL,
    senha_hash VARCHAR(255) NOT NULL,
    foto_url VARCHAR(500) NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    admin TINYINT(1) NOT NULL DEFAULT 0,
    consentimento_em DATETIME NOT NULL,
    versao_politica_aceita VARCHAR(50) NULL,
    criado_em DATETIME NOT NULL,
    atualizado_em DATETIME NOT NULL,
    reset_token_hash VARCHAR(64) NULL,
    reset_expira_em DATETIME NULL,
    ultimo_reset_solicitado_em DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_usuario_email (email),
    KEY idx_usuario_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS profissional (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    perfil_uuid CHAR(36) NOT NULL,
    usuario_id INT UNSIGNED NOT NULL,
    nome_publico VARCHAR(255) NOT NULL,
    telefone VARCHAR(50) NULL,
    cidade VARCHAR(255) NOT NULL,
    estado VARCHAR(2) NULL,
    verificado TINYINT(1) NOT NULL DEFAULT 0,
    pendente_revisao TINYINT(1) NOT NULL DEFAULT 0,
    public_nome_publico_aprovado VARCHAR(255) NULL,
    public_cidade_aprovado VARCHAR(255) NULL,
    public_habilidades_aprovado TEXT NULL,
    latitude DECIMAL(10, 8) NULL,
    longitude DECIMAL(11, 8) NULL,
    criado_em DATETIME NOT NULL,
    atualizado_em DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_profissional_perfil_uuid (perfil_uuid),
    UNIQUE KEY uq_profissional_usuario (usuario_id),
    KEY idx_profissional_cidade (cidade),
    KEY idx_profissional_verificado (verificado),
    KEY idx_profissional_pendente_revisao (pendente_revisao),
    CONSTRAINT fk_profissional_usuario FOREIGN KEY (usuario_id) REFERENCES usuario (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS habilidade (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo VARCHAR(50) NOT NULL,
    label VARCHAR(100) NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_habilidade_codigo (codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS profissional_habilidade (
    profissional_id INT UNSIGNED NOT NULL,
    habilidade_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (profissional_id, habilidade_id),
    KEY idx_profissional_habilidade_habilidade (habilidade_id),
    CONSTRAINT fk_profissional_habilidade_profissional FOREIGN KEY (profissional_id) REFERENCES profissional (id),
    CONSTRAINT fk_profissional_habilidade_habilidade FOREIGN KEY (habilidade_id) REFERENCES habilidade (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS igreja (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    perfil_uuid CHAR(36) NOT NULL,
    usuario_id INT UNSIGNED NOT NULL,
    nome_igreja VARCHAR(255) NOT NULL,
    email_contato VARCHAR(255) NOT NULL,
    telefone VARCHAR(50) NULL,
    cep VARCHAR(20) NOT NULL,
    cidade VARCHAR(255) NOT NULL,
    estado VARCHAR(2) NULL,
    cnpj VARCHAR(20) NULL,
    latitude DECIMAL(10, 8) NULL,
    longitude DECIMAL(11, 8) NULL,
    verificado TINYINT(1) NOT NULL DEFAULT 0,
    pendente_revisao TINYINT(1) NOT NULL DEFAULT 0,
    public_nome_igreja_aprovado VARCHAR(255) NULL,
    public_cidade_aprovado VARCHAR(255) NULL,
    criado_em DATETIME NOT NULL,
    atualizado_em DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_igreja_perfil_uuid (perfil_uuid),
    UNIQUE KEY uq_igreja_usuario (usuario_id),
    KEY idx_igreja_cidade (cidade),
    KEY idx_igreja_verificado (verificado),
    KEY idx_igreja_pendente_revisao (pendente_revisao),
    CONSTRAINT fk_igreja_usuario FOREIGN KEY (usuario_id) REFERENCES usuario (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS verificacao_solicitacao (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id INT UNSIGNED NOT NULL,
    tipo_entidade ENUM('ministro', 'igreja') NOT NULL,
    entidade_id INT UNSIGNED NOT NULL,
    tipo_fluxo ENUM('solicitacao', 'revisao') NOT NULL DEFAULT 'solicitacao',
    status ENUM('pendente', 'aprovado', 'rejeitado') NOT NULL DEFAULT 'pendente',
    rg VARCHAR(50) NULL,
    cpf VARCHAR(20) NULL,
    cnpj VARCHAR(20) NULL,
    motivo_rejeicao VARCHAR(500) NULL,
    analisado_por_usuario_id INT UNSIGNED NULL,
    analisado_em DATETIME NULL,
    criado_em DATETIME NOT NULL,
    atualizado_em DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_verificacao_usuario (usuario_id),
    KEY idx_verificacao_status_fluxo (status, tipo_fluxo),
    KEY idx_verificacao_entidade (tipo_entidade, entidade_id),
    CONSTRAINT fk_verificacao_usuario FOREIGN KEY (usuario_id) REFERENCES usuario (id),
    CONSTRAINT fk_verificacao_analisado_por FOREIGN KEY (analisado_por_usuario_id) REFERENCES usuario (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS verificacao_documento (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    verificacao_solicitacao_id INT UNSIGNED NOT NULL,
    tipo_documento VARCHAR(100) NOT NULL,
    nome_arquivo VARCHAR(255) NULL,
    caminho_arquivo VARCHAR(500) NULL,
    criado_em DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_verificacao_documento_solicitacao (verificacao_solicitacao_id),
    CONSTRAINT fk_verificacao_documento_solicitacao FOREIGN KEY (verificacao_solicitacao_id) REFERENCES verificacao_solicitacao (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS denuncia (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    denunciante_id INT UNSIGNED NOT NULL,
    denunciado_id INT UNSIGNED NOT NULL,
    descricao TEXT NOT NULL,
    criado_em DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_denuncia_denunciado (denunciado_id),
    KEY idx_denuncia_par_data (denunciante_id, denunciado_id, criado_em),
    KEY idx_denuncia_denunciante_data (denunciante_id, criado_em),
    CONSTRAINT fk_denuncia_denunciante FOREIGN KEY (denunciante_id) REFERENCES usuario (id),
    CONSTRAINT fk_denuncia_denunciado FOREIGN KEY (denunciado_id) REFERENCES usuario (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS conversa (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    solicitante_id INT UNSIGNED NOT NULL,
    destinatario_usuario_id INT UNSIGNED NOT NULL,
    tipo_entidade ENUM('ministro', 'igreja') NOT NULL,
    entidade_id INT UNSIGNED NOT NULL,
    criado_em DATETIME NOT NULL,
    atualizado_em DATETIME NOT NULL,
    primeiro_email_enviado_em DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_conversa_solicitante_entidade (solicitante_id, tipo_entidade, entidade_id),
    KEY idx_conversa_solicitante (solicitante_id),
    KEY idx_conversa_destinatario (destinatario_usuario_id),
    KEY idx_conversa_atualizado (atualizado_em),
    CONSTRAINT fk_conversa_solicitante FOREIGN KEY (solicitante_id) REFERENCES usuario (id),
    CONSTRAINT fk_conversa_destinatario FOREIGN KEY (destinatario_usuario_id) REFERENCES usuario (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mensagem (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    conversa_id INT UNSIGNED NOT NULL,
    remetente_id INT UNSIGNED NOT NULL,
    corpo TEXT NOT NULL,
    criado_em DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_mensagem_conversa_criado (conversa_id, criado_em),
    CONSTRAINT fk_mensagem_conversa FOREIGN KEY (conversa_id) REFERENCES conversa (id),
    CONSTRAINT fk_mensagem_remetente FOREIGN KEY (remetente_id) REFERENCES usuario (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO habilidade (codigo, label)
VALUES
    ('ensinar', 'Ensinar'),
    ('ministrar', 'Ministrar'),
    ('orar', 'Orar'),
    ('tocar_instrumento', 'Tocar instrumento'),
    ('pregacao', 'Pregação')
ON DUPLICATE KEY UPDATE
    label = VALUES(label);
