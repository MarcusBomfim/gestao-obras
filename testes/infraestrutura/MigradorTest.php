<?php

declare(strict_types=1);

use GestaoObras\Infraestrutura\Banco\Conexao;
use GestaoObras\Infraestrutura\Banco\Migrador;

/** @return string[] */
function tabelasDe(PDO $conexao): array
{
    $consulta = $conexao->query(
        "SELECT name FROM sqlite_master WHERE type = 'table' ORDER BY name"
    );

    return array_column($consulta === false ? [] : $consulta->fetchAll(), 'name');
}

grupo('Migrador');

teste('cria as tabelas do domínio', function (): void {
    $tabelas = tabelasDe(bancoDeTeste());

    verdadeiro(in_array('obras', $tabelas, true), 'tabela obras');
    verdadeiro(in_array('servicos', $tabelas, true), 'tabela servicos');
    verdadeiro(in_array('migrations_aplicadas', $tabelas, true), 'tabela de controle');
});

teste('aplica cada migration uma única vez', function (): void {
    $conexao = Conexao::emMemoria();
    $migrador = Migrador::padrao($conexao);

    $primeira = $migrador->aplicar();
    $segunda = $migrador->aplicar();

    verdadeiro(count($primeira) >= 2, 'primeira execução aplica tudo');
    igual([], $segunda, 'segunda execução não repete');
});

teste('lista as pendentes antes de aplicar', function (): void {
    $conexao = Conexao::emMemoria();
    $migrador = Migrador::padrao($conexao);

    verdadeiro($migrador->pendentes() !== [], 'banco novo tem pendências');

    $migrador->aplicar();

    igual([], $migrador->pendentes(), 'depois de aplicar não sobra nada');
});

grupo('Banco: integridade');

teste('a chave estrangeira está ativa', function (): void {
    // O SQLite ignora chave estrangeira por padrão. Se o PRAGMA da classe
    // Conexao sumir, este teste é o que denuncia.
    $conexao = bancoDeTeste();

    lanca(PDOException::class, static function () use ($conexao): void {
        $conexao->exec(
            "INSERT INTO servicos (obra_codigo, codigo, descricao, unidade,
                quantidade_prevista, preco_unitario)
             VALUES ('OBRA-QUE-NAO-EXISTE', 'X-01', 'Serviço órfão', 'm2', 10, 5)"
        );
    });
});

teste('apagar a obra apaga os serviços em cascata', function (): void {
    $conexao = bancoDeTeste();

    $conexao->exec(
        "INSERT INTO obras (codigo, nome, cliente, logradouro, numero, bairro, cidade, uf, cep,
            data_de_inicio, prazo_em_dias, responsavel_tecnico, registro_profissional, situacao)
         VALUES ('OBR-1', 'Obra', 'Cliente', 'Rua A', '1', 'Centro', 'Santos', 'SP', '11010000',
            '2026-01-01', 30, 'Responsável', 'CREA-SP 1234/D', 'planejada')"
    );
    $conexao->exec(
        "INSERT INTO servicos (obra_codigo, codigo, descricao, unidade,
            quantidade_prevista, preco_unitario)
         VALUES ('OBR-1', 'ALV-01', 'Alvenaria', 'm2', 100, 50)"
    );

    $conexao->exec("DELETE FROM obras WHERE codigo = 'OBR-1'");

    igual(0, (int) $conexao->query('SELECT COUNT(*) FROM servicos')->fetchColumn());
});

teste('o banco recusa executado acima do previsto', function (): void {
    // A mesma regra da classe Servico, agora garantida pelo banco: nenhum
    // caminho escapa, nem carga de dados nem correção manual.
    $conexao = bancoDeTeste();

    $conexao->exec(
        "INSERT INTO obras (codigo, nome, cliente, logradouro, numero, bairro, cidade, uf, cep,
            data_de_inicio, prazo_em_dias, responsavel_tecnico, registro_profissional, situacao)
         VALUES ('OBR-1', 'Obra', 'Cliente', 'Rua A', '1', 'Centro', 'Santos', 'SP', '11010000',
            '2026-01-01', 30, 'Responsável', 'CREA-SP 1234/D', 'em_andamento')"
    );

    lanca(PDOException::class, static function () use ($conexao): void {
        $conexao->exec(
            "INSERT INTO servicos (obra_codigo, codigo, descricao, unidade,
                quantidade_prevista, preco_unitario, quantidade_executada)
             VALUES ('OBR-1', 'ALV-01', 'Alvenaria', 'm2', 100, 50, 140)"
        );
    });
});

teste('o banco recusa situação fora das previstas', function (): void {
    $conexao = bancoDeTeste();

    lanca(PDOException::class, static function () use ($conexao): void {
        $conexao->exec(
            "INSERT INTO obras (codigo, nome, cliente, logradouro, numero, bairro, cidade, uf, cep,
                data_de_inicio, prazo_em_dias, responsavel_tecnico, registro_profissional, situacao)
             VALUES ('OBR-9', 'Obra', 'Cliente', 'Rua A', '1', 'Centro', 'Santos', 'SP', '11010000',
                '2026-01-01', 30, 'Responsável', 'CREA-SP 1234/D', 'inventada')"
        );
    });
});
