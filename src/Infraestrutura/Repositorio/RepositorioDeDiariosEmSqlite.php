<?php

declare(strict_types=1);

namespace GestaoObras\Infraestrutura\Repositorio;

use DateTimeImmutable;
use GestaoObras\Dominio\Diario\AtividadeExecutada;
use GestaoObras\Dominio\Diario\ClimaDoDia;
use GestaoObras\Dominio\Diario\DiarioDeObra;
use GestaoObras\Dominio\Diario\Efetivo;
use GestaoObras\Dominio\Diario\Ocorrencia;
use GestaoObras\Dominio\Diario\RepositorioDeDiarios;
use GestaoObras\Dominio\Diario\TipoDeOcorrencia;
use PDO;
use Throwable;

final class RepositorioDeDiariosEmSqlite implements RepositorioDeDiarios
{
    private const COLUNAS = 'obra_codigo, numero, data, clima_manha, condicao_manha,
        clima_tarde, condicao_tarde, clima_noite, condicao_noite, responsavel';

    public function __construct(private readonly PDO $conexao)
    {
    }

    public function salvar(DiarioDeObra $diario): int
    {
        return $this->emTransacao(function () use ($diario): int {
            $numero = $diario->numero() > 0
                ? $diario->numero()
                : $this->proximoNumero($diario->obraCodigo);

            $colunas = $diario->clima->paraColunas();

            $comando = $this->conexao->prepare(
                'INSERT INTO diarios (' . self::COLUNAS . ')
                 VALUES (:obra_codigo, :numero, :data, :clima_manha, :condicao_manha,
                         :clima_tarde, :condicao_tarde, :clima_noite, :condicao_noite,
                         :responsavel)'
            );

            $comando->execute([
                ':obra_codigo' => $diario->obraCodigo,
                ':numero' => $numero,
                ':data' => $diario->data->format('Y-m-d'),
                ':clima_manha' => $colunas['clima_manha'],
                ':condicao_manha' => $colunas['condicao_manha'],
                ':clima_tarde' => $colunas['clima_tarde'],
                ':condicao_tarde' => $colunas['condicao_tarde'],
                ':clima_noite' => $colunas['clima_noite'],
                ':condicao_noite' => $colunas['condicao_noite'],
                ':responsavel' => $diario->responsavel,
            ]);

            $this->gravarEfetivo($diario, $numero);
            $this->gravarAtividades($diario, $numero);
            $this->gravarOcorrencias($diario, $numero);

            /*
             * O número é devolvido, e não gravado no objeto: se a transação de
             * quem chamou desfizer tudo depois, a entidade ficaria carregando
             * um número que não existe no banco. Quem confirma é quem marca.
             */
            return $numero;
        });
    }

    public function porData(string $obraCodigo, DateTimeImmutable $data): ?DiarioDeObra
    {
        $consulta = $this->conexao->prepare(
            'SELECT ' . self::COLUNAS . ' FROM diarios
             WHERE obra_codigo = :obra_codigo AND data = :data'
        );
        $consulta->execute([
            ':obra_codigo' => self::normalizar($obraCodigo),
            ':data' => $data->format('Y-m-d'),
        ]);

        return $this->montarVarios($consulta->fetchAll())[0] ?? null;
    }

    public function porNumero(string $obraCodigo, int $numero): ?DiarioDeObra
    {
        $consulta = $this->conexao->prepare(
            'SELECT ' . self::COLUNAS . ' FROM diarios
             WHERE obra_codigo = :obra_codigo AND numero = :numero'
        );
        $consulta->execute([
            ':obra_codigo' => self::normalizar($obraCodigo),
            ':numero' => $numero,
        ]);

        return $this->montarVarios($consulta->fetchAll())[0] ?? null;
    }

    public function daObra(string $obraCodigo): array
    {
        $consulta = $this->conexao->prepare(
            'SELECT ' . self::COLUNAS . ' FROM diarios
             WHERE obra_codigo = :obra_codigo ORDER BY data DESC, numero DESC'
        );
        $consulta->execute([':obra_codigo' => self::normalizar($obraCodigo)]);

        return $this->montarVarios($consulta->fetchAll());
    }

