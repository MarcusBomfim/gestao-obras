<?php

declare(strict_types=1);

namespace GestaoObras\Aplicacao;

use DateInterval;
use DateTimeImmutable;

/**
 * A curva de avanço da obra: quanto do valor previsto já foi executado ao
 * longo do tempo.
 *
 * É a curva S do planejamento, desenhada com o que aconteceu de verdade em vez
 * do que estava previsto. Comparar as duas é o que mostra se a obra está
 * andando no ritmo contratado — e o desvio aparece bem antes do prazo estourar.
 */
final class CurvaDeAvanco
{
    /**
     * @param array<string, float> $valorPorDia     data ISO => valor executado no dia
     * @param ?DateTimeImmutable   $terminoPrevisto fim do prazo contratual; quando a
     *        curva é desenhada só até hoje, é ele que dá o ritmo esperado
     */
    public function __construct(
        private readonly array $valorPorDia,
        public readonly float $valorPrevisto,
        public readonly DateTimeImmutable $inicio,
        public readonly DateTimeImmutable $fim,
        private readonly ?DateTimeImmutable $terminoPrevisto = null,
    ) {
    }

    /**
     * Pontos acumulados. Só os dias com movimento entram, mais as duas pontas:
     * uma curva de obra de um ano com um ponto por dia seria ilegível e não
     * diria nada a mais.
     *
     * @return array<int, array{data: string, acumulado: float, percentual: float}>
     */
    public function pontos(): array
    {
        $pontos = [[
            'data' => $this->inicio->format('Y-m-d'),
            'acumulado' => 0.0,
            'percentual' => 0.0,
        ]];

        $acumulado = 0.0;

        foreach ($this->valorPorDia as $dia => $valor) {
            $acumulado += $valor;

            $pontos[] = [
                'data' => $dia,
                'acumulado' => round($acumulado, 2),
                'percentual' => $this->percentual($acumulado),
            ];
        }

        $ultimo = $pontos[count($pontos) - 1];

        if ($ultimo['data'] !== $this->fim->format('Y-m-d')) {
            $pontos[] = [
                'data' => $this->fim->format('Y-m-d'),
                'acumulado' => round($acumulado, 2),
                'percentual' => $this->percentual($acumulado),
            ];
        }

        return $pontos;
    }

    public function percentualFinal(): float
    {
        $pontos = $this->pontos();

        return $pontos[count($pontos) - 1]['percentual'];
    }

    public function valorExecutado(): float
    {
        return round(array_sum($this->valorPorDia), 2);
    }

    public function temMovimento(): bool
    {
        return $this->valorPorDia !== [];
    }

    /**
     * Avanço linear esperado até hoje, em percentual.
     *
     * É a referência mais ingênua possível — supõe ritmo constante do primeiro
     * ao último dia. Serve como linha de comparação enquanto o sistema não tem
     * cronograma físico-financeiro de verdade, e o README diz isso.
     *
     * O "último dia" é o fim do prazo, não o fim do desenho: uma obra na
     * metade do contrato, desenhada até hoje, tem 50 % esperados — não 100 %.
     */
    public function avancoLinearEsperado(DateTimeImmutable $referencia): float
    {
        $ultimoDia = $this->terminoPrevisto ?? $this->fim;
        $total = (int) $this->inicio->diff($ultimoDia)->days + 1;

        if ($total <= 0) {
            return 0.0;
        }

        $decorridos = (int) $this->inicio->diff($referencia->setTime(0, 0))->days + 1;

        return round(max(0.0, min(100.0, $decorridos / $total * 100)), 2);
    }

    /**
     * Pontos "x,y" para um <polyline> de SVG, já no sistema de coordenadas da
     * imagem: x pelo dia, y pelo percentual, com o zero embaixo.
     */
    public function polilinha(int $largura, int $altura, int $margem = 4): string
    {
        $pontos = $this->pontos();
        $totalDeDias = max(1, (int) $this->inicio->diff($this->fim)->days);

        $util = static fn (int $tamanho): int => max(1, $tamanho - 2 * $margem);

        $coordenadas = array_map(
            function (array $ponto) use ($largura, $altura, $margem, $totalDeDias, $util): string {
                $dia = (int) $this->inicio->diff(new DateTimeImmutable($ponto['data']))->days;

                $x = $margem + ($dia / $totalDeDias) * $util($largura);
                $y = $altura - $margem - ($ponto['percentual'] / 100) * $util($altura);

                return round($x, 1) . ',' . round($y, 1);
            },
            $pontos,
        );

        return implode(' ', $coordenadas);
    }

    /** @return array<int, string> rótulos de data para o eixo, no máximo cinco */
    public function marcasDoEixo(): array
    {
        $totalDeDias = (int) $this->inicio->diff($this->fim)->days;
        $passo = max(1, (int) ceil($totalDeDias / 4));

        $marcas = [];
        $cursor = $this->inicio;

        while ($cursor <= $this->fim && count($marcas) < 5) {
            $marcas[] = $cursor->format('d/m');
            $cursor = $cursor->add(new DateInterval("P{$passo}D"));
        }

        return $marcas;
    }

    private function percentual(float $acumulado): float
    {
        return $this->valorPrevisto <= 0.0
            ? 0.0
            : round(min(100.0, $acumulado / $this->valorPrevisto * 100), 2);
    }
}
