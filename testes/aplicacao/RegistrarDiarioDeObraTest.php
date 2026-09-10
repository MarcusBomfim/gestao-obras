<?php

declare(strict_types=1);

use GestaoObras\Dominio\Diario\AtividadeExecutada;
use GestaoObras\Dominio\Diario\ClimaDoDia;
use GestaoObras\Dominio\Diario\Efetivo;
use GestaoObras\Dominio\Diario\FuncaoDeMaoDeObra;
use GestaoObras\Dominio\Diario\Ocorrencia;
use GestaoObras\Dominio\Diario\TipoDeOcorrencia;
use GestaoObras\Dominio\ExcecaoDeDominio;

grupo('Registrar diário: caminho feliz');

teste('grava o diário e faz o serviço avançar', function (): void {
    $app = ambienteDeObraEmAndamento();

    $diario = diarioDeExemplo('2026-02-10');
    $diario->registrarAtividade(new AtividadeExecutada('ALV-01', 96.0));

    $numero = $app['registrar']->executar($diario);

    igual(1, $numero, 'primeiro diário da obra');

    $servico = $app['servicos']->porCodigo('OBR-2026-001', 'ALV-01');
    igualAproximado(96.0, $servico?->quantidadeExecutada() ?? 0.0);
    igualAproximado(30.0, $servico?->percentualExecutado() ?? 0.0);
});

teste('numera em sequência dentro da obra', function (): void {
    $app = ambienteDeObraEmAndamento();

    foreach (['2026-02-10', '2026-02-11', '2026-02-12'] as $indice => $data) {
        $diario = diarioDeExemplo($data);
        $diario->registrarAtividade(new AtividadeExecutada('ALV-01', 20.0));

        igual($indice + 1, $app['registrar']->executar($diario), "diário de {$data}");
    }
});

teste('guarda efetivo, atividades e ocorrências e lê tudo de volta', function (): void {
    $app = ambienteDeObraEmAndamento();

    $efetivo = new Efetivo();
    $efetivo->definir(FuncaoDeMaoDeObra::Encarregado, 1);
    $efetivo->definir(FuncaoDeMaoDeObra::Pedreiro, 6);
    $efetivo->definir(FuncaoDeMaoDeObra::Servente, 9);

    $diario = diarioDeExemplo('2026-02-10');
    $diario->definirEfetivo($efetivo);
    $diario->registrarAtividade(new AtividadeExecutada('ALV-01', 40.0, 'Fachada norte'));
    $diario->registrarAtividade(new AtividadeExecutada('ALV-01', 26.0, 'Fachada sul'));
    $diario->registrarOcorrencia(new Ocorrencia(
        TipoDeOcorrencia::EntregaDeMaterial,
        'Recebidos 4.000 blocos cerâmicos',
    ));

    $app['registrar']->executar($diario);

    $lido = $app['diarios']->porData('OBR-2026-001', dia('2026-02-10'));

    verdadeiro($lido !== null, 'diário encontrado');
    igual(16, $lido?->efetivo()->total());
    igual(15, $lido?->efetivo()->totalDireto());
    igual(2, count($lido?->atividades() ?? []));
    igual(1, count($lido?->ocorrencias() ?? []));
    igual('Fachada norte', $lido?->atividades()[0]->observacao);
    igualAproximado(66.0, $lido?->quantidadePorServico()['ALV-01'] ?? 0.0);
});

grupo('Registrar diário: um por dia');

teste('recusa o segundo diário da mesma data', function (): void {
    $app = ambienteDeObraEmAndamento();

    $primeiro = diarioDeExemplo('2026-02-10');
    $primeiro->registrarAtividade(new AtividadeExecutada('ALV-01', 40.0));
    $app['registrar']->executar($primeiro);

    $segundo = diarioDeExemplo('2026-02-10');
    $segundo->registrarAtividade(new AtividadeExecutada('ALV-01', 10.0));

    lanca(
        ExcecaoDeDominio::class,
        static fn () => $app['registrar']->executar($segundo),
        'Um dia tem um diário só',
    );
});

