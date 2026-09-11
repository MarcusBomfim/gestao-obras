<?php

declare(strict_types=1);

namespace GestaoObras\Web;

use GestaoObras\Dominio\Usuario\Usuario;
use RuntimeException;

/**
 * Renderiza um template PHP dentro do layout.
 *
 * As variáveis chegam ao template por extract, e a função e() fica disponível
 * lá dentro. Escapar é obrigação de quem escreve o template — e o nome curto
 * existe justamente para não haver desculpa de esquecer.
 */
final class Visao
{
    private ?Usuario $usuarioAtual = null;
    private string $tokenDaSessao = '';

    public function __construct(private readonly string $diretorio)
    {
    }

    public static function padrao(): self
    {
        return new self(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'visoes');
    }

    /** Quem está logado chega a todo template como $usuarioAtual. */
    public function definirUsuario(?Usuario $usuario): void
    {
        $this->usuarioAtual = $usuario;
    }

    /** O token anti-CSRF chega ao layout para o formulário de sair. */
    public function definirTokenDaSessao(string $token): void
    {
        $this->tokenDaSessao = $token;
    }

    /** @param array<string, mixed> $dados */
    public function renderizar(string $template, array $dados = [], string $titulo = ''): string
    {
        $dados['usuarioAtual'] = $this->usuarioAtual;

        $conteudo = $this->capturar($template, $dados);

        return $this->capturar('layout', [
            'conteudo' => $conteudo,
            'titulo' => $titulo,
            'usuarioAtual' => $this->usuarioAtual,
            'tokenDaSessao' => $this->tokenDaSessao,
            'mensagem' => $dados['mensagem'] ?? null,
            'erro' => $dados['erro'] ?? null,
        ]);
    }

    /** @param array<string, mixed> $dados */
    private function capturar(string $template, array $dados): string
    {
        $arquivo = $this->diretorio . DIRECTORY_SEPARATOR
            . str_replace('.', DIRECTORY_SEPARATOR, $template) . '.php';

        if (!is_file($arquivo)) {
            throw new RuntimeException("Template não encontrado: {$template}");
        }

        extract($dados, EXTR_SKIP);

        ob_start();

        try {
            require $arquivo;

            return (string) ob_get_clean();
        } catch (\Throwable $erro) {
            // Sem isto, um erro no meio do template deixaria o buffer aberto.
            ob_end_clean();

            throw $erro;
        }
    }
}