    public function noPeriodo(
        string $obraCodigo,
        DateTimeImmutable $inicio,
        DateTimeImmutable $fim,
    ): array {
        $consulta = $this->conexao->prepare(
            'SELECT ' . self::COLUNAS . ' FROM diarios
             WHERE obra_codigo = :obra_codigo AND data BETWEEN :inicio AND :fim
             ORDER BY data, numero'
        );
        $consulta->execute([
            ':obra_codigo' => self::normalizar($obraCodigo),
            ':inicio' => $inicio->format('Y-m-d'),
            ':fim' => $fim->format('Y-m-d'),
        ]);

        return $this->montarVarios($consulta->fetchAll());
    }

    public function existeParaData(string $obraCodigo, DateTimeImmutable $data): bool
    {
        $consulta = $this->conexao->prepare(
            'SELECT 1 FROM diarios WHERE obra_codigo = :obra_codigo AND data = :data'
        );
        $consulta->execute([
            ':obra_codigo' => self::normalizar($obraCodigo),
            ':data' => $data->format('Y-m-d'),
        ]);

        return $consulta->fetchColumn() !== false;
    }

    /**
     * Conta no SQL em vez de carregar tudo e contar em PHP: é a consulta que
     * vira anexo de pedido de prorrogação, e pode cobrir a obra inteira.
     */
    public function diasImpraticaveis(
        string $obraCodigo,
        DateTimeImmutable $inicio,
        DateTimeImmutable $fim,
    ): int {
        $consulta = $this->conexao->prepare(
            "SELECT COUNT(*) FROM diarios
             WHERE obra_codigo = :obra_codigo
               AND data BETWEEN :inicio AND :fim
               AND condicao_manha = 'impraticavel'
               AND condicao_tarde = 'impraticavel'
               AND condicao_noite = 'impraticavel'"
        );
        $consulta->execute([
            ':obra_codigo' => self::normalizar($obraCodigo),
            ':inicio' => $inicio->format('Y-m-d'),
            ':fim' => $fim->format('Y-m-d'),
        ]);

        return (int) $consulta->fetchColumn();
    }

    public function somaPorServico(
        string $obraCodigo,
        ?DateTimeImmutable $inicio,
        DateTimeImmutable $fim,
    ): array {
        $filtroDeInicio = $inicio === null ? '' : ' AND d.data >= :inicio';

        $consulta = $this->conexao->prepare(
            'SELECT a.servico_codigo AS servico, SUM(a.quantidade) AS total
             FROM diario_atividades a
             JOIN diarios d ON d.obra_codigo = a.obra_codigo AND d.numero = a.numero
             WHERE a.obra_codigo = :obra_codigo AND d.data <= :fim' . $filtroDeInicio . '
             GROUP BY a.servico_codigo
             ORDER BY a.servico_codigo'
        );

        $parametros = [
            ':obra_codigo' => self::normalizar($obraCodigo),
            ':fim' => $fim->format('Y-m-d'),
        ];

        if ($inicio !== null) {
            $parametros[':inicio'] = $inicio->format('Y-m-d');
        }

        $consulta->execute($parametros);

        $somas = [];

        foreach ($consulta->fetchAll() as $linha) {
            $somas[(string) $linha['servico']] = (float) $linha['total'];
        }

        return $somas;
    }

    public function valorExecutadoPorDia(
        string $obraCodigo,
        DateTimeImmutable $inicio,
        DateTimeImmutable $fim,
    ): array {
        /*
         * A multiplicação pelo preço acontece no SQL: a curva pode cobrir a
         * obra inteira, e trazer cada atividade para o PHP só para multiplicar
         * seria carregar centenas de linhas à toa.
         */
        $consulta = $this->conexao->prepare(
            'SELECT d.data AS dia, SUM(a.quantidade * s.preco_unitario) AS valor
             FROM diario_atividades a
             JOIN diarios d ON d.obra_codigo = a.obra_codigo AND d.numero = a.numero
             JOIN servicos s ON s.obra_codigo = a.obra_codigo AND s.codigo = a.servico_codigo
             WHERE a.obra_codigo = :obra_codigo AND d.data BETWEEN :inicio AND :fim
             GROUP BY d.data
             ORDER BY d.data'
        );

        $consulta->execute([
            ':obra_codigo' => self::normalizar($obraCodigo),
            ':inicio' => $inicio->format('Y-m-d'),
            ':fim' => $fim->format('Y-m-d'),
        ]);

        $porDia = [];

        foreach ($consulta->fetchAll() as $linha) {
            $porDia[(string) $linha['dia']] = round((float) $linha['valor'], 2);
        }

        return $porDia;
    }

