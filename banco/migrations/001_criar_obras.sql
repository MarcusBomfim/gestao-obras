-- O código da obra é a chave: quem define é a construtora, é único no contrato
-- e nunca muda. Não há ganho em criar um identificador artificial ao lado dele.
CREATE TABLE IF NOT EXISTS obras (
    codigo                TEXT    NOT NULL PRIMARY KEY,
    nome                  TEXT    NOT NULL,
    cliente               TEXT    NOT NULL,

    logradouro            TEXT    NOT NULL,
    numero                TEXT    NOT NULL,
    bairro                TEXT    NOT NULL,
    cidade                TEXT    NOT NULL,
    uf                    TEXT    NOT NULL CHECK (length(uf) = 2),
    cep                   TEXT    NOT NULL CHECK (length(cep) = 8),

    -- Datas em ISO 8601 (YYYY-MM-DD). É o formato que o SQLite ordena e
    -- compara corretamente como texto.
    data_de_inicio        TEXT    NOT NULL CHECK (date(data_de_inicio) IS NOT NULL),
    prazo_em_dias         INTEGER NOT NULL CHECK (prazo_em_dias > 0),

    responsavel_tecnico   TEXT    NOT NULL,
    registro_profissional TEXT    NOT NULL,

    situacao              TEXT    NOT NULL
        CHECK (situacao IN ('planejada', 'em_andamento', 'paralisada', 'concluida')),

    criado_em             TEXT    NOT NULL DEFAULT (datetime('now')),
    atualizado_em         TEXT    NOT NULL DEFAULT (datetime('now'))
);

CREATE INDEX IF NOT EXISTS idx_obras_situacao ON obras (situacao);

CREATE INDEX IF NOT EXISTS idx_obras_cidade_uf ON obras (uf, cidade);