teste('o diário recusado não altera o serviço', function (): void {
    $app = ambienteDeObraEmAndamento();

    $primeiro = diarioDeExemplo('2026-02-10');
    $primeiro->registrarAtividade(new AtividadeExecutada('ALV-01', 40.0));
    $app['registrar']->executar($primeiro);

    $segundo = diarioDeExemplo('2026-02-10');
    $segundo->registrarAtividade(new AtividadeExecutada('ALV-01', 10.0));

    try {
        $app['registrar']->executar($segundo);
    } catch (ExcecaoDeDominio) {
        // esperado
    }

    igualAproximado(
        40.0,
        $app['servicos']->porCodigo('OBR-2026-001', 'ALV-01')?->quantidadeExecutada() ?? 0.0,
    );
});

grupo('Registrar diário: recusas');

teste('obra planejada não aceita diário', function (): void {
    $app = ambienteDeObraEmAndamento();

    $obra = obraDeExemplo('OBR-2026-002');
    $app['obras']->salvar($obra);
    $app['servicos']->salvar('OBR-2026-002', servicoDeExemplo('ALV-01'));

    $diario = diarioDeExemplo('2026-02-10', null, 'OBR-2026-002');
    $diario->registrarAtividade(new AtividadeExecutada('ALV-01', 10.0));

    lanca(
        ExcecaoDeDominio::class,
        static fn () => $app['registrar']->executar($diario),
        'não aceita diário',
    );
});

teste('recusa diário anterior ao início da obra', function (): void {
    $app = ambienteDeObraEmAndamento();

    $diario = diarioDeExemplo('2026-01-15');
    $diario->registrarAtividade(new AtividadeExecutada('ALV-01', 10.0));

    lanca(
        ExcecaoDeDominio::class,
        static fn () => $app['registrar']->executar($diario),
        'anterior ao início da obra',
    );
});

teste('recusa serviço que não está no orçamento', function (): void {
    $app = ambienteDeObraEmAndamento();

    $diario = diarioDeExemplo('2026-02-10');
    $diario->registrarAtividade(new AtividadeExecutada('NAO-EXISTE', 10.0));

    lanca(
        ExcecaoDeDominio::class,
        static fn () => $app['registrar']->executar($diario),
        'não está no orçamento',
    );
});

teste('obra inexistente é recusada', function (): void {
    $app = ambienteDeObraEmAndamento();

    $diario = diarioDeExemplo('2026-02-10', null, 'OBR-FANTASMA');
    $diario->registrarAtividade(new AtividadeExecutada('ALV-01', 10.0));

    lanca(ExcecaoDeDominio::class, static fn () => $app['registrar']->executar($diario));
});

grupo('Registrar diário: transação');

teste('estouro do previsto desfaz o diário inteiro', function (): void {
    // É o teste que justifica a transação: o diário e o avanço precisam entrar
    // juntos ou não entrar. Diário gravado com avanço não aplicado só apareceria
    // na medição, semanas depois, sem ninguém saber a origem.
    $app = ambienteDeObraEmAndamento();

    $primeiro = diarioDeExemplo('2026-02-10');
    $primeiro->registrarAtividade(new AtividadeExecutada('ALV-01', 300.0));
    $app['registrar']->executar($primeiro);

    $segundo = diarioDeExemplo('2026-02-11');
    $segundo->registrarAtividade(new AtividadeExecutada('ALV-01', 50.0));

    lanca(
        ExcecaoDeDominio::class,
        static fn () => $app['registrar']->executar($segundo),
        'aditivo',
    );

    igual(null, $app['diarios']->porData('OBR-2026-001', dia('2026-02-11')), 'diário não gravado');
    igual(1, count($app['diarios']->daObra('OBR-2026-001')), 'só o primeiro diário existe');
    igualAproximado(
        300.0,
        $app['servicos']->porCodigo('OBR-2026-001', 'ALV-01')?->quantidadeExecutada() ?? 0.0,
    );
});

teste('depois do rollback dá para registrar o mesmo dia corrigido', function (): void {
    $app = ambienteDeObraEmAndamento();

    $primeiro = diarioDeExemplo('2026-02-10');
    $primeiro->registrarAtividade(new AtividadeExecutada('ALV-01', 300.0));
    $app['registrar']->executar($primeiro);

    $errado = diarioDeExemplo('2026-02-11');
    $errado->registrarAtividade(new AtividadeExecutada('ALV-01', 50.0));

    try {
        $app['registrar']->executar($errado);
    } catch (ExcecaoDeDominio) {
        // esperado
    }

    $corrigido = diarioDeExemplo('2026-02-11');
    $corrigido->registrarAtividade(new AtividadeExecutada('ALV-01', 20.0));

    igual(2, $app['registrar']->executar($corrigido), 'recebe o número 2');
    igualAproximado(
        320.0,
        $app['servicos']->porCodigo('OBR-2026-001', 'ALV-01')?->quantidadeExecutada() ?? 0.0,
    );
});

