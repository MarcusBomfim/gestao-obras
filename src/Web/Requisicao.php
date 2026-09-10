<?php

declare(strict_types=1);

namespace GestaoObras\Web;

/**
 * A requisição HTTP, com as superglobais isoladas em um lugar só.
 *
 * O resto do sistema nunca toca em $_GET ou $_POST: recebe uma Requisicao. Nos
 * testes, dá para montar uma à mão sem simular servidor nenhum.
 */
final class Requisicao
{
    /**
     * @param array<string, string>            $consulta  parâmetros da query string
     * @param array<string, string|array>      $corpo     campos enviados por POST
     * @param array<string, string>            $parametros trechos capturados da rota
     */
    public function __construct(
        public readonly string $metodo,
        public readonly string $caminho,
        public readonly array $consulta = [],
        public readonly array $corpo = [],
        public readonly array $parametros = [],
    ) {
    }

    public static function dasSuperglobais(): self
    {
        $metodo = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

        // REQUEST_URI traz a query junto; a rota olha só o caminho.
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $caminho = parse_url($uri, PHP_URL_PATH);

        return new self(
            $metodo,
            is_string($caminho) ? $caminho : '/',
            array_map(strval(...), $_GET),
            $_POST,
        );
    }

    /** Cópia da requisição com os parâmetros capturados pela rota. */
    public function comParametros(array $parametros): self
    {
        return new self($this->metodo, $this->caminho, $this->consulta, $this->corpo, $parametros);
    }

    public function parametro(string $nome, string $padrao = ''): string
    {
        return $this->parametros[$nome] ?? $padrao;
    }

    public function campo(string $nome, string $padrao = ''): string
    {
        $valor = $this->corpo[$nome] ?? $padrao;

        return is_string($valor) ? trim($valor) : $padrao;
    }

    public function campoInteiro(string $nome, int $padrao = 0): int
    {
        $valor = $this->campo($nome);

        return $valor === '' ? $padrao : (int) $valor;
    }

    public function campoDecimal(string $nome, float $padrao = 0.0): float
    {
        // Formulário em português manda "12,50"; o PHP quer "12.50".
        $valor = str_replace(',', '.', $this->campo($nome));

        return $valor === '' ? $padrao : (float) $valor;
    }

    /** @return array<int, array<string, string>> linhas de um campo repetido */
    public function linhas(string $nome): array
    {
        $valor = $this->corpo[$nome] ?? [];

        if (!is_array($valor)) {
            return [];
        }

        $linhas = [];

        foreach ($valor as $linha) {
            if (is_array($linha)) {
                $linhas[] = array_map(
                    static fn ($campo): string => is_string($campo) ? trim($campo) : '',
                    $linha,
                );
            }
        }

        return $linhas;
    }

    public function consulta(string $nome, string $padrao = ''): string
    {
        return $this->consulta[$nome] ?? $padrao;
    }
}
