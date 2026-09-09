<?php

declare(strict_types=1);

namespace GestaoObras\Dominio\Servico;

/**
 * Unidades de medida usadas em orçamento de obra.
 *
 * A distinção que importa: algumas aceitam fração e outras não. Dá para
 * executar 12,5 m² de alvenaria, mas não 2,5 portas.
 */
enum Unidade: string
{
    case MetroQuadrado = 'm2';
    case MetroCubico = 'm3';
    case MetroLinear = 'm';
    case Quilograma = 'kg';
    case Tonelada = 't';
    case Unidade = 'un';
    case Verba = 'vb';
    case Hora = 'h';

    public function simbolo(): string
    {
        return match ($this) {
            self::MetroQuadrado => 'm²',
            self::MetroCubico => 'm³',
            self::MetroLinear => 'm',
            self::Quilograma => 'kg',
            self::Tonelada => 't',
            self::Unidade => 'un',
            self::Verba => 'vb',
            self::Hora => 'h',
        };
    }

    public function descricao(): string
    {
        return match ($this) {
            self::MetroQuadrado => 'Metro quadrado',
            self::MetroCubico => 'Metro cúbico',
            self::MetroLinear => 'Metro linear',
            self::Quilograma => 'Quilograma',
            self::Tonelada => 'Tonelada',
            self::Unidade => 'Unidade',
            self::Verba => 'Verba',
            self::Hora => 'Hora',
        };
    }

    public function aceitaFracao(): bool
    {
        return match ($this) {
            self::Unidade, self::Verba => false,
            default => true,
        };
    }

    /** Casas decimais usadas ao exibir a quantidade. */
    public function casasDecimais(): int
    {
        return $this->aceitaFracao() ? 2 : 0;
    }

    public function formatar(float $quantidade): string
    {
        return number_format($quantidade, $this->casasDecimais(), ',', '.') . ' ' . $this->simbolo();
    }
}
