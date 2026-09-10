<?php

declare(strict_types=1);

use GestaoObras\Dominio\Obra\Endereco;
use GestaoObras\Dominio\Obra\Obra;
use GestaoObras\Dominio\Servico\Servico;
use GestaoObras\Dominio\Servico\Unidade;
use GestaoObras\Infraestrutura\Banco\Conexao;
use GestaoObras\Infraestrutura\Banco\Migrador;

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
