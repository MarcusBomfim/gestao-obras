<?php

declare(strict_types=1);

/*
 * Funções de apoio aos templates.
 *
 * Ficam no namespace global de propósito: os templates são PHP simples, sem
 * declaração de namespace, e chamariam o namespace global de qualquer jeito.
 * Declarar dentro de GestaoObras\Web faria "e($x)" no template procurar uma
 * função global que não existe — erro fatal, e só em tempo de execução.
 *
 * O arquivo é carregado pelo autoload, então basta incluir src/autoload.php.
 */

if (!function_exists('e')) {
    /**
     * Escapa para HTML.
     *
     * O nome é curto porque aparece em toda interpolação de template, e
     * qualquer atrito aqui vira desculpa para esquecer — que é exatamente
     * como nasce um XSS.
     */
    function e(mixed $valor): string
    {
        return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('numeroBr')) {
    /** Número no padrão brasileiro: 1.234,56 */
    function numeroBr(float $valor, int $casas = 2): string
    {
        return number_format($valor, $casas, ',', '.');
    }
}

if (!function_exists('reais')) {
    function reais(float $valor): string
    {
        return 'R$ ' . numeroBr($valor);
    }
}

if (!function_exists('dataBr')) {
    function dataBr(DateTimeInterface $data): string
    {
        return $data->format('d/m/Y');
    }
}

if (!function_exists('barraDeAvanco')) {
    /** Largura da barra de progresso, limitada a 100 mesmo com arredondamento. */
    function barraDeAvanco(float $percentual): string
    {
        return numeroBr(max(0.0, min(100.0, $percentual)), 1);
    }
}
