<?php

declare(strict_types=1);

namespace GestaoObras\Infraestrutura\Repositorio;

use DateTimeImmutable;
use GestaoObras\Dominio\Medicao\ItemDeMedicao;
use GestaoObras\Dominio\Medicao\Medicao;
use GestaoObras\Dominio\Medicao\RepositorioDeMedicoes;
use GestaoObras\Dominio\Medicao\SituacaoDaMedicao;
use GestaoObras\Dominio\Servico\Unidade;
use PDO;
use Throwable;

final class RepositorioDeMedicoesEmSqlite implements RepositorioDeMedicoes
{
    private const COLUNAS = 'obra_codigo, numero, inicio, fim, situacao';

    public function __construct(private readonly PDO $conexao)
    {
    }

    public function salvar(Medicao $medicao): int
    {
        return $this->emTransacao(function () use ($medicao): int {
            $numero = $medicao->numero() > 0
                ? $medicao->numero()
                : $this->proximoNumero($medicao->obraCodigo);

            $comando = $this->conexao->prepare(
                'INSERT INTO medicoes (' . self::COLUNAS . ')
                 VALUES (:obra_codigo, :numero, :inicio, :fim, :situacao)'
            );

            $comando->execute([
                ':obra_codigo' => $medicao->obraCodigo,
                ':numero' => $numero,
                ':inicio' => $medicao->inicio->format('Y-m-d'),
                ':fim' => $medicao->fim->format('Y-m-d'),
                ':situacao' => $medicao->situacao()->value,
            ]);

            $this->gravarItens($medicao, $numero);

            return $numero;
        });
    }

    public function porNumero(string $obraCodigo, int $numero): ?Medicao
    {
        $consulta = $this->conexao->prepare(
            'SELECT ' . self::COLUNAS . ' FROM medicoes
             WHERE obra_codigo = :obra_codigo AND numero = :numero'
        );
        $consulta->execute([
            ':obra_codigo' => self::normalizar($obraCodigo),
            ':numero' => $numero,
        ]);

        $linha = $consulta->fetch();

        return $linha === false ? null : $this->montar($linha);
    }

    public function daObra(string $obraCodigo): array
    {
        $consulta = $this->conexao->prepare(
            'SELECT ' . self::COLUNAS . ' FROM medicoes
             WHERE obra_codigo = :obra_codigo ORDER BY numero DESC'
        );
        $consulta->execute([':obra_codigo' => self::normalizar($obraCodigo)]);

        return array_map($this->montar(...), $consulta->fetchAll());
    }

    public function ultima(string $obraCodigo): ?Medicao
    {
        $consulta = $this->conexao->prepare(
            'SELECT ' . self::COLUNAS . ' FROM medicoes
             WHERE obra_codigo = :obra_codigo ORDER BY numero DESC LIMIT 1'
        );
        $consulta->execute([':obra_codigo' => self::normalizar($obraCodigo)]);

        $linha = $consulta->fetch();

        return $linha === false ? null : $this->montar($linha);
    }

    public function fechar(string $obraCodigo, int $numero): void
    {
        $comando = $this->conexao->prepare(
            "UPDATE medicoes
             SET situacao = 'fechada', fechado_em = datetime('now')
             WHERE obra_codigo = :obra_codigo AND numero = :numero"
        );
        $comando->execute([
            ':obra_codigo' => self::normalizar($obraCodigo),
            ':numero' => $numero,
        ]);
    }

    public function remover(string $obraCodigo, int $numero): void
    {
        // Os itens saem em cascata.
        $comando = $this->conexao->prepare(
            'DELETE FROM medicoes WHERE obra_codigo = :obra_codigo AND numero = :numero'
        );
        $comando->execute([
            ':obra_codigo' => self::normalizar($obraCodigo),
            ':numero' => $numero,
        ]);
    }

    private function proximoNumero(string $obraCodigo): int
    {
        $consulta = $this->conexao->prepare(
            'SELECT COALESCE(MAX(numero), 0) + 1 FROM medicoes WHERE obra_codigo = :obra_codigo'
        );
        $consulta->execute([':obra_codigo' => $obraCodigo]);

        return (int) $consulta->fetchColumn();
    }

    private function gravarItens(Medicao $medicao, int $numero): void
    {
        $comando = $this->conexao->prepare(
            'INSERT INTO medicao_itens
                (obra_codigo, numero, servico_codigo, descricao, unidade,
                 quantidade_prevista, preco_unitario, acumulado_anterior, no_periodo)
             VALUES (:obra_codigo, :numero, :servico_codigo, :descricao, :unidade,
                     :quantidade_prevista, :preco_unitario, :acumulado_anterior, :no_periodo)'
        );

        foreach ($medicao->itens() as $item) {
            $comando->execute([
                ':obra_codigo' => $medicao->obraCodigo,
                ':numero' => $numero,
                ':servico_codigo' => $item->servicoCodigo,
                ':descricao' => $item->descricao,
                ':unidade' => $item->unidade->value,
                ':quantidade_prevista' => $item->quantidadePrevista,
                ':preco_unitario' => $item->precoUnitario,
                ':acumulado_anterior' => $item->acumuladoAnterior,
                ':no_periodo' => $item->noPeriodo,
            ]);
        }
    }

    /** @param array<string, mixed> $linha */
    private function montar(array $linha): Medicao
    {
        $numero = (int) $linha['numero'];
        $obraCodigo = (string) $linha['obra_codigo'];
        $situacao = SituacaoDaMedicao::from((string) $linha['situacao']);

        /*
         * Reconstitui aberta e só fecha no fim: uma medição fechada recusa
         * receber itens, e é justamente o que precisamos fazer para montá-la.
         */
        $medicao = Medicao::reconstituir(
            $obraCodigo,
            $numero,
            new DateTimeImmutable((string) $linha['inicio']),
            new DateTimeImmutable((string) $linha['fim']),
            SituacaoDaMedicao::Aberta,
        );

        foreach ($this->carregarItens($obraCodigo, $numero) as $item) {
            $medicao->adicionarItem($item);
        }

        if ($situacao === SituacaoDaMedicao::Fechada) {
            $medicao->fechar();
        }

        return $medicao;
    }

    /** @return ItemDeMedicao[] */
    private function carregarItens(string $obraCodigo, int $numero): array
    {
        $consulta = $this->conexao->prepare(
            'SELECT servico_codigo, descricao, unidade, quantidade_prevista,
                    preco_unitario, acumulado_anterior, no_periodo
             FROM medicao_itens
             WHERE obra_codigo = :obra_codigo AND numero = :numero
             ORDER BY servico_codigo'
        );
        $consulta->execute([':obra_codigo' => $obraCodigo, ':numero' => $numero]);

        return array_map(
            static fn (array $linha): ItemDeMedicao => new ItemDeMedicao(
                (string) $linha['servico_codigo'],
                (string) $linha['descricao'],
                Unidade::from((string) $linha['unidade']),
                (float) $linha['quantidade_prevista'],
                (float) $linha['preco_unitario'],
                (float) $linha['acumulado_anterior'],
                (float) $linha['no_periodo'],
            ),
            $consulta->fetchAll(),
        );
    }

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
