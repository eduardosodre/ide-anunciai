ALTER TABLE profissional
    ADD COLUMN public_nome_publico_aprovado VARCHAR(255) NULL AFTER pendente_revisao,
    ADD COLUMN public_cidade_aprovado VARCHAR(255) NULL AFTER public_nome_publico_aprovado,
    ADD COLUMN public_habilidades_aprovado TEXT NULL AFTER public_cidade_aprovado;

ALTER TABLE igreja
    ADD COLUMN public_nome_igreja_aprovado VARCHAR(255) NULL AFTER pendente_revisao,
    ADD COLUMN public_cidade_aprovado VARCHAR(255) NULL AFTER public_nome_igreja_aprovado;

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
