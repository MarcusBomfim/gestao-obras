<?php

declare(strict_types=1);

namespace GestaoObras\Infraestrutura\Repositorio;

use GestaoObras\Dominio\Usuario\Papel;
use GestaoObras\Dominio\Usuario\RepositorioDeUsuarios;
use GestaoObras\Dominio\Usuario\Usuario;
use PDO;

final class RepositorioDeUsuariosEmSqlite implements RepositorioDeUsuarios
{
    private const COLUNAS = 'email, nome, papel, hash_senha, ativo';

    public function __construct(private readonly PDO $conexao)
    {
    }

    public function salvar(Usuario $usuario): void
    {
        $comando = $this->conexao->prepare(
            'INSERT INTO usuarios (' . self::COLUNAS . ')
             VALUES (:email, :nome, :papel, :hash_senha, :ativo)
             ON CONFLICT (email) DO UPDATE SET
                nome          = excluded.nome,
                papel         = excluded.papel,
                hash_senha    = excluded.hash_senha,
                ativo         = excluded.ativo,
                atualizado_em = datetime(\'now\')'
        );

        $comando->execute([
            ':email' => $usuario->email,
            ':nome' => $usuario->nome,
            ':papel' => $usuario->papel->value,
            ':hash_senha' => $usuario->hashDaSenha(),
            ':ativo' => $usuario->estaAtivo() ? 1 : 0,
        ]);
    }

    public function porEmail(string $email): ?Usuario
    {
        $consulta = $this->conexao->prepare(
            'SELECT ' . self::COLUNAS . ' FROM usuarios WHERE email = :email'
        );
        $consulta->execute([':email' => mb_strtolower(trim($email))]);

        $linha = $consulta->fetch();

        return $linha === false ? null : self::montar($linha);
    }

    public function todos(): array
    {
        $consulta = $this->conexao->query(
            'SELECT ' . self::COLUNAS . ' FROM usuarios ORDER BY nome'
        );

        return array_map(self::montar(...), $consulta === false ? [] : $consulta->fetchAll());
    }

    public function existe(string $email): bool
    {
        $consulta = $this->conexao->prepare('SELECT 1 FROM usuarios WHERE email = :email');
        $consulta->execute([':email' => mb_strtolower(trim($email))]);

        return $consulta->fetchColumn() !== false;
    }

    /** @param array<string, mixed> $linha */
    private static function montar(array $linha): Usuario
    {
        return new Usuario(
            (string) $linha['email'],
            (string) $linha['nome'],
            Papel::from((string) $linha['papel']),
            (string) $linha['hash_senha'],
            (int) $linha['ativo'] === 1,
        );
    }
}
