<?php

declare(strict_types=1);

namespace GestaoObras\Web;

use GestaoObras\Dominio\Usuario\Papel;
use GestaoObras\Dominio\Usuario\RepositorioDeUsuarios;
use GestaoObras\Dominio\Usuario\Usuario;

/**
 * Envolve uma ação de controlador com a exigência de login e de permissão.
 *
 * A permissão é conferida a cada requisição lendo o usuário no banco, e não
 * confiando no que está na sessão. Assim, desativar uma conta ou trocar o
 * papel dela vale no acesso seguinte — sem esperar a sessão expirar.
 */
final class Guarda
{
    public function __construct(
        private readonly Sessao $sessao,
        private readonly RepositorioDeUsuarios $usuarios,
        private readonly Visao $visao,
    ) {
    }

    public function usuarioAtual(): ?Usuario
    {
        $email = $this->sessao->emailAtual();

        if ($email === null) {
            return null;
        }

        $usuario = $this->usuarios->porEmail($email);

        if ($usuario === null || !$usuario->estaAtivo()) {
            return null;
        }

        return $usuario;
    }

    /** Exige apenas estar logado. */
    public function autenticado(callable $acao): callable
    {
        return $this->com(static fn (Papel $papel): bool => $papel->podeConsultar(), $acao);
    }

    /**
     * Exige estar logado e ter a permissão. A permissão é uma função sobre o
     * papel, para a regra morar no enum Papel e não aqui.
     *
     * @param callable(Papel): bool $permissao
     */
    public function com(callable $permissao, callable $acao): callable
    {
        return function (Requisicao $requisicao) use ($permissao, $acao): Resposta {
            $usuario = $this->usuarioAtual();

            if ($usuario === null) {
                // Guarda o destino para voltar a ele depois do login.
                return Resposta::redirecionar(
                    '/entrar?voltar=' . rawurlencode($requisicao->caminho)
                );
            }

            if (!$permissao($usuario->papel)) {
                return Resposta::proibido($this->visao->renderizar('erro', [
                    'titulo' => 'Sem permissão',
                    'detalhe' => sprintf(
                        'O papel "%s" não pode fazer isto. %s',
                        $usuario->papel->rotulo(),
                        $usuario->papel->descricaoDasPermissoes(),
                    ),
                ], 'Sem permissão'));
            }

            return $acao($requisicao);
        };
    }
}
