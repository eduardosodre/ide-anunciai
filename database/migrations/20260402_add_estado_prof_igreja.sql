-- Estado (UF) para desambiguar cidade homônima e filtrar busca por região.

ALTER TABLE profissional
    ADD COLUMN estado VARCHAR(2) NULL AFTER cidade;

ALTER TABLE igreja
    ADD COLUMN estado VARCHAR(2) NULL AFTER cidade;
