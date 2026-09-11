<?php

declare(strict_types=1);

namespace GestaoObras\Dominio\Medicao;

use DateTimeImmutable;
use GestaoObras\Dominio\ExcecaoDeDominio;
use GestaoObras\Dominio\Regras;

/**
 * A medição de um período: quanto da obra pode ser faturado nesta competência.
 *
 * É o documento que fecha o ciclo do sistema. O diário registra o dia a dia, o
 * serviço acumula o avanço, e a medição recorta um período e diz quanto disso
 * vira fatura — com a memória de cálculo linha a linha.
 */
final class Medicao
{
    public readonly string $obraCodigo;
    public readonly DateTimeImmutable $inicio;
    public readonly DateTimeImmutable $fim;

    private int $numero = 0;
    private SituacaoDaMedicao $situacao = SituacaoDaMedicao::Aberta;

    /** @var ItemDeMedicao[] */
    private array $itens = [];

    public function __construct(
        string $obraCodigo,
        DateTimeImmutable $inicio,
        DateTimeImmutable $fim,
        ?DateTimeImmutable $hoje = null,
    ) {
        $this->obraCodigo = strtoupper(Regras::textoObrigatorio($obraCodigo, 'Código da obra', 20));

        $primeiroDia = $inicio->setTime(0, 0);
        $ultimoDia = $fim->setTime(0, 0);

        if ($ultimoDia < $primeiroDia) {
            throw new ExcecaoDeDominio('O fim do período não pode ser antes do início.');
        }

        $limite = ($hoje ?? new DateTimeImmutable('today'))->setTime(0, 0);

        if ($ultimoDia > $limite) {
            throw new ExcecaoDeDominio(sprintf(
                'Não é possível medir até %s: o período ainda não terminou.',
                $ultimoDia->format('d/m/Y'),
            ));
        }

        $this->inicio = $primeiroDia;
        $this->fim = $ultimoDia;
    }

    public static function reconstituir(
        string $obraCodigo,
        int $numero,
        DateTimeImmutable $inicio,
        DateTimeImmutable $fim,
        SituacaoDaMedicao $situacao,
    ): self {
        $medicao = new self($obraCodigo, $inicio, $fim, $fim);
        $medicao->definirNumero($numero);
        $medicao->situacao = $situacao;

        return $medicao;
    }

    public function numero(): int
    {
        return $this->numero;
    }

    public function definirNumero(int $numero): void
    {
        $this->numero = Regras::inteiroPositivo($numero, 'Número da medição');
    }

    public function situacao(): SituacaoDaMedicao
    {
        return $this->situacao;
    }

    public function adicionarItem(ItemDeMedicao $item): void
    {
        $this->exigirAberta('incluir item');

        $this->itens[] = $item;
    }

    /** @return ItemDeMedicao[] */
    public function itens(): array
    {
        return $this->itens;
    }

    /** @return ItemDeMedicao[] só os que movimentaram no período */
    public function itensComMovimento(): array
    {
        return array_values(array_filter(
            $this->itens,
            static fn (ItemDeMedicao $item): bool => $item->teveMovimento(),
        ));
    }

    /** O que esta medição fatura. */
    public function valorNoPeriodo(): float
    {
        return round($this->somar(
            static fn (ItemDeMedicao $item): float => $item->valorNoPeriodo()
        ), 2);
    }

    /** O que a obra já mediu desde o começo, incluindo este período. */
    public function valorAcumulado(): float
    {
        return round($this->somar(
            static fn (ItemDeMedicao $item): float => $item->valorAcumulado()
        ), 2);
    }

    public function valorPrevisto(): float
    {
        return round($this->somar(
            static fn (ItemDeMedicao $item): float => $item->valorPrevisto()
        ), 2);
    }

    /** Avanço acumulado da obra até o fim deste período, ponderado por valor. */
    public function percentualAcumulado(): float
    {
        $previsto = $this->valorPrevisto();

        return $previsto <= 0.0 ? 0.0 : round($this->valorAcumulado() / $previsto * 100, 2);
    }

    public function diasDoPeriodo(): int
    {
        return (int) $this->inicio->diff($this->fim)->days + 1;
    }

    /**
     * Fecha a medição. A partir daqui ela é documento: corrigir passa por uma
     * medição de acerto na competência seguinte, não por editar esta.
     */
    public function fechar(): void
    {
        $this->exigirAberta('fechar');

        if ($this->itensComMovimento() === []) {
            throw new ExcecaoDeDominio(
                'Não há o que medir neste período: nenhum serviço teve execução. '
                . 'Confira se os diários do período foram registrados.'
            );
        }

        $this->situacao = SituacaoDaMedicao::Fechada;
    }

    public function estaFechada(): bool
    {
        return $this->situacao === SituacaoDaMedicao::Fechada;
    }

    private function exigirAberta(string $acao): void
    {
        if ($this->situacao->permiteAlteracao()) {
            return;
        }

        throw new ExcecaoDeDominio(sprintf(
            'A medição nº %d está fechada e não é possível %s.',
            $this->numero,
            $acao,
        ));
    }

    private function somar(callable $valor): float
    {
        return array_sum(array_map($valor, $this->itens));
    }
}
