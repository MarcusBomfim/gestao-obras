/*
 * Medições: o recorte de período que vira fatura.
 *
 * Duas medições da mesma obra não podem cobrir períodos que se sobrepõem —
 * seria faturar o mesmo serviço duas vezes.
 *
 * No PostgreSQL isso caberia em uma linha, com uma constraint de exclusão
 * sobre DATERANGE e o operador de sobreposição. O SQLite não tem EXCLUDE nem
 * tipo de intervalo, então a garantia aqui vem de outro lado: as medições de
 * uma obra são consecutivas, e o caso de uso exige que cada uma comece no dia
 * seguinte ao fim da anterior. Não é a mesma coisa — é uma regra mais
 * restritiva, que por sorte descreve como o setor mede de verdade, competência
 * após competência, sem buracos nem sobreposição.
 *
 * O UNIQUE abaixo garante a parte que dá para garantir no banco: nenhuma obra
 * tem duas medições terminando no mesmo dia.
 */
CREATE TABLE IF NOT EXISTS medicoes (
    obra_codigo TEXT    NOT NULL
        REFERENCES obras (codigo) ON DELETE CASCADE ON UPDATE CASCADE,
    numero      INTEGER NOT NULL CHECK (numero > 0),

    inicio      TEXT    NOT NULL CHECK (date(inicio) IS NOT NULL),
    fim         TEXT    NOT NULL CHECK (date(fim) IS NOT NULL),

    situacao    TEXT    NOT NULL CHECK (situacao IN ('aberta', 'fechada')),

    criado_em   TEXT    NOT NULL DEFAULT (datetime('now')),
    fechado_em  TEXT,

    PRIMARY KEY (obra_codigo, numero),

    UNIQUE (obra_codigo, fim),

    CHECK (fim >= inicio),

    -- Fechada tem data de fechamento; aberta não tem.
    CHECK (
        (situacao = 'fechada' AND fechado_em IS NOT NULL)
        OR (situacao = 'aberta' AND fechado_em IS NULL)
    )
);

CREATE INDEX IF NOT EXISTS idx_medicoes_periodo ON medicoes (obra_codigo, fim DESC);


/*
 * A memória de cálculo, linha a linha.
 *
 * Os valores do serviço são copiados para cá em vez de referenciados: uma
 * medição fechada é documento, e precisa continuar mostrando o preço unitário
 * que valia na competência, mesmo que o orçamento seja aditivado depois.
 */
CREATE TABLE IF NOT EXISTS medicao_itens (
    obra_codigo         TEXT NOT NULL,
    numero              INTEGER NOT NULL,
    servico_codigo      TEXT NOT NULL,

    descricao           TEXT NOT NULL,
    unidade             TEXT NOT NULL
        CHECK (unidade IN ('m2', 'm3', 'm', 'kg', 't', 'un', 'vb', 'h')),

    quantidade_prevista REAL NOT NULL CHECK (quantidade_prevista > 0),
    preco_unitario      REAL NOT NULL CHECK (preco_unitario > 0),
    acumulado_anterior  REAL NOT NULL DEFAULT 0 CHECK (acumulado_anterior >= 0),
    no_periodo          REAL NOT NULL DEFAULT 0 CHECK (no_periodo >= 0),

    PRIMARY KEY (obra_codigo, numero, servico_codigo),

    FOREIGN KEY (obra_codigo, numero)
        REFERENCES medicoes (obra_codigo, numero) ON DELETE CASCADE,

    -- A mesma regra do orçamento, agora no documento de medição.
    CHECK (acumulado_anterior + no_periodo <= quantidade_prevista + 0.001)
);

CREATE INDEX IF NOT EXISTS idx_medicao_itens_servico
    ON medicao_itens (obra_codigo, servico_codigo);
