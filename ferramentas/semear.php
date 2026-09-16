<?php

declare(strict_types=1);

/*
 * Carrega os dados de demonstração.
 *
 *   php ferramentas/semear.php
 *
 * A carga é feita em PHP, pelo domínio e pelos casos de uso, e não em SQL:
 * o avanço de cada serviço nasce de diários de obra de verdade, e as
 * medições são geradas a partir deles — exatamente como acontece pela tela.
 * Um SQL que gravasse "quantidade executada = 5250" sem os diários por trás
 * deixaria a curva de avanço vazia e a medição zerada.
 *
 * As datas são relativas a hoje, para a obra em andamento estar sempre no
 * meio do prazo e a medição do mês corrente sempre em aberto.
 *
 * Idempotente: se a obra de demonstração já existe, só as contas são
 * conferidas. Para recarregar do zero, apague banco/gestao-obras.sqlite e
 * rode migrar.php e semear.php de novo.
 */

require __DIR__ . '/../src/autoload.php';

use GestaoObras\Aplicacao\FecharMedicao;
use GestaoObras\Aplicacao\GerarMedicao;
use GestaoObras\Aplicacao\RegistrarDiarioDeObra;
use GestaoObras\Dominio\Diario\AtividadeExecutada;
use GestaoObras\Dominio\Diario\Clima;
use GestaoObras\Dominio\Diario\ClimaDoDia;
use GestaoObras\Dominio\Diario\CondicaoDeTrabalho;
use GestaoObras\Dominio\Diario\DiarioDeObra;
use GestaoObras\Dominio\Diario\Efetivo;
use GestaoObras\Dominio\Diario\Ocorrencia;
use GestaoObras\Dominio\Diario\TipoDeOcorrencia;
use GestaoObras\Dominio\Obra\Endereco;
use GestaoObras\Dominio\Obra\Obra;
use GestaoObras\Dominio\Servico\Servico;
use GestaoObras\Dominio\Servico\Unidade;
use GestaoObras\Dominio\Usuario\Papel;
use GestaoObras\Dominio\Usuario\Usuario;
use GestaoObras\Infraestrutura\Banco\Conexao;
use GestaoObras\Infraestrutura\Banco\Migrador;
use GestaoObras\Infraestrutura\Repositorio\RepositorioDeDiariosEmSqlite;
use GestaoObras\Infraestrutura\Repositorio\RepositorioDeMedicoesEmSqlite;
use GestaoObras\Infraestrutura\Repositorio\RepositorioDeObrasEmSqlite;
use GestaoObras\Infraestrutura\Repositorio\RepositorioDeServicosEmSqlite;
use GestaoObras\Infraestrutura\Repositorio\RepositorioDeUsuariosEmSqlite;

$conexao = Conexao::abrir();

if (Migrador::padrao($conexao)->pendentes() !== []) {
    fwrite(STDERR, 'Há migrations pendentes. Rode php ferramentas/migrar.php antes.' . PHP_EOL);
    exit(1);
}

$obras = new RepositorioDeObrasEmSqlite($conexao);
$servicos = new RepositorioDeServicosEmSqlite($conexao);
$diarios = new RepositorioDeDiariosEmSqlite($conexao);
$medicoes = new RepositorioDeMedicoesEmSqlite($conexao);
$usuarios = new RepositorioDeUsuariosEmSqlite($conexao);

$registrarDiario = new RegistrarDiarioDeObra($conexao, $obras, $servicos, $diarios);
$gerarMedicao = new GerarMedicao($conexao, $obras, $servicos, $diarios, $medicoes);
$fecharMedicao = new FecharMedicao($medicoes);

const OBRA_EM_ANDAMENTO = 'OBR-2026-001';
const OBRA_PLANEJADA = 'OBR-2026-002';
const OBRA_CONCLUIDA = 'OBR-2026-003';

const MESTRE = 'Helena Duarte';

/** Um dia relativo a hoje: dia(-1) é ontem. */
function dia(int $deslocamento): DateTimeImmutable
{
    return (new DateTimeImmutable('today'))->modify(sprintf('%+d days', $deslocamento));
}

/**
 * Reparte um total em N parcelas com duas casas, somando exatamente o total.
 * A diferença de arredondamento vai para a última parcela — sem isso a soma
 * dos diários passaria do previsto por um centavo e o domínio recusaria.
 *
 * @return float[]
 */
function repartir(float $total, int $parcelas): array
{
    if ($parcelas <= 0) {
        return [];
    }

    $parcela = floor($total / $parcelas * 100) / 100;
    $valores = array_fill(0, $parcelas, $parcela);
    $valores[$parcelas - 1] = round($total - $parcela * ($parcelas - 1), 2);

    return $valores;
}

