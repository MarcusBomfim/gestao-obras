<?php

declare(strict_types=1);

namespace GestaoObras\Dominio\Diario;

use GestaoObras\Dominio\ExcecaoDeDominio;

/** Quantas pessoas de cada função estavam no canteiro naquele dia. */
final class Efetivo
{
    /** @var array<string, int> função => quantidade */
    private array $porFuncao = [];

    /** @param array<string, int> $mapa valores do enum FuncaoDeMaoDeObra */
    public static function de(array $mapa): self
    {
        $efetivo = new self();

        foreach ($mapa as $funcao => $quantidade) {
            $efetivo->definir(FuncaoDeMaoDeObra::from((string) $funcao), (int) $quantidade);
        }

        return $efetivo;
    }

    /**
     * Define quantas pessoas daquela função estavam presentes. Define, e não
     * soma: o efetivo do dia é uma contagem, não um acumulado. Zero remove a
     * função do apontamento.
     */
    public function definir(FuncaoDeMaoDeObra $funcao, int $quantidade): void
    {
        if ($quantidade < 0) {
            throw new ExcecaoDeDominio(
                "O efetivo de {$funcao->rotulo()} não pode ser negativo."
            );
        }

        if ($quantidade === 0) {
            unset($this->porFuncao[$funcao->value]);

            return;
        }

        $this->porFuncao[$funcao->value] = $quantidade;
    }

    public function quantidade(FuncaoDeMaoDeObra $funcao): int
    {
        return $this->porFuncao[$funcao->value] ?? 0;
    }

    public function total(): int
    {
        return array_sum($this->porFuncao);
    }

    /** Mão de obra de produção: exclui engenheiro, encarregado e segurança. */
    public function totalDireto(): int
    {
        return $this->somar(static fn (FuncaoDeMaoDeObra $funcao): bool => !$funcao->ehIndireta());
    }

    public function totalIndireto(): int
    {
        return $this->somar(static fn (FuncaoDeMaoDeObra $funcao): bool => $funcao->ehIndireta());
    }

    public function estaVazio(): bool
    {
        return $this->porFuncao === [];
    }

    /** @return array<string, int> função => quantidade, só o que foi apontado */
    public function paraArray(): array
    {
        ksort($this->porFuncao);

        return $this->porFuncao;
    }

    /** @return array<string, int> rótulo legível => quantidade */
    public function linhas(): array
    {
        $linhas = [];

        foreach ($this->paraArray() as $funcao => $quantidade) {
            $linhas[FuncaoDeMaoDeObra::from($funcao)->rotulo()] = $quantidade;
        }

        return $linhas;
    }

    private function somar(callable $filtro): int
    {
        $total = 0;

        foreach ($this->porFuncao as $funcao => $quantidade) {
            if ($filtro(FuncaoDeMaoDeObra::from($funcao))) {
                $total += $quantidade;
            }
        }

        return $total;
    }
}
