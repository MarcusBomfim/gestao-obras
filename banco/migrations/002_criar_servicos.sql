-- Itens do orçamento da obra. A chave é composta: o código do serviço só
-- precisa ser único dentro da obra, e "ALV-01" existir em duas obras é normal.
CREATE TABLE IF NOT EXISTS servicos (
    obra_codigo          TEXT NOT NULL
        REFERENCES obras (codigo) ON DELETE CASCADE ON UPDATE CASCADE,
    codigo               TEXT NOT NULL,
    descricao            TEXT NOT NULL,

    unidade              TEXT NOT NULL
        CHECK (unidade IN ('m2', 'm3', 'm', 'kg', 't', 'un', 'vb', 'h')),

    quantidade_prevista  REAL NOT NULL CHECK (quantidade_prevista > 0),
    preco_unitario       REAL NOT NULL CHECK (preco_unitario > 0),
    quantidade_executada REAL NOT NULL DEFAULT 0 CHECK (quantidade_executada >= 0),

    criado_em            TEXT NOT NULL DEFAULT (datetime('now')),
    atualizado_em        TEXT NOT NULL DEFAULT (datetime('now')),

    PRIMARY KEY (obra_codigo, codigo),

    /*
     * A mesma regra que a classe Servico aplica, repetida aqui.
     *
     * Não é redundância desperdiçada: a validação da aplicação dá a mensagem
     * boa para quem está usando, e a do banco garante que nenhum caminho
     * escape — um script de carga, uma correção manual, ou duas requisições
     * simultâneas. A tolerância é a mesma 0,001 de Regras::TOLERANCIA, porque
     * REAL sofre do mesmo arredondamento que o float do PHP.
     */
    CHECK (quantidade_executada <= quantidade_prevista + 0.001)
);

CREATE INDEX IF NOT EXISTS idx_servicos_obra ON servicos (obra_codigo);
