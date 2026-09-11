<?php

declare(strict_types=1);

namespace GestaoObras\Dominio\Usuario;

use GestaoObras\Dominio\ExcecaoDeDominio;
use GestaoObras\Dominio\Regras;

/**
 * Uma conta de acesso.
 *
 * A senha nunca passa por aqui em texto: a entidade recebe e devolve só o
 * hash. Quem gera o hash é password_hash(), e quem confere é
 * password_verify() — os dois cuidam de sal e de custo sem que este código
 * precise saber o que é bcrypt.
 */
final class Usuario
{
    public readonly string $email;
    public readonly string $nome;
    public readonly Papel $papel;

    private string $hashDaSenha;
    private bool $ativo;

    public function __construct(
        string $email,
        string $nome,
        Papel $papel,
        string $hashDaSenha,
        bool $ativo = true,
    ) {
        $this->email = self::validarEmail($email);
        $this->nome = Regras::textoObrigatorio($nome, 'Nome', 120);
        $this->papel = $papel;
        $this->hashDaSenha = Regras::textoObrigatorio($hashDaSenha, 'Hash da senha', 255);
        $this->ativo = $ativo;
    }

    /** Cria a conta já gerando o hash. É o único caminho para senha em texto. */
    public static function criar(string $email, string $nome, Papel $papel, string $senha): self
    {
        if (mb_strlen($senha) < 8) {
            throw new ExcecaoDeDominio('A senha precisa ter ao menos 8 caracteres.');
        }

        return new self($email, $nome, $papel, password_hash($senha, PASSWORD_DEFAULT));
    }

    public function hashDaSenha(): string
    {
        return $this->hashDaSenha;
    }

    public function estaAtivo(): bool
    {
        return $this->ativo;
    }

    /**
     * Confere a senha. password_verify compara em tempo constante e sabe ler
     * o algoritmo e o custo que estão embutidos no próprio hash.
     */
    public function senhaConfere(string $senha): bool
    {
        return password_verify($senha, $this->hashDaSenha);
    }

    /**
     * Diz se o hash foi gerado com custo antigo e vale regravar. Quando o
     * custo padrão do PHP sobe numa versão nova, os hashes antigos continuam
     * válidos, mas mais fracos — o login é a hora de atualizar.
     */
    public function hashPrecisaAtualizar(): bool
    {
        return password_needs_rehash($this->hashDaSenha, PASSWORD_DEFAULT);
    }

    public function atualizarSenha(string $senha): void
    {
        if (mb_strlen($senha) < 8) {
            throw new ExcecaoDeDominio('A senha precisa ter ao menos 8 caracteres.');
        }

        $this->hashDaSenha = password_hash($senha, PASSWORD_DEFAULT);
    }

    public function desativar(): void
    {
        $this->ativo = false;
    }

    public function reativar(): void
    {
        $this->ativo = true;
    }

    private static function validarEmail(string $email): string
    {
        $limpo = mb_strtolower(trim($email));

        if (filter_var($limpo, FILTER_VALIDATE_EMAIL) === false) {
            throw new ExcecaoDeDominio("E-mail inválido: {$email}.");
        }

        return $limpo;
    }
}
