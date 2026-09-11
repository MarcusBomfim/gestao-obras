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

use GestaoObras\Aplicacao\FecharMedicao;
use GestaoObras\Aplicacao\GerarMedicao;
use GestaoObras\Aplicacao\RegistrarDiarioDeObra;
use GestaoObras\Aplicacao\RemoverDiarioDeObra;
use GestaoObras\Infraestrutura\Banco\Conexao;
use GestaoObras\Infraestrutura\Banco\Migrador;
use GestaoObras\Infraestrutura\Repositorio\RepositorioDeDiariosEmSqlite;
use GestaoObras\Infraestrutura\Repositorio\RepositorioDeMedicoesEmSqlite;
use GestaoObras\Infraestrutura\Repositorio\RepositorioDeObrasEmSqlite;
use GestaoObras\Infraestrutura\Repositorio\RepositorioDeServicosEmSqlite;
use GestaoObras\Web\Controlador\ControladorDeDiarios;
use GestaoObras\Web\Controlador\ControladorDeMedicoes;
use GestaoObras\Web\Controlador\ControladorDeObras;
use GestaoObras\Web\Requisicao;
use GestaoObras\Web\Resposta;
use GestaoObras\Web\Roteador;
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

$obras = new RepositorioDeObrasEmSqlite($conexao);
$servicos = new RepositorioDeServicosEmSqlite($conexao);
$diarios = new RepositorioDeDiariosEmSqlite($conexao);
$medicoes = new RepositorioDeMedicoesEmSqlite($conexao);

$controladorDeObras = new ControladorDeObras($obras, $servicos, $diarios, $visao, $sessao);

$controladorDeMedicoes = new ControladorDeMedicoes(
    $obras,
    $medicoes,
    new GerarMedicao($conexao, $obras, $servicos, $diarios, $medicoes),
    new FecharMedicao($medicoes),
    $visao,
    $sessao,
);

$controladorDeDiarios = new ControladorDeDiarios(
    $obras,
    $servicos,
    $diarios,
    new RegistrarDiarioDeObra($conexao, $obras, $servicos, $diarios),
    new RemoverDiarioDeObra($conexao, $servicos, $diarios),
    $visao,
    $sessao,
);

$roteador = new Roteador();

$roteador->get('/', static fn (): Resposta => Resposta::redirecionar('/obras'));

$roteador->get('/obras', $controladorDeObras->lista(...));
$roteador->get('/obras/{codigo}', $controladorDeObras->detalhe(...));

$roteador->get('/obras/{codigo}/diarios', $controladorDeDiarios->lista(...));
$roteador->get('/obras/{codigo}/diarios/novo', $controladorDeDiarios->formulario(...));
$roteador->post('/obras/{codigo}/diarios', $controladorDeDiarios->criar(...));
$roteador->post('/obras/{codigo}/diarios/{numero}/remover', $controladorDeDiarios->remover(...));

$roteador->get('/obras/{codigo}/medicoes', $controladorDeMedicoes->lista(...));
$roteador->get('/obras/{codigo}/medicoes/{numero}', $controladorDeMedicoes->detalhe(...));
$roteador->post('/obras/{codigo}/medicoes', $controladorDeMedicoes->criar(...));
$roteador->post('/obras/{codigo}/medicoes/{numero}/fechar', $controladorDeMedicoes->fechar(...));

$roteador->despachar(Requisicao::dasSuperglobais())->enviar();
