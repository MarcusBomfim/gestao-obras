<?php

declare(strict_types=1);

use GestaoObras\Dominio\Diario\AtividadeExecutada;
use GestaoObras\Dominio\Diario\Clima;
use GestaoObras\Dominio\Diario\ClimaDoDia;
use GestaoObras\Dominio\Diario\CondicaoDeTrabalho;
use GestaoObras\Dominio\Diario\DiarioDeObra;
use GestaoObras\Dominio\Diario\Efetivo;
use GestaoObras\Dominio\Diario\FuncaoDeMaoDeObra;
use GestaoObras\Dominio\Diario\Ocorrencia;
use GestaoObras\Dominio\Diario\PeriodoDoDia;
use GestaoObras\Dominio\Diario\TipoDeOcorrencia;
use GestaoObras\Dominio\ExcecaoDeDominio;

grupo('Clima do dia');

teste('dia trabalhável tem os três períodos praticáveis', function (): void {
    $clima = ClimaDoDia::diaTrabalhavel();

    verdadeiro($clima->houvePeriodoTrabalhavel(), 'houve trabalho');
    falso($clima->ehDiaPerdido(), 'não é dia perdido');
    igual([], $clima->periodosImpraticaveis());
});

teste('dia perdido não tem nenhum período praticável', function (): void {
    $clima = ClimaDoDia::diaPerdido();

    verdadeiro($clima->ehDiaPerdido(), 'dia perdido');
    igual(3, count($clima->periodosImpraticaveis()));
});

teste('um período praticável já basta para o dia render', function (): void {
    $clima = new ClimaDoDia(
        Clima::Chuvoso,
        CondicaoDeTrabalho::Impraticavel,
        Clima::Nublado,
        CondicaoDeTrabalho::Praticavel,
        Clima::Chuvoso,
        CondicaoDeTrabalho::Impraticavel,
    );

    falso($clima->ehDiaPerdido(), 'a tarde salvou o dia');
    igual(2, count($clima->periodosImpraticaveis()));
    igual(Clima::Nublado, $clima->clima(PeriodoDoDia::Tarde));
});

teste('vai para colunas e volta sem perder nada', function (): void {
    $original = new ClimaDoDia(
        Clima::Bom,
        CondicaoDeTrabalho::Praticavel,
        Clima::Chuvoso,
        CondicaoDeTrabalho::Impraticavel,
        Clima::Nublado,
        CondicaoDeTrabalho::Praticavel,
    );

    $voltou = ClimaDoDia::deColunas($original->paraColunas());

    igual(Clima::Chuvoso, $voltou->clima(PeriodoDoDia::Tarde));
    igual(CondicaoDeTrabalho::Impraticavel, $voltou->condicao(PeriodoDoDia::Tarde));
    igual($original->resumo(), $voltou->resumo());
});

grupo('Efetivo');

teste('soma o total e separa direto de indireto', function (): void {
    $efetivo = new Efetivo();
    $efetivo->definir(FuncaoDeMaoDeObra::Engenheiro, 1);
    $efetivo->definir(FuncaoDeMaoDeObra::Encarregado, 2);
    $efetivo->definir(FuncaoDeMaoDeObra::Pedreiro, 8);
    $efetivo->definir(FuncaoDeMaoDeObra::Servente, 12);

    igual(23, $efetivo->total());
    igual(20, $efetivo->totalDireto());
    igual(3, $efetivo->totalIndireto());
});

teste('definir de novo substitui, não acumula', function (): void {
    $efetivo = new Efetivo();
    $efetivo->definir(FuncaoDeMaoDeObra::Pedreiro, 8);
    $efetivo->definir(FuncaoDeMaoDeObra::Pedreiro, 5);

    igual(5, $efetivo->quantidade(FuncaoDeMaoDeObra::Pedreiro));
});

teste('zero remove a função do apontamento', function (): void {
    $efetivo = new Efetivo();
    $efetivo->definir(FuncaoDeMaoDeObra::Pintor, 3);
    $efetivo->definir(FuncaoDeMaoDeObra::Pintor, 0);

    igual([], $efetivo->paraArray());
    verdadeiro($efetivo->estaVazio(), 'vazio');
});

teste('recusa efetivo negativo', function (): void {
    $efetivo = new Efetivo();

    lanca(
        ExcecaoDeDominio::class,
        static fn () => $efetivo->definir(FuncaoDeMaoDeObra::Servente, -1),
        'não pode ser negativo',
    );
});

grupo('Ocorrência');

