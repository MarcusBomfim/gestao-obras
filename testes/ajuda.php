<?php

declare(strict_types=1);

use GestaoObras\Aplicacao\RegistrarDiarioDeObra;
use GestaoObras\Aplicacao\RemoverDiarioDeObra;
use GestaoObras\Dominio\Diario\ClimaDoDia;
use GestaoObras\Dominio\Diario\DiarioDeObra;
use GestaoObras\Dominio\Obra\Endereco;
use GestaoObras\Dominio\Obra\Obra;
use GestaoObras\Dominio\Servico\Servico;
use GestaoObras\Dominio\Servico\Unidade;
use GestaoObras\Infraestrutura\Banco\Conexao;
use GestaoObras\Infraestrutura\Banco\Migrador;
use GestaoObras\Infraestrutura\Repositorio\RepositorioDeDiariosEmSqlite;
use GestaoObras\Infraestrutura\Repositorio\RepositorioDeObrasEmSqlite;
use GestaoObras\Infraestrutura\Repositorio\RepositorioDeServicosEmSqlite;

/**
 * Banco descartável em memória, com as migrations reais já aplicadas.
 *
 * Os testes de repositório rodam contra o mesmo SQL que roda em produção — o
 * que muda é só onde o banco vive. Cada chamada devolve um banco limpo.
 */
function bancoDeTeste(): PDO
{
    $conexao = Conexao::emMemoria();
    Migrador::padrao($conexao)->aplicar();

    return $conexao;
}

function obraDeExemplo(string $codigo = 'OBR-2026-001'): Obra
{
    return new Obra(
        $codigo,
        'Reforma estrutural do galpão 3',
        'Terminal Portuário Litoral S.A.',
        new Endereco('Avenida Eng. Augusto Barata', '780', 'Macuco', 'Santos', 'SP', '11015-300'),
        new DateTimeImmutable('2026-02-02'),
        120,
        'Marcus Bomfim',
        'CREA-SP 5069874521/D',
    );
}

function servicoDeExemplo(string $codigo = 'ALV-01'): Servico
{
    return new Servico(
        $codigo,
        'Alvenaria de vedação em bloco cerâmico 14x19x39',
        Unidade::MetroQuadrado,
        320.0,
        78.50,
    );
}

function dia(string $data): DateTimeImmutable
{
    return new DateTimeImmutable($data);
}

function diarioDeExemplo(
    string $data = '2026-02-10',
    ?ClimaDoDia $clima = null,
    string $obraCodigo = 'OBR-2026-001',
): DiarioDeObra {
    return new DiarioDeObra(
        $obraCodigo,
        dia($data),
        $clima ?? ClimaDoDia::diaTrabalhavel(),
        'Marcus Bomfim',
        dia($data),
    );
}

/**
 * Ambiente completo para os testes de caso de uso: banco em memória, uma obra
 * em andamento com um serviço no orçamento, e os dois casos de uso montados.
 *
 * @return array{
 *     conexao: PDO,
 *     obras: RepositorioDeObrasEmSqlite,
 *     servicos: RepositorioDeServicosEmSqlite,
 *     diarios: RepositorioDeDiariosEmSqlite,
 *     registrar: RegistrarDiarioDeObra,
 *     remover: RemoverDiarioDeObra
 * }
 */
function ambienteDeObraEmAndamento(): array
{
    $conexao = bancoDeTeste();

    $obras = new RepositorioDeObrasEmSqlite($conexao);
    $servicos = new RepositorioDeServicosEmSqlite($conexao);
    $diarios = new RepositorioDeDiariosEmSqlite($conexao);

    $obra = obraDeExemplo();
    $obra->iniciar();
    $obras->salvar($obra);

    $servicos->salvar('OBR-2026-001', servicoDeExemplo('ALV-01'));

    return [
        'conexao' => $conexao,
        'obras' => $obras,
        'servicos' => $servicos,
        'diarios' => $diarios,
        'registrar' => new RegistrarDiarioDeObra($conexao, $obras, $servicos, $diarios),
        'remover' => new RemoverDiarioDeObra($conexao, $servicos, $diarios),
    ];
}
