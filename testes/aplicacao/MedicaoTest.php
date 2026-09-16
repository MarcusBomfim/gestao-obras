<?php

declare(strict_types=1);

use GestaoObras\Aplicacao\CurvaDeAvanco;
use GestaoObras\Dominio\ExcecaoDeDominio;
use GestaoObras\Dominio\Medicao\ItemDeMedicao;
use GestaoObras\Dominio\Medicao\Medicao;
use GestaoObras\Dominio\Medicao\SituacaoDaMedicao;
use GestaoObras\Dominio\Servico\Unidade;

function itemDeExemplo(float $anterior = 0.0, float $noPeriodo = 96.0): ItemDeMedicao
{
    return new ItemDeMedicao(
        'ALV-01',
        'Alvenaria de vedação',
        Unidade::MetroQuadrado,
        320.0,
        78.50,
        $anterior,
        $noPeriodo,
    );
}

grupo('Item de medição');

teste('mostra os três números que o cliente confere', function (): void {
    $item = itemDeExemplo(100.0, 60.0);

    igualAproximado(100.0, $item->acumuladoAnterior);
    igualAproximado(60.0, $item->noPeriodo);
    igualAproximado(160.0, $item->acumulado());
    igualAproximado(160.0, $item->saldo());
});

teste('calcula os valores da linha', function (): void {
    $item = itemDeExemplo(0.0, 100.0);

    igualAproximado(7850.0, $item->valorNoPeriodo());
    igualAproximado(7850.0, $item->valorAcumulado());
    igualAproximado(25120.0, $item->valorPrevisto());
    igualAproximado(31.25, $item->percentualAcumulado());
});

teste('recusa acumulado acima do previsto', function (): void {
    lanca(
        ExcecaoDeDominio::class,
        static fn () => itemDeExemplo(300.0, 50.0),
        'acima do previsto',
    );
});

teste('item sem movimento no período é válido', function (): void {
    $item = itemDeExemplo(100.0, 0.0);

    falso($item->teveMovimento(), 'sem movimento');
    igualAproximado(0.0, $item->valorNoPeriodo());
});

grupo('Medição');

teste('recusa período invertido', function (): void {
    lanca(
        ExcecaoDeDominio::class,
        static fn () => new Medicao('OBR-1', dia('2026-03-31'), dia('2026-03-01'), dia('2026-04-10')),
        'não pode ser antes do início',
    );
});

teste('recusa período que ainda não terminou', function (): void {
    lanca(
        ExcecaoDeDominio::class,
        static fn () => new Medicao('OBR-1', dia('2026-03-01'), dia('2026-03-31'), dia('2026-03-15')),
        'ainda não terminou',
    );
});

teste('nasce aberta e conta os dias do período', function (): void {
    $medicao = new Medicao('OBR-1', dia('2026-03-01'), dia('2026-03-31'), dia('2026-04-10'));

    igual(SituacaoDaMedicao::Aberta, $medicao->situacao());
    igual(31, $medicao->diasDoPeriodo());
});

teste('soma os valores dos itens', function (): void {
    $medicao = new Medicao('OBR-1', dia('2026-03-01'), dia('2026-03-31'), dia('2026-04-10'));
    $medicao->adicionarItem(itemDeExemplo(0.0, 100.0));
    $medicao->adicionarItem(new ItemDeMedicao(
        'COB-01', 'Cobertura', Unidade::MetroQuadrado, 1000.0, 100.0, 200.0, 50.0,
    ));

    igualAproximado(12850.0, $medicao->valorNoPeriodo(), '100×78,50 + 50×100');
    igualAproximado(32850.0, $medicao->valorAcumulado(), 'inclui os 200 anteriores');
});

teste('separa os itens que movimentaram', function (): void {
    $medicao = new Medicao('OBR-1', dia('2026-03-01'), dia('2026-03-31'), dia('2026-04-10'));
    $medicao->adicionarItem(itemDeExemplo(0.0, 100.0));
    $medicao->adicionarItem(new ItemDeMedicao(
        'COB-01', 'Cobertura', Unidade::MetroQuadrado, 1000.0, 100.0, 0.0, 0.0,
    ));

    igual(2, count($medicao->itens()));
    igual(1, count($medicao->itensComMovimento()));
});

teste('não fecha sem nada medido', function (): void {
    $medicao = new Medicao('OBR-1', dia('2026-03-01'), dia('2026-03-31'), dia('2026-04-10'));
    $medicao->definirNumero(1);
    $medicao->adicionarItem(itemDeExemplo(100.0, 0.0));

    lanca(ExcecaoDeDominio::class, static fn () => $medicao->fechar(), 'Não há o que medir');
});

teste('medição fechada não aceita mais itens', function (): void {
    $medicao = new Medicao('OBR-1', dia('2026-03-01'), dia('2026-03-31'), dia('2026-04-10'));
    $medicao->definirNumero(1);
    $medicao->adicionarItem(itemDeExemplo());
    $medicao->fechar();

    verdadeiro($medicao->estaFechada(), 'fechada');
    lanca(ExcecaoDeDominio::class, static fn () => $medicao->adicionarItem(itemDeExemplo()), 'está fechada');
    lanca(ExcecaoDeDominio::class, static fn () => $medicao->fechar(), 'está fechada');
});

