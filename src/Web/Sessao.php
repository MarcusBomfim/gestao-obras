<?php

declare(strict_types=1);

namespace GestaoObras\Web;

/**
 * Sessão, token anti-CSRF e mensagens que sobrevivem ao redirecionamento.
 *
 * O token existe porque toda alteração aqui é feita por POST, e sem ele
 * qualquer página externa poderia enviar um formulário em nome de quem está
 * autenticado — apagar um diário, por exemplo. O navegador manda os cookies
 * de qualquer jeito; o que o site de terceiro não consegue é adivinhar o token.
 *
 * Em memória, para os testes: a mesma classe, sem session_start(). O que se
 * testa é o comportamento — token, login, mensagens —, não o mecanismo de
 * cookie do PHP, que os testes não conseguem exercitar na linha de comando.
 */
final class Sessao
{
    private const CHAVE_TOKEN = '_token';
    private const CHAVE_MENSAGEM = '_mensagem';
    private const CHAVE_ERRO = '_erro';
    private const CHAVE_USUARIO = '_usuario';

    /** @var array<string, mixed>|null nulo quando a sessão é a do PHP */
    private ?array $memoria = null;

    public static function emMemoria(): self
    {
        $sessao = new self();
        $sessao->memoria = [];

        return $sessao;
    }

    /**
     * Marca a sessão como autenticada.
     *
     * Regenera o id no login para a sessão anônima anterior não virar sessão
     * autenticada. É a defesa contra fixação de sessão: sem isto, quem
     * conseguisse plantar um id de sessão no navegador da vítima passaria a
     * compartilhar a sessão dela depois do login.
     */
    public function entrar(string $email): void
    {
        $dados = &$this->dados();

        if ($this->memoria === null) {
            session_regenerate_id(true);
        }

        $dados[self::CHAVE_USUARIO] = $email;

        // Sessão nova, token novo.
        unset($dados[self::CHAVE_TOKEN]);
    }

    public function sair(): void
    {
        $dados = &$this->dados();
        $dados = [];

        if ($this->memoria === null) {
            session_regenerate_id(true);
            session_destroy();
        }
    }

    public function emailAtual(): ?string
    {
        $email = $this->dados()[self::CHAVE_USUARIO] ?? null;

        return is_string($email) && $email !== '' ? $email : null;
    }

    public function iniciar(): void
    {
        if ($this->memoria !== null || session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_set_cookie_params([
            'httponly' => true,
            'samesite' => 'Strict',
            'path' => '/',
        ]);

        session_start();
    }

    public function token(): string
    {
        $dados = &$this->dados();

        if (!isset($dados[self::CHAVE_TOKEN]) || !is_string($dados[self::CHAVE_TOKEN])) {
            $dados[self::CHAVE_TOKEN] = bin2hex(random_bytes(32));
        }

        return $dados[self::CHAVE_TOKEN];
    }

    /**
     * Compara com hash_equals, e não com ===, para o tempo da comparação não
     * revelar quantos caracteres do token estavam certos.
     */
    public function tokenValido(string $enviado): bool
    {
        $guardado = $this->dados()[self::CHAVE_TOKEN] ?? null;

        return is_string($guardado) && $guardado !== '' && hash_equals($guardado, $enviado);
    }

    public function guardarMensagem(string $mensagem): void
    {
        $dados = &$this->dados();
        $dados[self::CHAVE_MENSAGEM] = $mensagem;
    }

    public function guardarErro(string $erro): void
    {
        $dados = &$this->dados();
        $dados[self::CHAVE_ERRO] = $erro;
    }

    /** Lê e apaga: a mensagem aparece uma vez só, na página seguinte. */
    public function tirarMensagem(): ?string
    {
        return $this->tirar(self::CHAVE_MENSAGEM);
    }

    public function tirarErro(): ?string
    {
        return $this->tirar(self::CHAVE_ERRO);
    }

    private function tirar(string $chave): ?string
    {
        $dados = &$this->dados();

        $valor = $dados[$chave] ?? null;
        unset($dados[$chave]);

        return is_string($valor) ? $valor : null;
    }

    /**
     * Onde a sessão mora: no $_SESSION do PHP ou no array em memória. É
     * devolvido por referência para as escritas chegarem ao lugar certo.
     *
     * @return array<string, mixed>
     */
    private function &dados(): array
    {
        if ($this->memoria !== null) {
            return $this->memoria;
        }

        $this->iniciar();

        return $_SESSION;
    }
}
