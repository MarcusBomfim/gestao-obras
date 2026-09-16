<?php

declare(strict_types=1);

use GestaoObras\Dominio\ExcecaoDeDominio;
use GestaoObras\Dominio\Obra\Endereco;
use GestaoObras\Dominio\Obra\Obra;
use GestaoObras\Dominio\Obra\SituacaoDaObra;

function obraDeTeste(int $prazoEmDias = 10): Obra
{
    return new Obra(
        'obr-2026-001',
        'Reforma do galpão 3',
        'Terminal Portuário Litoral',
        new Endereco('Rua do Porto', '400', 'Centro', 'Santos', 'SP', '11010-000'),
        new DateTimeImmutable('2026-01-01'),
        $prazoEmDias,
        'Marcus Bomfim',
        'CREA-SP 123456/D',
    );
}

function em(string $data): DateTimeImmutable
{
    return new DateTimeImmutable($data);
}

grupo('Obra: cadastro');

teste('normaliza o código para maiúsculas', function (): void {
    igual('OBR-2026-001', obraDeTeste()->codigo);
});

teste('nasce planejada', function (): void {
    igual(SituacaoDaObra::Planejada, obraDeTeste()->situacao());
});

teste('recusa prazo zerado ou negativo', function (): void {
    lanca(ExcecaoDeDominio::class, static fn () => obraDeTeste(0), 'Prazo em dias');
    lanca(ExcecaoDeDominio::class, static fn () => obraDeTeste(-5), 'Prazo em dias');
});

teste('recusa registro profissional fora de formato', function (): void {
    lanca(
        ExcecaoDeDominio::class,
        static fn () => new Obra(
            'OBR-1',
            'Obra',
            'Cliente',
            new Endereco('Rua A', '1', 'Centro', 'Santos', 'SP', '11010-000'),
            em('2026-01-01'),
            30,
            'Responsável',
            'abc',
        ),
        'Registro profissional inválido',
    );
});

grupo('Obra: situação');

teste('planejada só pode ir para em andamento', function (): void {
    $obra = obraDeTeste();

    lanca(ExcecaoDeDominio::class, static fn () => $obra->concluir(), 'Não é possível');
    lanca(ExcecaoDeDominio::class, static fn () => $obra->paralisar());

    $obra->iniciar();
    igual(SituacaoDaObra::EmAndamento, $obra->situacao());
});

teste('percorre o ciclo completo', function (): void {
    $obra = obraDeTeste();

    $obra->iniciar();
    $obra->paralisar();
    igual(SituacaoDaObra::Paralisada, $obra->situacao());

    $obra->retomar();
    igual(SituacaoDaObra::EmAndamento, $obra->situacao());

    $obra->concluir();
    igual(SituacaoDaObra::Concluida, $obra->situacao());
});

teste('obra concluída é estado final', function (): void {
    $obra = obraDeTeste();
    $obra->iniciar();
    $obra->concluir();

    verdadeiro($obra->situacao()->ehFinal(), 'concluída é final');
    lanca(ExcecaoDeDominio::class, static fn () => $obra->paralisar());
    lanca(ExcecaoDeDominio::class, static fn () => $obra->iniciar());
});

teste('só obra em andamento aceita execução', function (): void {
    falso(SituacaoDaObra::Planejada->aceitaExecucao(), 'planejada');
    verdadeiro(SituacaoDaObra::EmAndamento->aceitaExecucao(), 'em andamento');
    falso(SituacaoDaObra::Paralisada->aceitaExecucao(), 'paralisada');
    falso(SituacaoDaObra::Concluida->aceitaExecucao(), 'concluída');
});

grupo('Obra: prazo');

teste('o dia de início conta dentro do prazo', function (): void {
    // Começa em 01/01 com 10 dias de prazo, então termina em 10/01, não em 11/01.
    igual('2026-01-10', obraDeTeste(10)->dataPrevistaDeTermino()->format('Y-m-d'));
});

teste('conta os dias decorridos incluindo hoje', function (): void {
    $obra = obraDeTeste();

    igual(1, $obra->diasDecorridos(em('2026-01-01')));
    igual(5, $obra->diasDecorridos(em('2026-01-05')));
});

teste('antes do início, nenhum dia decorreu', function (): void {
    igual(0, obraDeTeste()->diasDecorridos(em('2025-12-20')));
});

teste('não há atraso dentro do prazo', function (): void {
    $obra = obraDeTeste(10);

    igual(0, $obra->diasDeAtraso(em('2026-01-05')));
    igual(0, $obra->diasDeAtraso(em('2026-01-10')));
    falso($obra->estaAtrasada(em('2026-01-10')), 'último dia do prazo');
});

teste('conta os dias passados do prazo', function (): void {
    $obra = obraDeTeste(10);

    igual(3, $obra->diasDeAtraso(em('2026-01-13')));
    verdadeiro($obra->estaAtrasada(em('2026-01-13')), 'três dias além');
});

teste('obra concluída não acumula atraso', function (): void {
    $obra = obraDeTeste(10);
    $obra->iniciar();
    $obra->concluir();

    igual(0, $obra->diasDeAtraso(em('2026-03-01')));
});
