<?php

declare(strict_types=1);

use GestaoObras\Dominio\Obra\SituacaoDaObra;
use GestaoObras\Infraestrutura\Repositorio\RepositorioDeObrasEmSqlite;

grupo('Repositório de obras');

teste('grava e lê a obra de volta inteira', function (): void {
    $repositorio = new RepositorioDeObrasEmSqlite(bancoDeTeste());
    $repositorio->salvar(obraDeExemplo());

    $lida = $repositorio->porCodigo('OBR-2026-001');

    verdadeiro($lida !== null, 'obra encontrada');
    igual('Reforma estrutural do galpão 3', $lida?->nome);
    igual('Terminal Portuário Litoral S.A.', $lida?->cliente);
    igual('Santos', $lida?->endereco->cidade);
    igual('11015300', $lida?->endereco->cep);
    igual('2026-02-02', $lida?->dataDeInicio->format('Y-m-d'));
    igual(120, $lida?->prazoEmDias);
    igual('CREA-SP 5069874521/D', $lida?->registroProfissional);
});

teste('preserva a situação ao recarregar', function (): void {
    $repositorio = new RepositorioDeObrasEmSqlite(bancoDeTeste());

    $obra = obraDeExemplo();
    $obra->iniciar();
    $obra->paralisar();
    $repositorio->salvar($obra);

    igual(SituacaoDaObra::Paralisada, $repositorio->porCodigo('OBR-2026-001')?->situacao());
});

teste('devolve null para código que não existe', function (): void {
    $repositorio = new RepositorioDeObrasEmSqlite(bancoDeTeste());

    igual(null, $repositorio->porCodigo('OBR-INEXISTENTE'));
});

teste('encontra pelo código em minúsculas', function (): void {
    $repositorio = new RepositorioDeObrasEmSqlite(bancoDeTeste());
    $repositorio->salvar(obraDeExemplo());

    verdadeiro($repositorio->porCodigo('obr-2026-001') !== null, 'busca sem diferenciar caixa');
});

teste('salvar de novo atualiza em vez de duplicar', function (): void {
    $conexao = bancoDeTeste();
    $repositorio = new RepositorioDeObrasEmSqlite($conexao);

    $repositorio->salvar(obraDeExemplo());

    $obra = obraDeExemplo();
    $obra->iniciar();
    $repositorio->salvar($obra);

    igual(1, (int) $conexao->query('SELECT COUNT(*) FROM obras')->fetchColumn());
    igual(SituacaoDaObra::EmAndamento, $repositorio->porCodigo('OBR-2026-001')?->situacao());
});

teste('lista todas ordenadas por código', function (): void {
    $repositorio = new RepositorioDeObrasEmSqlite(bancoDeTeste());

    $repositorio->salvar(obraDeExemplo('OBR-2026-003'));
    $repositorio->salvar(obraDeExemplo('OBR-2026-001'));
    $repositorio->salvar(obraDeExemplo('OBR-2026-002'));

    $codigos = array_map(static fn ($obra) => $obra->codigo, $repositorio->todas());

    igual(['OBR-2026-001', 'OBR-2026-002', 'OBR-2026-003'], $codigos);
});

teste('filtra por situação', function (): void {
    $repositorio = new RepositorioDeObrasEmSqlite(bancoDeTeste());

    $planejada = obraDeExemplo('OBR-P');
    $andamento = obraDeExemplo('OBR-A');
    $andamento->iniciar();

    $repositorio->salvar($planejada);
    $repositorio->salvar($andamento);

    igual(1, count($repositorio->porSituacao(SituacaoDaObra::EmAndamento)));
    igual('OBR-A', $repositorio->porSituacao(SituacaoDaObra::EmAndamento)[0]->codigo);
    igual(0, count($repositorio->porSituacao(SituacaoDaObra::Concluida)));
});

teste('responde se a obra existe', function (): void {
    $repositorio = new RepositorioDeObrasEmSqlite(bancoDeTeste());
    $repositorio->salvar(obraDeExemplo());

    verdadeiro($repositorio->existe('OBR-2026-001'), 'existe');
    falso($repositorio->existe('OBR-2026-999'), 'não existe');
});

teste('remove a obra', function (): void {
    $repositorio = new RepositorioDeObrasEmSqlite(bancoDeTeste());
    $repositorio->salvar(obraDeExemplo());

    $repositorio->remover('OBR-2026-001');

    falso($repositorio->existe('OBR-2026-001'), 'removida');
});