    public function remover(string $obraCodigo, int $numero): void
    {
        // Efetivo, atividades e ocorrências saem em cascata.
        $comando = $this->conexao->prepare(
            'DELETE FROM diarios WHERE obra_codigo = :obra_codigo AND numero = :numero'
        );
        $comando->execute([
            ':obra_codigo' => self::normalizar($obraCodigo),
            ':numero' => $numero,
        ]);
    }

    private function proximoNumero(string $obraCodigo): int
    {
        $consulta = $this->conexao->prepare(
            'SELECT COALESCE(MAX(numero), 0) + 1 FROM diarios WHERE obra_codigo = :obra_codigo'
        );
        $consulta->execute([':obra_codigo' => $obraCodigo]);

        return (int) $consulta->fetchColumn();
    }

    private function gravarEfetivo(DiarioDeObra $diario, int $numero): void
    {
        $mapa = $diario->efetivo()->paraArray();

        if ($mapa === []) {
            return;
        }

        $comando = $this->conexao->prepare(
            'INSERT INTO diario_efetivo (obra_codigo, numero, funcao, quantidade)
             VALUES (:obra_codigo, :numero, :funcao, :quantidade)'
        );

        foreach ($mapa as $funcao => $quantidade) {
            $comando->execute([
                ':obra_codigo' => $diario->obraCodigo,
                ':numero' => $numero,
                ':funcao' => $funcao,
                ':quantidade' => $quantidade,
            ]);
        }
    }

    private function gravarAtividades(DiarioDeObra $diario, int $numero): void
    {
        $comando = $this->conexao->prepare(
            'INSERT INTO diario_atividades
                (obra_codigo, numero, servico_codigo, quantidade, observacao)
             VALUES (:obra_codigo, :numero, :servico_codigo, :quantidade, :observacao)'
        );

        foreach ($diario->atividades() as $atividade) {
            $comando->execute([
                ':obra_codigo' => $diario->obraCodigo,
                ':numero' => $numero,
                ':servico_codigo' => $atividade->servicoCodigo,
                ':quantidade' => $atividade->quantidade,
                ':observacao' => $atividade->observacao,
            ]);
        }
    }

    private function gravarOcorrencias(DiarioDeObra $diario, int $numero): void
    {
        $comando = $this->conexao->prepare(
            'INSERT INTO diario_ocorrencias (obra_codigo, numero, tipo, descricao)
             VALUES (:obra_codigo, :numero, :tipo, :descricao)'
        );

        foreach ($diario->ocorrencias() as $ocorrencia) {
            $comando->execute([
                ':obra_codigo' => $diario->obraCodigo,
                ':numero' => $numero,
                ':tipo' => $ocorrencia->tipo->value,
                ':descricao' => $ocorrencia->descricao,
            ]);
        }
    }