grupo('Gerar medição a partir dos diários');

teste('soma o que os diários apontaram no período', function (): void {
    $app = ambienteDeObraEmAndamento();

    apontar($app, '2026-02-10', 40.0);
    apontar($app, '2026-02-11', 30.0);
    apontar($app, '2026-02-12', 26.0);

    $medicao = $app['gerarMedicao']->executar('OBR-2026-001', dia('2026-02-02'), dia('2026-02-28'));

    igual(1, $medicao->numero());
    igualAproximado(96.0, $medicao->itens()[0]->noPeriodo);
    igualAproximado(7536.0, $medicao->valorNoPeriodo(), '96 × 78,50');
});

teste('o que ficou fora do período vai para o acumulado anterior', function (): void {
    $app = ambienteDeObraEmAndamento();

    apontar($app, '2026-02-10', 40.0);
    apontar($app, '2026-03-05', 30.0);

    $primeira = $app['gerarMedicao']->executar('OBR-2026-001', dia('2026-02-02'), dia('2026-02-28'));
    $app['fecharMedicao']->executar('OBR-2026-001', $primeira->numero());

    $segunda = $app['gerarMedicao']->executar('OBR-2026-001', dia('2026-03-01'), dia('2026-03-31'));

    igualAproximado(40.0, $segunda->itens()[0]->acumuladoAnterior);
    igualAproximado(30.0, $segunda->itens()[0]->noPeriodo);
    igualAproximado(70.0, $segunda->itens()[0]->acumulado());
});

teste('exige que a próxima medição comece no dia seguinte', function (): void {
    // Sem isto, dois períodos poderiam se sobrepor e faturar o mesmo serviço
    // duas vezes. No PostgreSQL uma constraint de exclusão resolveria; no
    // SQLite a garantia é esta regra, mais restritiva e igualmente segura.
    $app = ambienteDeObraEmAndamento();

    apontar($app, '2026-02-10', 40.0);
    $app['gerarMedicao']->executar('OBR-2026-001', dia('2026-02-02'), dia('2026-02-28'));

    lanca(
        ExcecaoDeDominio::class,
        static fn () => $app['gerarMedicao']->executar(
            'OBR-2026-001',
            dia('2026-02-20'),
            dia('2026-03-20'),
        ),
        'precisa começar em 01/03/2026',
    );
});

teste('recusa primeira medição anterior ao início da obra', function (): void {
    $app = ambienteDeObraEmAndamento();
    apontar($app, '2026-02-10', 40.0);

    lanca(
        ExcecaoDeDominio::class,
        static fn () => $app['gerarMedicao']->executar(
            'OBR-2026-001',
            dia('2026-01-01'),
            dia('2026-02-28'),
        ),
        'antes da obra',
    );
});

teste('inclui no orçamento os serviços sem movimento', function (): void {
    $app = ambienteDeObraEmAndamento();
    $app['servicos']->salvar('OBR-2026-001', servicoDeExemplo('COB-01'));

    apontar($app, '2026-02-10', 40.0, 'ALV-01');

    $medicao = $app['gerarMedicao']->executar('OBR-2026-001', dia('2026-02-02'), dia('2026-02-28'));

    igual(2, count($medicao->itens()), 'os dois serviços aparecem');
    igual(1, count($medicao->itensComMovimento()), 'só um movimentou');
});

teste('a medição é lida de volta com a memória de cálculo inteira', function (): void {
    $app = ambienteDeObraEmAndamento();
    apontar($app, '2026-02-10', 96.0);

    $numero = $app['gerarMedicao']->executar(
        'OBR-2026-001',
        dia('2026-02-02'),
        dia('2026-02-28'),
    )->numero();

    $lida = $app['medicoes']->porNumero('OBR-2026-001', $numero);

    verdadeiro($lida !== null, 'medição encontrada');
    igual(1, count($lida?->itens() ?? []));
    igualAproximado(7536.0, $lida?->valorNoPeriodo() ?? 0.0);
    igualAproximado(78.50, $lida?->itens()[0]->precoUnitario ?? 0.0);
});

grupo('Fechar medição');

teste('fecha e o estado sobrevive à releitura', function (): void {
    $app = ambienteDeObraEmAndamento();
    apontar($app, '2026-02-10', 96.0);

    $medicao = $app['gerarMedicao']->executar('OBR-2026-001', dia('2026-02-02'), dia('2026-02-28'));
    $app['fecharMedicao']->executar('OBR-2026-001', $medicao->numero());

    verdadeiro(
        $app['medicoes']->porNumero('OBR-2026-001', $medicao->numero())?->estaFechada() ?? false,
        'continua fechada depois de recarregar',
    );
});

