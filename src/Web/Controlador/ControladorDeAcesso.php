<?php

declare(strict_types=1);

namespace GestaoObras\Web\Controlador;

use GestaoObras\Aplicacao\Autenticador;
use GestaoObras\Web\Requisicao;
use GestaoObras\Web\Resposta;
use GestaoObras\Web\Sessao;
use GestaoObras\Web\Visao;

final class ControladorDeAcesso
{
    public function __construct(
        private readonly Autenticador $autenticador,
        private readonly Visao $visao,
        private readonly Sessao $sessao,
    ) {
    }

    public function formulario(Requisicao $requisicao): Resposta
    {
        return Resposta::html($this->visao->renderizar('acesso.entrar', [
            'voltar' => self::destinoSeguro($requisicao->consulta('voltar')),
            'token' => $this->sessao->token(),
            'erro' => $this->sessao->tirarErro(),
            'mensagem' => $this->sessao->tirarMensagem(),
        ], 'Entrar'));
    }

    public function entrar(Requisicao $requisicao): Resposta
    {
        $voltar = self::destinoSeguro($requisicao->campo('voltar'));

        if (!$this->sessao->tokenValido($requisicao->campo('token'))) {
            $this->sessao->guardarErro('A sessão expirou. Tente de novo.');

            return Resposta::redirecionar('/entrar');
        }

        $usuario = $this->autenticador->autenticar(
            $requisicao->campo('email'),
            $requisicao->campo('senha'),
        );

        if ($usuario === null) {
            // Uma mensagem só, para não dizer se foi o e-mail ou a senha.
            $this->sessao->guardarErro('E-mail ou senha incorretos.');

            return Resposta::redirecionar('/entrar');
        }

        $this->sessao->entrar($usuario->email);
        $this->sessao->guardarMensagem("Bem-vindo, {$usuario->nome}.");

        return Resposta::redirecionar($voltar);
    }

    public function sair(Requisicao $requisicao): Resposta
    {
        if ($this->sessao->tokenValido($requisicao->campo('token'))) {
            $this->sessao->sair();
        }

        return Resposta::redirecionar('/entrar');
    }

    /**
     * Só aceita caminho local que comece com barra. "//evil.com" e
     * "https://evil.com" são recusados: seria um redirecionamento aberto,
     * e o login é justamente a página que mais convida a esse golpe.
     */
    private static function destinoSeguro(string $destino): string
    {
        if ($destino === '' || $destino[0] !== '/' || str_starts_with($destino, '//')) {
            return '/obras';
        }

        return $destino;
    }
}