    /**
     * Monta vários diários carregando os filhos em três consultas, e não em
     * três por diário. Listar um mês de obra são 30 diários: a diferença entre
     * 4 consultas e 91 aparece.
     *
     * @param  array<int, array<string, mixed>> $linhas
     * @return DiarioDeObra[]
     */
    private function montarVarios(array $linhas): array
    {
        if ($linhas === []) {
            return [];
        }

        $obraCodigo = (string) $linhas[0]['obra_codigo'];
        $numeros = array_map(static fn (array $linha): int => (int) $linha['numero'], $linhas);

        $efetivos = $this->carregarEfetivos($obraCodigo, $numeros);
        $atividades = $this->carregarAtividades($obraCodigo, $numeros);
        $ocorrencias = $this->carregarOcorrencias($obraCodigo, $numeros);

        $diarios = [];

        foreach ($linhas as $linha) {
            $numero = (int) $linha['numero'];

            $diario = DiarioDeObra::reconstituir(
                (string) $linha['obra_codigo'],
                $numero,
                new DateTimeImmutable((string) $linha['data']),
                ClimaDoDia::deColunas($linha),
                (string) $linha['responsavel'],
            );

            $diario->definirEfetivo(Efetivo::de($efetivos[$numero] ?? []));

            foreach ($atividades[$numero] ?? [] as $atividade) {
                $diario->registrarAtividade($atividade);
            }

            foreach ($ocorrencias[$numero] ?? [] as $ocorrencia) {
                $diario->registrarOcorrencia($ocorrencia);
            }

            $diarios[] = $diario;
        }

        return $diarios;
    }

    /**
     * @param  int[] $numeros
     * @return array<int, array<string, int>>
     */
    private function carregarEfetivos(string $obraCodigo, array $numeros): array
    {
        $linhas = $this->buscarFilhos(
            'SELECT numero, funcao, quantidade FROM diario_efetivo',
            $obraCodigo,
            $numeros,
        );

        $porDiario = [];

        foreach ($linhas as $linha) {
            $porDiario[(int) $linha['numero']][(string) $linha['funcao']] =
                (int) $linha['quantidade'];
        }

        return $porDiario;
    }

    /**
     * @param  int[] $numeros
     * @return array<int, AtividadeExecutada[]>
     */
    private function carregarAtividades(string $obraCodigo, array $numeros): array
    {
        $linhas = $this->buscarFilhos(
            'SELECT numero, servico_codigo, quantidade, observacao FROM diario_atividades',
            $obraCodigo,
            $numeros,
            'ORDER BY id',
        );

        $porDiario = [];

        foreach ($linhas as $linha) {
            $observacao = $linha['observacao'];

            $porDiario[(int) $linha['numero']][] = new AtividadeExecutada(
                (string) $linha['servico_codigo'],
                (float) $linha['quantidade'],
                $observacao === null ? null : (string) $observacao,
            );
        }

        return $porDiario;
    }

    /**
     * @param  int[] $numeros
     * @return array<int, Ocorrencia[]>
     */
    private function carregarOcorrencias(string $obraCodigo, array $numeros): array
    {
        $linhas = $this->buscarFilhos(
            'SELECT numero, tipo, descricao FROM diario_ocorrencias',
            $obraCodigo,
            $numeros,
            'ORDER BY id',
        );

        $porDiario = [];

        foreach ($linhas as $linha) {
            $porDiario[(int) $linha['numero']][] = new Ocorrencia(
                TipoDeOcorrencia::from((string) $linha['tipo']),
                (string) $linha['descricao'],
            );
        }

        return $porDiario;
    }

    /**
     * Monta o IN com um marcador por número. Os valores continuam viajando
     * como parâmetro; só a quantidade de marcadores é montada em texto.
     *
     * @param  int[] $numeros
     * @return array<int, array<string, mixed>>
     */
    private function buscarFilhos(
        string $select,
        string $obraCodigo,
        array $numeros,
        string $ordem = '',
    ): array {
        $marcadores = implode(', ', array_fill(0, count($numeros), '?'));

        $consulta = $this->conexao->prepare(
            "{$select} WHERE obra_codigo = ? AND numero IN ({$marcadores}) {$ordem}"
        );
        $consulta->execute([$obraCodigo, ...$numeros]);

        return $consulta->fetchAll();
    }

    /** Só abre transação se ainda não houver uma: o caso de uso pode já ter aberto. */
    private function emTransacao(callable $acao): mixed
    {
        if ($this->conexao->inTransaction()) {
            return $acao();
        }

        $this->conexao->beginTransaction();

        try {
            $resultado = $acao();
            $this->conexao->commit();

            return $resultado;
        } catch (Throwable $erro) {
            $this->conexao->rollBack();

            throw $erro;
        }
    }

    private static function normalizar(string $codigo): string
    {
        return strtoupper(trim($codigo));
    }
}
