-- Contas de acesso. O e-mail é a chave e é guardado em minúsculas pela
-- entidade, então a busca não precisa de LOWER() nem de índice funcional.
CREATE TABLE IF NOT EXISTS usuarios (
    email         TEXT    NOT NULL PRIMARY KEY,
    nome          TEXT    NOT NULL,
    papel         TEXT    NOT NULL
        CHECK (papel IN ('engenheiro', 'mestre_de_obras', 'cliente')),

    -- Só o hash. A senha em texto não passa pelo banco em momento nenhum.
    hash_senha    TEXT    NOT NULL,

    ativo         INTEGER NOT NULL DEFAULT 1 CHECK (ativo IN (0, 1)),

    criado_em     TEXT    NOT NULL DEFAULT (datetime('now')),
    atualizado_em TEXT    NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_usuarios_papel ON usuarios (papel, ativo);
