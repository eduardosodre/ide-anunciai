-- Etapa 7: denúncias e moderação
SET NAMES utf8mb4;

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
);