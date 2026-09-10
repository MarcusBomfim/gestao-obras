/*
 * Relatório Diário de Obra.
 *
 * A regra central do módulo é uma linha só: UNIQUE (obra_codigo, data).
 *
 * Poderia estar na aplicação — consultar se já existe diário do dia antes de
 * gravar. Mas duas requisições simultâneas leem a agenda antes de qualquer uma
 * escrever, as duas passam na verificação e as duas gravam. Com a restrição no
 * banco, a segunda recebe erro de unicidade, e o caso de uso traduz para uma
 * mensagem legível. Deixa de ser improvável e passa a ser impossível.
 */
CREATE TABLE IF NOT EXISTS diarios (
    obra_codigo    TEXT    NOT NULL
        REFERENCES obras (codigo) ON DELETE CASCADE ON UPDATE CASCADE,

    -- Sequencial dentro da obra: "RDO nº 47 da obra OBR-2026-001".
    numero         INTEGER NOT NULL CHECK (numero > 0),

    data           TEXT    NOT NULL CHECK (date(data) IS NOT NULL),

    clima_manha    TEXT    NOT NULL CHECK (clima_manha IN ('bom', 'nublado', 'chuvoso')),
    condicao_manha TEXT    NOT NULL CHECK (condicao_manha IN ('praticavel', 'impraticavel')),
    clima_tarde    TEXT    NOT NULL CHECK (clima_tarde IN ('bom', 'nublado', 'chuvoso')),
    condicao_tarde TEXT    NOT NULL CHECK (condicao_tarde IN ('praticavel', 'impraticavel')),
    clima_noite    TEXT    NOT NULL CHECK (clima_noite IN ('bom', 'nublado', 'chuvoso')),
    condicao_noite TEXT    NOT NULL CHECK (condicao_noite IN ('praticavel', 'impraticavel')),

    responsavel    TEXT    NOT NULL,
    criado_em      TEXT    NOT NULL DEFAULT (datetime('now')),

    PRIMARY KEY (obra_codigo, numero),

    -- Um diário por obra por dia. É a regra que não pode escapar.
    UNIQUE (obra_codigo, data)
);

CREATE INDEX IF NOT EXISTS idx_diarios_data ON diarios (obra_codigo, data DESC);


-- Efetivo presente, por função.
CREATE TABLE IF NOT EXISTS diario_efetivo (
    obra_codigo TEXT    NOT NULL,
    numero      INTEGER NOT NULL,
    funcao      TEXT    NOT NULL,
    quantidade  INTEGER NOT NULL CHECK (quantidade > 0),

    PRIMARY KEY (obra_codigo, numero, funcao),

    FOREIGN KEY (obra_codigo, numero)
        REFERENCES diarios (obra_codigo, numero) ON DELETE CASCADE
);


/*
 * Serviços executados no dia.
 *
 * A chave estrangeira para servicos garante o que mais importa: ninguém aponta
 * execução de um serviço que não está no orçamento daquela obra. O mesmo
 * serviço pode aparecer em mais de uma linha no mesmo dia — manhã e tarde, com
 * observações diferentes — e por isso a chave é artificial, não composta.
 *
 * As duas chaves usam CASCADE de propósito. RESTRICT em servicos seria a regra
 * mais desejável ("não apague serviço que já tem apontamento"), mas apagar a
 * obra cascateia para servicos e para diarios ao mesmo tempo, e a ordem entre
 * as duas não é garantida — o RESTRICT dispararia dependendo de qual caminho
 * o SQLite percorrer primeiro. Proteger serviço com apontamento é política, e
 * fica na aplicação, onde ainda dá para explicar o motivo a quem tentou.
 */
CREATE TABLE IF NOT EXISTS diario_atividades (
    id             INTEGER PRIMARY KEY AUTOINCREMENT,
    obra_codigo    TEXT    NOT NULL,
    numero         INTEGER NOT NULL,
    servico_codigo TEXT    NOT NULL,
    quantidade     REAL    NOT NULL CHECK (quantidade > 0),
    observacao     TEXT,

    FOREIGN KEY (obra_codigo, numero)
        REFERENCES diarios (obra_codigo, numero) ON DELETE CASCADE,

    FOREIGN KEY (obra_codigo, servico_codigo)
        REFERENCES servicos (obra_codigo, codigo) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_atividades_servico
    ON diario_atividades (obra_codigo, servico_codigo);

CREATE INDEX IF NOT EXISTS idx_atividades_diario
    ON diario_atividades (obra_codigo, numero);


-- Acidentes, paralisações, visitas, inspeções e entregas.
CREATE TABLE IF NOT EXISTS diario_ocorrencias (
    id          INTEGER PRIMARY KEY AUTOINCREMENT,
    obra_codigo TEXT    NOT NULL,
    numero      INTEGER NOT NULL,
    tipo        TEXT    NOT NULL
        CHECK (tipo IN ('acidente', 'paralisacao', 'visita', 'inspecao',
                        'entrega_material', 'outro')),
    descricao   TEXT    NOT NULL,

    FOREIGN KEY (obra_codigo, numero)
        REFERENCES diarios (obra_codigo, numero) ON DELETE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_ocorrencias_diario
    ON diario_ocorrencias (obra_codigo, numero);
