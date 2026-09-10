<?php

declare(strict_types=1);

use GestaoObras\Dominio\Servico\Unidade;
use GestaoObras\Infraestrutura\Repositorio\RepositorioDeObrasEmSqlite;
use GestaoObras\Infraestrutura\Repositorio\RepositorioDeServicosEmSqlite;

/** @return array{0: RepositorioDeObrasEmSqlite, 1: RepositorioDeServicosEmSqlite, 2: PDO} */
function repositoriosComObra(): array
{
    $conexao = bancoDeTeste();

    $obras = new RepositorioDeObrasEmSqlite($conexao);
    $obras->salvar(obraDeExemplo());

    return [$obras, new RepositorioDeServicosEmSqlite($conexao), $conexao];
}

grupo('Repositório de serviços');

teste('grava e lê o serviço de volta', function (): void {
    [, $servicos] = repositoriosComObra();
    $servicos->salvar('OBR-2026-001', servicoDeExemplo());

    $lido = $servicos->porCodigo('OBR-2026-001', 'ALV-01');

    verdadeiro($lido !== null, 'serviço encontrado');
    igual('Alvenaria de vedação em bloco cerâmico 14x19x39', $lido?->descricao);
    igual(Unidade::MetroQuadrado, $lido?->unidade);
    igualAproximado(320.0, $lido?->quantidadePrevista ?? 0.0);
    igualAproximado(78.50, $lido?->precoUnitario ?? 0.0);
});

teste('preserva o que já foi apontado', function (): void {
    [, $servicos] = repositoriosComObra();

    $servico = servicoDeExemplo();
    $servico->registrarExecucao(96.0);
    $servicos->salvar('OBR-2026-001', $servico);

    $lido = $servicos->porCodigo('OBR-2026-001', 'ALV-01');

    igualAproximado(96.0, $lido?->quantidadeExecutada() ?? 0.0);
    igualAproximado(30.0, $lido?->percentualExecutado() ?? 0.0);
});

teste('continua apontando depois de recarregar', function (): void {
    [, $servicos] = repositoriosComObra();

    $servico = servicoDeExemplo();
    $servico->registrarExecucao(100.0);
    $servicos->salvar('OBR-2026-001', $servico);

    $recarregado = $servicos->porCodigo('OBR-2026-001', 'ALV-01');
    $recarregado?->registrarExecucao(50.0);
    $servicos->salvar('OBR-2026-001', $recarregado);

    igualAproximado(
        150.0,
        $servicos->porCodigo('OBR-2026-001', 'ALV-01')?->quantidadeExecutada() ?? 0.0,
    );
});

teste('lista os serviços da obra ordenados', function (): void {
    [, $servicos] = repositoriosComObra();

    $servicos->salvar('OBR-2026-001', servicoDeExemplo('PIN-01'));
    $servicos->salvar('OBR-2026-001', servicoDeExemplo('ALV-01'));
    $servicos->salvar('OBR-2026-001', servicoDeExemplo('COB-01'));

    $codigos = array_map(static fn ($servico) => $servico->codigo, $servicos->daObra('OBR-2026-001'));

    igual(['ALV-01', 'COB-01', 'PIN-01'], $codigos);
});

teste('serviços de outra obra não aparecem na lista', function (): void {
    [$obras, $servicos] = repositoriosComObra();
    $obras->salvar(obraDeExemplo('OBR-2026-002'));

    $servicos->salvar('OBR-2026-001', servicoDeExemplo('ALV-01'));
    $servicos->salvar('OBR-2026-002', servicoDeExemplo('ALV-01'));

    igual(1, count($servicos->daObra('OBR-2026-001')));
    igual(1, count($servicos->daObra('OBR-2026-002')));
});

teste('o mesmo código pode existir em obras diferentes', function (): void {
    [$obras, $servicos] = repositoriosComObra();
    $obras->salvar(obraDeExemplo('OBR-2026-002'));

    $primeiro = servicoDeExemplo('ALV-01');
    $primeiro->registrarExecucao(96.0);

    $servicos->salvar('OBR-2026-001', $primeiro);
    $servicos->salvar('OBR-2026-002', servicoDeExemplo('ALV-01'));

    igualAproximado(96.0, $servicos->porCodigo('OBR-2026-001', 'ALV-01')?->quantidadeExecutada() ?? -1.0);
    igualAproximado(0.0, $servicos->porCodigo('OBR-2026-002', 'ALV-01')?->quantidadeExecutada() ?? -1.0);
});

teste('salvar de novo atualiza em vez de duplicar', function (): void {
    [, $servicos, $conexao] = repositoriosComObra();

    $servicos->salvar('OBR-2026-001', servicoDeExemplo());
    $servicos->salvar('OBR-2026-001', servicoDeExemplo());

    igual(1, (int) $conexao->query('SELECT COUNT(*) FROM servicos')->fetchColumn());
});

teste('devolve null para serviço que não existe', function (): void {
    [, $servicos] = repositoriosComObra();

    igual(null, $servicos->porCodigo('OBR-2026-001', 'NAO-EXISTE'));
});

teste('remove o serviço sem tocar nos outros', function (): void {
    [, $servicos] = repositoriosComObra();

    $servicos->salvar('OBR-2026-001', servicoDeExemplo('ALV-01'));
    $servicos->salvar('OBR-2026-001', servicoDeExemplo('COB-01'));

    $servicos->remover('OBR-2026-001', 'ALV-01');

    igual(1, count($servicos->daObra('OBR-2026-001')));
    igual('COB-01', $servicos->daObra('OBR-2026-001')[0]->codigo);
});

teste('remover a obra leva os serviços junto', function (): void {
    [$obras, $servicos] = repositoriosComObra();
    $servicos->salvar('OBR-2026-001', servicoDeExemplo());

    $obras->remover('OBR-2026-001');

    igual([], $servicos->daObra('OBR-2026-001'));
});
