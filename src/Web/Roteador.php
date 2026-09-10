<?php

declare(strict_types=1);

namespace GestaoObras\Web;

/**
 * Roteador mínimo: casa verbo e caminho, captura parâmetros e chama a ação.
 *
 * Distingue 404 de 405 de propósito. "O caminho não existe" e "o caminho
 * existe mas não aceita esse verbo" são coisas diferentes, e quem consome a
 * aplicação precisa saber qual das duas aconteceu.
 */
final class Roteador
{
    /** @var array<int, array{metodo: string, regex: string, acao: callable}> */
    private array $rotas = [];

    public function get(string $caminho, callable $acao): void
    {
        $this->registrar('GET', $caminho, $acao);
    }

    public function post(string $caminho, callable $acao): void
    {
        $this->registrar('POST', $caminho, $acao);
    }

    public function despachar(Requisicao $requisicao): Resposta
    {
        $caminho = self::normalizar($requisicao->caminho);
        $verbosDoCaminho = [];

        foreach ($this->rotas as $rota) {
            if (preg_match($rota['regex'], $caminho, $capturas) !== 1) {
                continue;
            }

            if ($rota['metodo'] !== $requisicao->metodo) {
                $verbosDoCaminho[] = $rota['metodo'];
                continue;
            }

            // Só as capturas nomeadas interessam; preg_match devolve as duas formas.
            $parametros = array_filter(
                $capturas,
                static fn (string|int $chave): bool => is_string($chave),
                ARRAY_FILTER_USE_KEY,
            );

            return ($rota['acao'])($requisicao->comParametros(array_map(
                static fn (string $valor): string => urldecode($valor),
                $parametros,
            )));
        }

        if ($verbosDoCaminho !== []) {
            return Resposta::metodoNaoPermitido(array_values(array_unique($verbosDoCaminho)));
        }

        return Resposta::naoEncontrado(
            '<!doctype html><meta charset="utf-8"><title>Não encontrado</title>'
            . '<p>Página não encontrada. <a href="/obras">Voltar para as obras</a>.</p>'
        );
    }

    private function registrar(string $metodo, string $caminho, callable $acao): void
    {
        $this->rotas[] = [
            'metodo' => $metodo,
            'regex' => self::compilar(self::normalizar($caminho)),
            'acao' => $acao,
        ];
    }

    /**
     * Transforma "/obras/{codigo}/diarios" em uma expressão com captura
     * nomeada. O parâmetro não atravessa barra: {codigo} casa "OBR-1", não
     * "OBR-1/diarios".
     */
    private static function compilar(string $caminho): string
    {
        $escapado = preg_quote($caminho, '#');

        $comParametros = preg_replace(
            '#\\\\\{(\w+)\\\\\}#',
            '(?P<$1>[^/]+)',
            $escapado,
        );

        return '#^' . $comParametros . '$#';
    }

    /** Tira a barra final para "/obras" e "/obras/" serem a mesma rota. */
    private static function normalizar(string $caminho): string
    {
        $limpo = rtrim($caminho, '/');

        return $limpo === '' ? '/' : $limpo;
    }
}
