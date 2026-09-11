<?php

declare(strict_types=1);

namespace GestaoObras\Aplicacao;

use GestaoObras\Dominio\Usuario\RepositorioDeUsuarios;
use GestaoObras\Dominio\Usuario\Usuario;

/**
 * Confere e-mail e senha.
 *
 * Devolve o usuário ou null — e nunca diz qual dos dois estava errado. Uma
 * mensagem diferente para "e-mail não existe" entregaria a lista de contas a
 * quem ficasse tentando.
 */
final class Autenticador
{
    /** Hash gerado uma vez por processo, para a conta inexistente "custar" o mesmo. */
    private static ?string $hashIsca = null;

    public function __construct(private readonly RepositorioDeUsuarios $usuarios)
    {
    }

    public function autenticar(string $email, string $senha): ?Usuario
    {
        $usuario = $this->usuarios->porEmail($email);

        if ($usuario === null) {
            /*
             * Conferir contra um hash de mentira gasta o mesmo tempo que a
             * conferência real. Sem isto, e-mail inexistente responderia em
             * microssegundos e e-mail existente em dezenas de milissegundos —
             * e o tempo de resposta contaria quais contas existem.
             */
            password_verify($senha, self::hashIsca());

            return null;
        }

        if (!$usuario->estaAtivo() || !$usuario->senhaConfere($senha)) {
            return null;
        }

        // Login é o momento de regravar hashes feitos com custo antigo.
        if ($usuario->hashPrecisaAtualizar()) {
            $usuario->atualizarSenha($senha);
            $this->usuarios->salvar($usuario);
        }

        return $usuario;
    }

    private static function hashIsca(): string
    {
        return self::$hashIsca ??= password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    }
}
