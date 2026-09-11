<?php

declare(strict_types=1);

namespace GestaoObras\Dominio\Medicao;

use GestaoObras\Dominio\ExcecaoDeDominio;
use GestaoObras\Dominio\Regras;
use GestaoObras\Dominio\Servico\Unidade;

/**
 * Uma linha da memória de cálculo: o quanto de um serviço entra nesta medição.
 *
 * Guarda os três números que o cliente confere ao receber a fatura — o que já
 * havia sido medido antes, o que entra agora e o total acumulado. Sem os três,
 * a conversa vira "confia em mim".
 */
final class ItemDeMedicao
{
    public readonly string $servicoCodigo;
    public readonly string $descricao;
    public readonly Unidade $unidade;
    public readonly float $quantidadePrevista;
    public readonly float $precoUnitario;
    public readonly float $acumuladoAnterior;
    public readonly float $noPeriodo;

    public function __construct(
        string $servicoCodigo,
        string $descricao,
        Unidade $unidade,
        float $quantidadePrevista,
        float $precoUnitario,
        float $acumuladoAnterior,
        float $noPeriodo,
    ) {
        $this->servicoCodigo = strtoupper(
            Regras::textoObrigatorio($servicoCodigo, 'Código do serviço', 20)
        );
        $this->descricao = Regras::textoObrigatorio($descricao, 'Descrição do serviço', 200);
        $this->unidade = $unidade;
        $this->quantidadePrevista = Regras::numeroPositivo($quantidadePrevista, 'Quantidade prevista');
        $this->precoUnitario = Regras::numeroPositivo($precoUnitario, 'Preço unitário');
        $this->acumuladoAnterior = Regras::naoNegativo($acumuladoAnterior, 'Acumulado anterior');
        $this->noPeriodo = Regras::naoNegativo($noPeriodo, 'Quantidade no período');

        if (Regras::maiorQue($this->acumulado(), $quantidadePrevista)) {
            throw new ExcecaoDeDominio(sprintf(
                'O serviço %s acumularia %s, acima do previsto de %s.',
                $this->servicoCodigo,
                $unidade->formatar($this->acumulado()),
                $unidade->formatar($quantidadePrevista),
            ));
        }
    }

    public function acumulado(): float
    {
        return $this->acumuladoAnterior + $this->noPeriodo;
    }

    public function saldo(): float
    {
        return $this->quantidadePrevista - $this->acumulado();
    }

    public function valorNoPeriodo(): float
    {
        return round($this->noPeriodo * $this->precoUnitario, 2);
    }

    public function valorAcumulado(): float
    {
        return round($this->acumulado() * $this->precoUnitario, 2);
    }

    public function valorPrevisto(): float
    {
        return round($this->quantidadePrevista * $this->precoUnitario, 2);
    }

    public function percentualAcumulado(): float
    {
        return round($this->acumulado() / $this->quantidadePrevista * 100, 2);
    }

    /** Item sem movimento no período: aparece na memória, mas não fatura. */
    public function teveMovimento(): bool
    {
        return $this->noPeriodo > 0.0;
    }
}
