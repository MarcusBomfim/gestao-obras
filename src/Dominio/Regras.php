<?php

declare(strict_types=1);

namespace GestaoObras\Dominio;

/**
 * Validações que se repetem em várias entidades. Cada método devolve o valor
 * já normalizado, para poder ser usado direto na atribuição.
 */
final class Regras
{
    /**
     * Comparações entre float precisam de folga: 0.1 + 0.2 não é exatamente
     * 0.3 em ponto flutuante. Uma milésima parte da unidade basta para
     * quantidades de obra, medidas em m², m³ ou kg.
     */
    public const TOLERANCIA = 0.001;

    private function __construct()
    {
    }

    public static function textoObrigatorio(string $valor, string $campo, int $tamanhoMaximo): string
    {
        $limpo = trim($valor);

        if ($limpo === '') {
            throw new ExcecaoDeDominio("{$campo} é obrigatório.");
        }

        if (mb_strlen($limpo) > $tamanhoMaximo) {
            throw new ExcecaoDeDominio(
                "{$campo} pode ter no máximo {$tamanhoMaximo} caracteres."
            );
        }

        return $limpo;
    }

    public static function numeroPositivo(float $valor, string $campo): float
    {
        if ($valor <= 0) {
            throw new ExcecaoDeDominio("{$campo} precisa ser maior que zero.");
        }

        return $valor;
    }

    public static function inteiroPositivo(int $valor, string $campo): int
    {
        if ($valor <= 0) {
            throw new ExcecaoDeDominio("{$campo} precisa ser maior que zero.");
        }

        return $valor;
    }

    public static function naoNegativo(float $valor, string $campo): float
    {
        if ($valor < 0) {
            throw new ExcecaoDeDominio("{$campo} não pode ser negativo.");
        }

        return $valor;
    }

    /** Diz se $a é maior que $b levando a tolerância em conta. */
    public static function maiorQue(float $a, float $b): bool
    {
        return $a - $b > self::TOLERANCIA;
    }

    /** Diz se o número é inteiro dentro da tolerância (2.0000001 conta como 2). */
    public static function ehInteiro(float $valor): bool
    {
        return abs($valor - round($valor)) <= self::TOLERANCIA;
    }
}
