<?php

declare(strict_types=1);

use GestaoObras\Dominio\ExcecaoDeDominio;
use GestaoObras\Dominio\Servico\Servico;
use GestaoObras\Dominio\Servico\Unidade;

function alvenaria(float $previsto = 320.0): Servico
{
    return new Servico('alv-01', 'Alvenaria de vedação em bloco cerâmico', Unidade::MetroQuadrado, $previsto, 78.50);
}

function portas(float $previsto = 12.0): Servico
{
    return new Servico('PRT-01', 'Porta de madeira semi-oca 80x210', Unidade::Unidade, $previsto, 640.00);
}

grupo('Serviço: cadastro');

teste('normaliza o código e guarda os dados', function (): void {
    $servico = alvenaria();

    igual('ALV-01', $servico->codigo);
    igual(Unidade::MetroQuadrado, $servico->unidade);
    igualAproximado(320.0, $servico->quantidadePrevista);
});

teste('começa sem nada executado', function (): void {
    igualAproximado(0.0, alvenaria()->quantidadeExecutada());
    igualAproximado(0.0, alvenaria()->percentualExecutado());
});

teste('recusa quantidade prevista zerada', function (): void {
    lanca(ExcecaoDeDominio::class, static fn () => alvenaria(0.0), 'Quantidade prevista');
});

teste('unidade sem fração recusa quantidade quebrada', function (): void {
    lanca(ExcecaoDeDominio::class, static fn () => portas(12.5), 'não aceita fração');
});

teste('unidade com fração aceita quantidade quebrada', function (): void {
    igualAproximado(320.75, alvenaria(320.75)->quantidadePrevista);
});

grupo('Serviço: apontamento de execução');

teste('acumula os apontamentos', function (): void {
    $servico = alvenaria();

    $servico->registrarExecucao(80.0);
    $servico->registrarExecucao(45.5);

    igualAproximado(125.5, $servico->quantidadeExecutada());
});

teste('calcula o avanço físico', function (): void {
    $servico = alvenaria(320.0);
    $servico->registrarExecucao(160.0);

    igualAproximado(50.0, $servico->percentualExecutado());
    igualAproximado(160.0, $servico->saldo());
});

teste('permite executar exatamente o previsto', function (): void {
    $servico = alvenaria(320.0);
    $servico->registrarExecucao(320.0);

    igualAproximado(100.0, $servico->percentualExecutado());
    verdadeiro($servico->estaConcluido(), 'saldo zerado');
});

teste('recusa apontamento que passa do previsto', function (): void {
    $servico = alvenaria(320.0);
    $servico->registrarExecucao(300.0);

    lanca(ExcecaoDeDominio::class, static fn () => $servico->registrarExecucao(25.0), 'aditivo');

    // O apontamento recusado não pode ter alterado o acumulado.
    igualAproximado(300.0, $servico->quantidadeExecutada());
});

teste('a mensagem de recusa informa o saldo disponível', function (): void {
    $servico = alvenaria(320.0);
    $servico->registrarExecucao(300.0);

    lanca(ExcecaoDeDominio::class, static fn () => $servico->registrarExecucao(25.0), '20,00 m²');
});

teste('soma de frações não estoura por erro de ponto flutuante', function (): void {
    // 0.1 + 0.2 não dá exatamente 0.3 em float; a tolerância cobre isso.
    $servico = new Servico('TST-01', 'Teste de arredondamento', Unidade::MetroCubico, 0.3, 100.0);

    $servico->registrarExecucao(0.1);
    $servico->registrarExecucao(0.2);

    verdadeiro($servico->estaConcluido(), 'deveria fechar em 100%');
});

teste('recusa apontamento zerado ou negativo', function (): void {
    $servico = alvenaria();

    lanca(ExcecaoDeDominio::class, static fn () => $servico->registrarExecucao(0.0));
    lanca(ExcecaoDeDominio::class, static fn () => $servico->registrarExecucao(-10.0));
});

grupo('Serviço: estorno');

teste('devolve quantidade ao saldo', function (): void {
    $servico = alvenaria(320.0);
    $servico->registrarExecucao(200.0);
    $servico->estornarExecucao(50.0);

    igualAproximado(150.0, $servico->quantidadeExecutada());
});

teste('recusa estorno maior que o apontado', function (): void {
    $servico = alvenaria(320.0);
    $servico->registrarExecucao(30.0);

    lanca(ExcecaoDeDominio::class, static fn () => $servico->estornarExecucao(50.0), 'só há');
});

grupo('Serviço: valores');

teste('calcula previsto e executado em reais', function (): void {
    $servico = alvenaria(320.0);

    igualAproximado(25120.0, $servico->valorPrevisto());

    $servico->registrarExecucao(160.0);
    igualAproximado(12560.0, $servico->valorExecutado());
});

grupo('Unidade de medida');

teste('formata conforme a unidade', function (): void {
    igual('320,00 m²', Unidade::MetroQuadrado->formatar(320.0));
    igual('12 un', Unidade::Unidade->formatar(12.0));
    igual('1.250,50 kg', Unidade::Quilograma->formatar(1250.5));
});

teste('sabe quais unidades aceitam fração', function (): void {
    verdadeiro(Unidade::MetroCubico->aceitaFracao(), 'm³');
    falso(Unidade::Unidade->aceitaFracao(), 'un');
    falso(Unidade::Verba->aceitaFracao(), 'vb');
});

teste('converte de e para o valor guardado no banco', function (): void {
    igual(Unidade::MetroQuadrado, Unidade::from('m2'));
    igual(null, Unidade::tryFrom('quilometro'));
});