/**
 * Registra um diário por dia útil, da data de início até a data final,
 * repartindo a quantidade de cada serviço pelos dias da janela dele.
 *
 * @param array<int, array{codigo: string, total: float, de: int, ate: ?int}> $plano
 *        janela em semanas contadas do início da obra; "ate" nulo vai até o fim
 * @param int[] $diasPerdidos índices (a partir do início) dos dias de chuva forte
 * @param array<int, array{dia: int, tipo: TipoDeOcorrencia, descricao: string}> $ocorrencias
 */
function registrarDiarios(
    RegistrarDiarioDeObra $registrar,
    string $obraCodigo,
    DateTimeImmutable $inicio,
    DateTimeImmutable $fim,
    array $plano,
    array $diasPerdidos,
    array $ocorrencias,
    Efetivo $efetivoPadrao,
): int {
    // Os dias úteis, indexados a partir do início; domingo não tem diário.
    $uteis = [];

    for ($indice = 0, $data = $inicio; $data <= $fim; $indice++, $data = $data->modify('+1 day')) {
        if ($data->format('N') === '7') {
            continue;
        }

        $uteis[$indice] = $data;
    }

    // Quanto cada serviço executa em cada dia, já repartido pela janela.
    $porDia = [];

    foreach ($plano as $servico) {
        $diasDaJanela = array_filter(
            array_keys($uteis),
            static fn (int $indice): bool => !in_array($indice, $diasPerdidos, true)
                && intdiv($indice, 7) >= $servico['de']
                && ($servico['ate'] === null || intdiv($indice, 7) <= $servico['ate']),
        );

        $diasDaJanela = array_values($diasDaJanela);

        foreach (repartir($servico['total'], count($diasDaJanela)) as $posicao => $quantidade) {
            if ($quantidade > 0) {
                $porDia[$diasDaJanela[$posicao]][$servico['codigo']] = $quantidade;
            }
        }
    }

    $registrados = 0;

    foreach ($uteis as $indice => $data) {
        $ehSabado = $data->format('N') === '6';
        $perdido = in_array($indice, $diasPerdidos, true);

        $diario = new DiarioDeObra(
            $obraCodigo,
            $data,
            $perdido ? ClimaDoDia::diaPerdido() : ClimaDoDia::diaTrabalhavel($indice % 5 === 3 ? Clima::Nublado : Clima::Bom),
            MESTRE,
        );

        if ($perdido) {
            $diario->registrarOcorrencia(new Ocorrencia(
                TipoDeOcorrencia::Paralisacao,
                'Chuva forte durante todo o dia. Frentes de serviço alagadas; equipe dispensada às 9h por segurança.',
            ));
        } else {
            $diario->definirEfetivo($ehSabado ? Efetivo::de(['encarregado' => 1, 'pedreiro' => 2, 'servente' => 2]) : $efetivoPadrao);

            foreach ($porDia[$indice] ?? [] as $codigo => $quantidade) {
                $diario->registrarAtividade(new AtividadeExecutada($codigo, $quantidade));
            }
        }

        foreach ($ocorrencias as $ocorrencia) {
            if ($ocorrencia['dia'] === $indice) {
                $diario->registrarOcorrencia(new Ocorrencia($ocorrencia['tipo'], $ocorrencia['descricao']));
            }
        }

        // Dia útil sem atividade e sem ocorrência não vira diário: o domínio
        // recusa, e com razão — não há o que registrar.
        if ($diario->atividades() === [] && $diario->ocorrencias() === []) {
            continue;
        }

        $registrar->executar($diario);
        $registrados++;
    }

    return $registrados;
}

/**
 * Gera uma medição por competência mensal, do início até a data final, e
 * fecha todas menos a última quando ela ainda está em curso.
 */
function medirPorCompetencia(
    GerarMedicao $gerar,
    FecharMedicao $fechar,
    string $obraCodigo,
    DateTimeImmutable $inicio,
    DateTimeImmutable $ate,
    bool $fecharAUltima,
): int {
    $geradas = 0;
    $periodoInicio = $inicio;

    while ($periodoInicio <= $ate) {
        $fimDoMes = $periodoInicio->modify('last day of this month');
        $periodoFim = $fimDoMes < $ate ? $fimDoMes : $ate;

        $medicao = $gerar->executar($obraCodigo, $periodoInicio, $periodoFim);
        $geradas++;

        if ($periodoFim < $ate || $fecharAUltima) {
            $fechar->executar($obraCodigo, $medicao->numero());
        }

        $periodoInicio = $periodoFim->modify('+1 day');
    }

    return $geradas;
}

$resumo = ['diarios' => 0, 'medicoes' => 0];