teste('acidente exige descrição detalhada', function (): void {
    lanca(
        ExcecaoDeDominio::class,
        static fn () => new Ocorrencia(TipoDeOcorrencia::Acidente, 'Caiu'),
        'ao menos 30 caracteres',
    );
});

teste('acidente com relato completo é aceito', function (): void {
    $ocorrencia = new Ocorrencia(
        TipoDeOcorrencia::Acidente,
        'Servente sofreu corte superficial no antebraço ao manusear vergalhão. '
        . 'Atendido no ambulatório, liberado, CAT aberta.',
    );

    igual(TipoDeOcorrencia::Acidente, $ocorrencia->tipo);
});

teste('visita aceita descrição curta', function (): void {
    $ocorrencia = new Ocorrencia(TipoDeOcorrencia::Visita, 'Fiscal do cliente');

    igual('Fiscal do cliente', $ocorrencia->descricao);
});

grupo('Diário de obra');

teste('recusa diário com data futura', function (): void {
    lanca(
        ExcecaoDeDominio::class,
        static fn () => new DiarioDeObra(
            'OBR-2026-001',
            dia('2026-02-20'),
            ClimaDoDia::diaTrabalhavel(),
            'Marcus Bomfim',
            dia('2026-02-10'),
        ),
        'a data ainda não chegou',
    );
});

teste('aceita diário do próprio dia', function (): void {
    $diario = new DiarioDeObra(
        'OBR-2026-001',
        dia('2026-02-10'),
        ClimaDoDia::diaTrabalhavel(),
        'Marcus Bomfim',
        dia('2026-02-10'),
    );

    igual('2026-02-10', $diario->data->format('Y-m-d'));
});

teste('dia impraticável nos três períodos não aceita atividade', function (): void {
    $diario = diarioDeExemplo('2026-02-10', ClimaDoDia::diaPerdido());

    lanca(
        ExcecaoDeDominio::class,
        static fn () => $diario->registrarAtividade(new AtividadeExecutada('ALV-01', 40.0)),
        'impraticável nos três períodos',
    );
});

teste('dia com um período praticável aceita atividade', function (): void {
    $clima = new ClimaDoDia(
        Clima::Chuvoso,
        CondicaoDeTrabalho::Impraticavel,
        Clima::Nublado,
        CondicaoDeTrabalho::Praticavel,
        Clima::Chuvoso,
        CondicaoDeTrabalho::Impraticavel,
    );

    $diario = diarioDeExemplo('2026-02-10', $clima);
    $diario->registrarAtividade(new AtividadeExecutada('ALV-01', 40.0));

    igual(1, count($diario->atividades()));
});

teste('diário sem atividade e sem ocorrência é recusado', function (): void {
    $diario = diarioDeExemplo();

    lanca(
        ExcecaoDeDominio::class,
        static fn () => $diario->exigirConsistencia(),
        'ao menos uma atividade executada ou uma ocorrência',
    );
});

teste('dia perdido só precisa da ocorrência que explica', function (): void {
    $diario = diarioDeExemplo('2026-02-10', ClimaDoDia::diaPerdido());
    $diario->registrarOcorrencia(new Ocorrencia(
        TipoDeOcorrencia::Paralisacao,
        'Chuva forte durante todo o dia, canteiro alagado, equipe dispensada às 8h.',
    ));

    $diario->exigirConsistencia();

    verdadeiro($diario->ehDiaPerdido(), 'dia perdido');
    igual([], $diario->quantidadePorServico());
});

teste('soma as linhas do mesmo serviço', function (): void {
    $diario = diarioDeExemplo();
    $diario->registrarAtividade(new AtividadeExecutada('ALV-01', 40.0, 'Turno da manhã'));
    $diario->registrarAtividade(new AtividadeExecutada('ALV-01', 26.5, 'Turno da tarde'));
    $diario->registrarAtividade(new AtividadeExecutada('COB-01', 12.0));

    $totais = $diario->quantidadePorServico();

    igualAproximado(66.5, $totais['ALV-01']);
    igualAproximado(12.0, $totais['COB-01']);
    igual(3, count($diario->atividades()), 'as três linhas continuam registradas');
});

teste('normaliza o código do serviço na atividade', function (): void {
    $atividade = new AtividadeExecutada('alv-01', 40.0);

    igual('ALV-01', $atividade->servicoCodigo);
});

teste('recusa atividade com quantidade zerada', function (): void {
    lanca(ExcecaoDeDominio::class, static fn () => new AtividadeExecutada('ALV-01', 0.0));
});

teste('observação em branco vira nulo', function (): void {
    igual(null, (new AtividadeExecutada('ALV-01', 10.0, '   '))->observacao);
});
