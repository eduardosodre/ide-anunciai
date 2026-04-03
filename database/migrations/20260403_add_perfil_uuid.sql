-- Identificador público opaco (UUID v4) para URLs de perfil ministro e igreja.

ALTER TABLE profissional
    ADD COLUMN perfil_uuid CHAR(36) NULL UNIQUE AFTER id;

UPDATE profissional SET perfil_uuid = LOWER(UUID()) WHERE perfil_uuid IS NULL;

ALTER TABLE profissional
    MODIFY perfil_uuid CHAR(36) NOT NULL;

ALTER TABLE igreja
    ADD COLUMN perfil_uuid CHAR(36) NULL UNIQUE AFTER id;

UPDATE igreja SET perfil_uuid = LOWER(UUID()) WHERE perfil_uuid IS NULL;

ALTER TABLE igreja
    MODIFY perfil_uuid CHAR(36) NOT NULL;