if ($obras->existe(OBRA_EM_ANDAMENTO)) {
    echo 'As obras de demonstração já existem; só as contas foram conferidas.', PHP_EOL;
} else {
    /*
     * Galpão 3: em andamento há dois meses, com prazo de cinco. A demolição
     * terminou, o reforço metálico está na metade e a alvenaria começou.
     */
    $galpao = new Obra(
        OBRA_EM_ANDAMENTO,
        'Reforma estrutural do galpão 3',
        'Terminal Portuário Litoral S.A.',
        new Endereco('Avenida Eng. Augusto Barata', '780', 'Macuco', 'Santos', 'SP', '11015-300'),
        dia(-62),
        150,
        'Marcus Bomfim',
        'CREA-SP 5069874521/D',
    );
    $galpao->iniciar();
    $obras->salvar($galpao);

    foreach ([
        ['DEM-01', 'Demolição de alvenaria existente com remoção de entulho', Unidade::MetroCubico, 145.0, 96.40],
        ['EST-01', 'Reforço estrutural em perfil metálico ASTM A572', Unidade::Quilograma, 8400.0, 22.75],
        ['ALV-01', 'Alvenaria de vedação em bloco cerâmico 14x19x39', Unidade::MetroQuadrado, 320.0, 78.50],
        ['COB-01', 'Cobertura em telha metálica trapezoidal 0,50mm', Unidade::MetroQuadrado, 1180.0, 142.30],
        ['PIN-01', 'Pintura epóxi sobre estrutura metálica, duas demãos', Unidade::MetroQuadrado, 640.0, 54.90],
        ['ADM-01', 'Administração local e mobilização de canteiro', Unidade::Verba, 1.0, 84000.00],
    ] as [$codigo, $descricao, $unidade, $prevista, $preco]) {
        $servicos->salvar(OBRA_EM_ANDAMENTO, new Servico($codigo, $descricao, $unidade, $prevista, $preco));
    }

    $resumo['diarios'] += registrarDiarios(
        $registrarDiario,
        OBRA_EM_ANDAMENTO,
        dia(-62),
        dia(-1),
        [
            ['codigo' => 'DEM-01', 'total' => 145.0, 'de' => 0, 'ate' => 2],
            ['codigo' => 'EST-01', 'total' => 5250.0, 'de' => 3, 'ate' => null],
            ['codigo' => 'ALV-01', 'total' => 96.0, 'de' => 6, 'ate' => null],
        ],
        [9, 31],
        [
            ['dia' => 0, 'tipo' => TipoDeOcorrencia::Outro, 'descricao' => 'Mobilização do canteiro: instalação do container de escritório e do tapume.'],
            ['dia' => 21, 'tipo' => TipoDeOcorrencia::EntregaDeMaterial, 'descricao' => 'Recebidos 4,2 t de perfis metálicos ASTM A572, conferidos com a nota fiscal.'],
            ['dia' => 25, 'tipo' => TipoDeOcorrencia::Visita, 'descricao' => 'Visita do engenheiro do cliente para acompanhar o início do reforço estrutural.'],
            ['dia' => 44, 'tipo' => TipoDeOcorrencia::Inspecao, 'descricao' => 'Inspeção de solda por líquido penetrante nas emendas dos perfis do eixo 3.'],
        ],
        Efetivo::de(['engenheiro' => 1, 'encarregado' => 1, 'tecnico_seguranca' => 1, 'pedreiro' => 4, 'servente' => 6, 'armador' => 2]),
    );

    $resumo['medicoes'] += medirPorCompetencia($gerarMedicao, $fecharMedicao, OBRA_EM_ANDAMENTO, dia(-62), dia(-1), false);

    /*
     * Vista Serra: contrato assinado, começa no mês que vem. Serve para
     * mostrar uma obra planejada, com orçamento e nada executado.
     */
    $vistaSerra = new Obra(
        OBRA_PLANEJADA,
        'Edifício residencial Vista Serra',
        'Construtora Vale Verde Ltda.',
        new Endereco('Rua Frei Gaspar', '1420', 'Centro', 'São Vicente', 'SP', '11310-061'),
        dia(30),
        540,
        'Helena Duarte',
        'CAU A118472-3',
    );
    $obras->salvar($vistaSerra);

    foreach ([
        ['FUN-01', 'Estaca hélice contínua diâmetro 40cm', Unidade::MetroLinear, 1860.0, 189.00],
        ['CON-01', 'Concreto usinado fck 30 MPa bombeado', Unidade::MetroCubico, 2240.0, 612.50],
        ['FOR-01', 'Forma em chapa compensada plastificada 18mm', Unidade::MetroQuadrado, 9800.0, 68.20],
        ['ACO-01', 'Armadura em aço CA-50, corte, dobra e montagem', Unidade::Quilograma, 186000.0, 12.90],
        ['ESQ-01', 'Porta de madeira semi-oca 80x210 com batente', Unidade::Unidade, 148.0, 640.00],
    ] as [$codigo, $descricao, $unidade, $prevista, $preco]) {
        $servicos->salvar(OBRA_PLANEJADA, new Servico($codigo, $descricao, $unidade, $prevista, $preco));
    }

    /*
     * Pátio de contêineres: 90 dias de obra, entregue há mais de três meses.
     * Começa no primeiro dia de um mês para as competências ficarem inteiras.
     * Tudo executado por diário e medido em competências fechadas.
     */
    $inicioDoPatio = dia(-200)->modify('first day of this month');
    $entregaDoPatio = $inicioDoPatio->modify('+86 days');

    $patio = new Obra(
        OBRA_CONCLUIDA,
        'Pavimentação do pátio de contêineres',
        'Terminal Portuário Litoral S.A.',
        new Endereco('Avenida Perimetral', '2100', 'Alemoa', 'Santos', 'SP', '11095-400'),
        $inicioDoPatio,
        90,
        'Marcus Bomfim',
        'CREA-SP 5069874521/D',
    );
    $patio->iniciar();
    $obras->salvar($patio);

    foreach ([
        ['TER-01', 'Terraplenagem e regularização do subleito', Unidade::MetroQuadrado, 14500.0, 18.60],
        ['PAV-01', 'Pavimento rígido em concreto fck 35 MPa, espessura 22cm', Unidade::MetroQuadrado, 14500.0, 214.80],
        ['DRE-01', 'Drenagem em tubo de concreto DN 400', Unidade::MetroLinear, 620.0, 168.40],
        ['SIN-01', 'Sinalização horizontal em resina acrílica', Unidade::MetroQuadrado, 840.0, 42.70],
    ] as [$codigo, $descricao, $unidade, $prevista, $preco]) {
        $servicos->salvar(OBRA_CONCLUIDA, new Servico($codigo, $descricao, $unidade, $prevista, $preco));
    }

    $resumo['diarios'] += registrarDiarios(
        $registrarDiario,
        OBRA_CONCLUIDA,
        $inicioDoPatio,
        $entregaDoPatio,
        [
            ['codigo' => 'TER-01', 'total' => 14500.0, 'de' => 0, 'ate' => 3],
            ['codigo' => 'DRE-01', 'total' => 620.0, 'de' => 2, 'ate' => 5],
            ['codigo' => 'PAV-01', 'total' => 14500.0, 'de' => 4, 'ate' => 10],
            ['codigo' => 'SIN-01', 'total' => 840.0, 'de' => 11, 'ate' => null],
        ],
        [16, 53],
        [
            ['dia' => 37, 'tipo' => TipoDeOcorrencia::Inspecao, 'descricao' => 'Controle tecnológico do concreto: moldados 6 corpos de prova da primeira placa.'],
            ['dia' => 86, 'tipo' => TipoDeOcorrencia::Visita, 'descricao' => 'Vistoria de entrega com o cliente; pátio liberado para operação.'],
        ],
        Efetivo::de(['engenheiro' => 1, 'encarregado' => 2, 'tecnico_seguranca' => 1, 'pedreiro' => 6, 'servente' => 8, 'operador_equipamento' => 3]),
    );

    $resumo['medicoes'] += medirPorCompetencia($gerarMedicao, $fecharMedicao, OBRA_CONCLUIDA, $inicioDoPatio, $entregaDoPatio, true);

    $patio->concluir();
    $obras->salvar($patio);
}

