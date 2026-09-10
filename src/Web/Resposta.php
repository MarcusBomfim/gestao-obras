<?php

declare(strict_types=1);

namespace GestaoObras\Web;

/**
 * A resposta HTTP como valor.
 *
 * Os controladores devolvem uma Resposta em vez de chamar header() e echo
 * direto. Assim dá para testá-los sem servidor: basta olhar o status e o corpo.
 */
final class Resposta
{
    /** @param array<string, string> $cabecalhos */
    private function __construct(
        public readonly int $status,
        public readonly string $corpo,
        public readonly array $cabecalhos = [],
    ) {
    }

    public static function html(string $corpo, int $status = 200): self
    {
        return new self($status, $corpo, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    public static function redirecionar(string $destino, int $status = 303): self
    {
        /*
         * 303 e não 302: depois de um POST bem-sucedido, o navegador precisa
         * fazer GET no destino. É o padrão POST-Redirect-GET, que impede o
         * "reenviar formulário?" ao atualizar a página.
         */
        return new self($status, '', ['Location' => $destino]);
    }

    public static function naoEncontrado(string $corpo): self
    {
        return new self(404, $corpo, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /** @param string[] $permitidos verbos que a rota aceita, para o cabeçalho Allow */
    public static function metodoNaoPermitido(array $permitidos): self
    {
        return new self(405, 'Método não permitido.', [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Allow' => implode(', ', $permitidos),
        ]);
    }

    /** Envia a resposta de verdade. Só o ponto de entrada chama isto. */
    public function enviar(): void
    {
        http_response_code($this->status);

        foreach ($this->cabecalhos as $nome => $valor) {
            header("{$nome}: {$valor}");
        }

        echo $this->corpo;
    }
}