teste('recusa fechar duas vezes', function (): void {
    $app = ambienteDeObraEmAndamento();
    apontar($app, '2026-02-10', 96.0);

    $medicao = $app['gerarMedicao']->executar('OBR-2026-001', dia('2026-02-02'), dia('2026-02-28'));
    $app['fecharMedicao']->executar('OBR-2026-001', $medicao->numero());

    lanca(
        ExcecaoDeDominio::class,
        static fn () => $app['fecharMedicao']->executar('OBR-2026-001', $medicao->numero()),
        'já está fechada',
    );
});

teste('exige fechar as competências em ordem', function (): void {
    $app = ambienteDeObraEmAndamento();

    apontar($app, '2026-02-10', 40.0);
    apontar($app, '2026-03-05', 30.0);

    $app['gerarMedicao']->executar('OBR-2026-001', dia('2026-02-02'), dia('2026-02-28'));
    $segunda = $app['gerarMedicao']->executar('OBR-2026-001', dia('2026-03-01'), dia('2026-03-31'));

    lanca(
        ExcecaoDeDominio::class,
        static fn () => $app['fecharMedicao']->executar('OBR-2026-001', $segunda->numero()),
        'Feche as competências em ordem',
    );
});

teste('recusa fechar medição inexistente', function (): void {
    $app = ambienteDeObraEmAndamento();

    lanca(
        ExcecaoDeDominio::class,
        static fn () => $app['fecharMedicao']->executar('OBR-2026-001', 99),
        'Não existe medição',
    );
});

grupo('Curva de avanço');

teste('acumula o valor executado ao longo do tempo', function (): void {
    $curva = new CurvaDeAvanco(
        ['2026-02-10' => 1000.0, '2026-02-20' => 3000.0],
        10000.0,
        dia('2026-02-01'),
        dia('2026-02-28'),
    );

    $pontos = $curva->pontos();

    igual('2026-02-01', $pontos[0]['data'], 'começa no início da obra');
    igualAproximado(0.0, $pontos[0]['percentual']);
    igualAproximado(10.0, $pontos[1]['percentual'], 'mil de dez mil');
    igualAproximado(40.0, $pontos[2]['percentual'], 'acumulado de quatro mil');
    igualAproximado(40.0, $curva->percentualFinal());
});

teste('fecha a curva no último dia do período', function (): void {
    $curva = new CurvaDeAvanco(
        ['2026-02-10' => 5000.0],
        10000.0,
        dia('2026-02-01'),
        dia('2026-02-28'),
    );

    $pontos = $curva->pontos();

    igual('2026-02-28', $pontos[count($pontos) - 1]['data']);
    igualAproximado(50.0, $pontos[count($pontos) - 1]['percentual']);
});

teste('sem movimento não desenha curva', function (): void {
    $curva = new CurvaDeAvanco([], 10000.0, dia('2026-02-01'), dia('2026-02-28'));

    falso($curva->temMovimento(), 'sem movimento');
    igualAproximado(0.0, $curva->percentualFinal());
});

teste('o avanço linear esperado acompanha o calendário', function (): void {
    $curva = new CurvaDeAvanco([], 10000.0, dia('2026-02-01'), dia('2026-02-10'));

    igualAproximado(10.0, $curva->avancoLinearEsperado(dia('2026-02-01')), 'primeiro de dez dias');
    igualAproximado(50.0, $curva->avancoLinearEsperado(dia('2026-02-05')));
    igualAproximado(100.0, $curva->avancoLinearEsperado(dia('2026-02-10')));
    igualAproximado(100.0, $curva->avancoLinearEsperado(dia('2026-03-01')), 'não passa de 100');
});

teste('o ritmo esperado é do prazo contratual, não do trecho desenhado', function (): void {
    // Obra de 100 dias, desenhada só até o dia 25: o esperado hoje é 25 %,
    // e não 100 % por a curva terminar hoje.
    $curva = new CurvaDeAvanco(
        ['2026-02-10' => 1000.0],
        10000.0,
        dia('2026-02-01'),
        dia('2026-02-25'),
        dia('2026-05-11'),
    );

    igualAproximado(25.0, $curva->avancoLinearEsperado(dia('2026-02-25')));
    igualAproximado(10.0, $curva->percentualFinal(), 'o executado continua sendo o que foi apontado');
});

teste('gera pontos de SVG dentro da área da imagem', function (): void {
    $curva = new CurvaDeAvanco(
        ['2026-02-10' => 5000.0, '2026-02-28' => 5000.0],
        10000.0,
        dia('2026-02-01'),
        dia('2026-02-28'),
    );

    $coordenadas = explode(' ', $curva->polilinha(700, 200));

    igual(3, count($coordenadas), 'início mais dois dias com movimento');

    foreach ($coordenadas as $par) {
        [$x, $y] = array_map(floatval(...), explode(',', $par));

        verdadeiro($x >= 0 && $x <= 700, "x dentro da largura: {$x}");
        verdadeiro($y >= 0 && $y <= 200, "y dentro da altura: {$y}");
    }
});