/*
 * As contas ficam fora de qualquer SQL de propósito: o hash da senha precisa
 * ser gerado pelo password_hash() do PHP, com sal aleatório, e não copiado de
 * um arquivo. Conta que já existe não é sobrescrita — se você trocou a senha,
 * ela fica.
 */
$contas = [
    ['engenheiro@obras.dev', 'Marcus Bomfim', Papel::Engenheiro, 'Engenheiro@123'],
    ['mestre@obras.dev', 'Helena Duarte', Papel::MestreDeObras, 'Mestre@123'],
    ['cliente@obras.dev', 'Rafael Nunes', Papel::Cliente, 'Cliente@123'],
];

$criadas = 0;

foreach ($contas as [$email, $nome, $papel, $senha]) {
    if ($usuarios->existe($email)) {
        continue;
    }

    $usuarios->salvar(Usuario::criar($email, $nome, $papel, $senha));
    $criadas++;
}

printf(
    'Banco carregado: %d obras, %d serviços, %d diários, %d medições e %d conta(s) nova(s).%s',
    (int) $conexao->query('SELECT COUNT(*) FROM obras')->fetchColumn(),
    (int) $conexao->query('SELECT COUNT(*) FROM servicos')->fetchColumn(),
    $resumo['diarios'],
    $resumo['medicoes'],
    $criadas,
    PHP_EOL,
);
