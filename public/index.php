<?php

declare(strict_types=1);

/*
 * Ponto de entrada da aplicação web.
 *
 *   php -S localhost:8000 -t public public/index.php
 *
 * Antes da primeira execução:
 *   php ferramentas/migrar.php
 *   php ferramentas/semear.php
 */

/*
 * O servidor embutido do PHP não serve arquivo estático quando há um script de
 * roteamento. Devolver false devolve a tarefa a ele — é como o estilo.css
 * chega ao navegador sem passar pelo roteador.
 */
if (PHP_SAPI === 'cli-server') {
    $caminhoDoArquivo = __DIR__ . parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH);

    if (is_file($caminhoDoArquivo)) {
        return false;
    }
}

require dirname(__DIR__) . '/src/autoload.php';

use GestaoObras\Infraestrutura\Banco\Conexao;
use GestaoObras\Infraestrutura\Banco\Migrador;
use GestaoObras\Web\Montagem;
use GestaoObras\Web\Requisicao;
use GestaoObras\Web\Resposta;
use GestaoObras\Web\Sessao;
use GestaoObras\Web\Visao;

$visao = Visao::padrao();

try {
    $conexao = Conexao::abrir();
    $pendentes = Migrador::padrao($conexao)->pendentes();
} catch (Throwable $erro) {
    // Sem banco não há aplicação: melhor dizer o que fazer do que dar erro 500.
    Resposta::html($visao->renderizar('erro', [
        'titulo' => 'Banco de dados indisponível',
        'detalhe' => 'Rode php ferramentas/migrar.php e tente de novo.',
    ], 'Banco indisponível'), 500)->enviar();

    return;
}

if ($pendentes !== []) {
    Resposta::html($visao->renderizar('erro', [
        'titulo' => 'Há migrations pendentes',
        'detalhe' => 'Rode php ferramentas/migrar.php antes de usar a aplicação.',
    ], 'Migrations pendentes'), 503)->enviar();

    return;
}

$sessao = new Sessao();
$sessao->iniciar();

// Toda a montagem — repositórios, casos de uso, controladores e rotas — fica
// em Montagem, para os testes atravessarem exatamente o mesmo caminho.
Montagem::roteador($conexao, $sessao, $visao)
    ->despachar(Requisicao::dasSuperglobais())
    ->enviar();