grupo('Remover diário: estorno');

teste('devolve ao orçamento o que o diário tinha apontado', function (): void {
    $app = ambienteDeObraEmAndamento();

    $diario = diarioDeExemplo('2026-02-10');
    $diario->registrarAtividade(new AtividadeExecutada('ALV-01', 96.0));
    $numero = $app['registrar']->executar($diario);

    $app['remover']->executar('OBR-2026-001', $numero);

    igualAproximado(
        0.0,
        $app['servicos']->porCodigo('OBR-2026-001', 'ALV-01')?->quantidadeExecutada() ?? -1.0,
    );
    igual(null, $app['diarios']->porNumero('OBR-2026-001', $numero), 'diário removido');
});

teste('libera a data para um novo diário', function (): void {
    $app = ambienteDeObraEmAndamento();

    $errado = diarioDeExemplo('2026-02-10');
    $errado->registrarAtividade(new AtividadeExecutada('ALV-01', 200.0));
    $numero = $app['registrar']->executar($errado);

    $app['remover']->executar('OBR-2026-001', $numero);

    $certo = diarioDeExemplo('2026-02-10');
    $certo->registrarAtividade(new AtividadeExecutada('ALV-01', 20.0));
    $app['registrar']->executar($certo);

    igualAproximado(
        20.0,
        $app['servicos']->porCodigo('OBR-2026-001', 'ALV-01')?->quantidadeExecutada() ?? 0.0,
    );
});

teste('recusa remover diário que não existe', function (): void {
    $app = ambienteDeObraEmAndamento();

    lanca(
        ExcecaoDeDominio::class,
        static fn () => $app['remover']->executar('OBR-2026-001', 99),
        'Não existe diário',
    );
});

grupo('Consultas do diário');

teste('conta os dias impraticáveis do período', function (): void {
    $app = ambienteDeObraEmAndamento();

    // Dois dias perdidos e um produtivo.
    foreach (['2026-02-10', '2026-02-11'] as $data) {
        $perdido = diarioDeExemplo($data, ClimaDoDia::diaPerdido());
        $perdido->registrarOcorrencia(new Ocorrencia(
            TipoDeOcorrencia::Paralisacao,
            'Chuva forte durante todo o dia, canteiro alagado, equipe dispensada.',
        ));
        $app['registrar']->executar($perdido);
    }

    $produtivo = diarioDeExemplo('2026-02-12');
    $produtivo->registrarAtividade(new AtividadeExecutada('ALV-01', 30.0));
    $app['registrar']->executar($produtivo);

    igual(
        2,
        $app['diarios']->diasImpraticaveis('OBR-2026-001', dia('2026-02-01'), dia('2026-02-28')),
    );
});

teste('lista o período do mais antigo para o mais novo', function (): void {
    $app = ambienteDeObraEmAndamento();

    foreach (['2026-02-12', '2026-02-10', '2026-02-11'] as $data) {
        $diario = diarioDeExemplo($data);
        $diario->registrarAtividade(new AtividadeExecutada('ALV-01', 10.0));
        $app['registrar']->executar($diario);
    }

    $datas = array_map(
        static fn ($diario) => $diario->data->format('Y-m-d'),
        $app['diarios']->noPeriodo('OBR-2026-001', dia('2026-02-10'), dia('2026-02-11')),
    );

    igual(['2026-02-10', '2026-02-11'], $datas);
});

teste('apagar a obra leva os diários junto', function (): void {
    $app = ambienteDeObraEmAndamento();

    $diario = diarioDeExemplo('2026-02-10');
    $diario->registrarAtividade(new AtividadeExecutada('ALV-01', 10.0));
    $app['registrar']->executar($diario);

    $app['obras']->remover('OBR-2026-001');

    igual([], $app['diarios']->daObra('OBR-2026-001'));
});
