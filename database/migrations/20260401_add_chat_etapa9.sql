-- Etapa 9: chat / primeiro contato + coordenadas em profissional (se ainda não existirem)
SET NAMES utf8mb4;

ALTER TABLE profissional
    ADD COLUMN latitude DECIMAL(10, 8) NULL,
    ADD COLUMN longitude DECIMAL(11, 8) NULL;

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
