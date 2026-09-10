<?php

declare(strict_types=1);

namespace GestaoObras\Aplicacao;

use GestaoObras\Dominio\Obra\Obra;
use GestaoObras\Dominio\Servico\Servico;

/**
 * Visão consolidada de uma obra: o quanto dela já saiu do papel.
 *
 * O avanço físico é ponderado pelo valor de cada serviço, não pela média
 * simples dos percentuais. Um serviço de R$ 84 mil concluído e outro de R$ 500
 * parado não dão "50% da obra" — e a média simples diria exatamente isso.
 */
final class ResumoDaObra
{
    /** @param Servico[] $servicos */
    public function __construct(
        public readonly Obra $obra,
        public readonly array $servicos,
    ) {
    }

    public function valorPrevisto(): float
    {
        return round($this->somar(static fn (Servico $s): float => $s->valorPrevisto()), 2);
    }

    public function valorExecutado(): float
    {
        return round($this->somar(static fn (Servico $s): float => $s->valorExecutado()), 2);
    }

    public function saldoAExecutar(): float
    {
        return round($this->valorPrevisto() - $this->valorExecutado(), 2);
    }

    /** Avanço físico de 0 a 100, ponderado pelo valor de cada serviço. */
    public function percentualFisico(): float
    {
        $previsto = $this->valorPrevisto();

        if ($previsto <= 0.0) {
            return 0.0;
        }

        return round($this->valorExecutado() / $previsto * 100, 2);
    }

    public function totalDeServicos(): int
    {
        return count($this->servicos);
    }

    public function servicosConcluidos(): int
    {
        return count(array_filter(
            $this->servicos,
            static fn (Servico $servico): bool => $servico->estaConcluido(),
        ));
    }

    public function servicosNaoIniciados(): int
    {
        return count(array_filter(
            $this->servicos,
            static fn (Servico $servico): bool => $servico->quantidadeExecutada() <= 0.0,
        ));
    }

    public function servicosEmAndamento(): int
    {
        return $this->totalDeServicos()
            - $this->servicosConcluidos()
            - $this->servicosNaoIniciados();
    }

    private function somar(callable $valor): float
    {
        return array_sum(array_map($valor, $this->servicos));
    }
}
