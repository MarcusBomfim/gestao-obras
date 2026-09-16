<?php

declare(strict_types=1);

namespace GestaoObras\Web;

use GestaoObras\Aplicacao\Autenticador;
use GestaoObras\Aplicacao\FecharMedicao;
use GestaoObras\Aplicacao\GerarMedicao;
use GestaoObras\Aplicacao\RegistrarDiarioDeObra;
use GestaoObras\Aplicacao\RemoverDiarioDeObra;
use GestaoObras\Dominio\Usuario\Papel;
use GestaoObras\Infraestrutura\Repositorio\RepositorioDeDiariosEmSqlite;
use GestaoObras\Infraestrutura\Repositorio\RepositorioDeMedicoesEmSqlite;
use GestaoObras\Infraestrutura\Repositorio\RepositorioDeObrasEmSqlite;
use GestaoObras\Infraestrutura\Repositorio\RepositorioDeServicosEmSqlite;
use GestaoObras\Infraestrutura\Repositorio\RepositorioDeUsuariosEmSqlite;
use GestaoObras\Web\Controlador\ControladorDeAcesso;
use GestaoObras\Web\Controlador\ControladorDeDiarios;
use GestaoObras\Web\Controlador\ControladorDeMedicoes;
use GestaoObras\Web\Controlador\ControladorDeObras;
use PDO;

/**
 * Monta a aplicação: repositórios, casos de uso, controladores e rotas.
 *
 * É o único lugar que conhece todas as classes concretas. O public/index.php
 * chama isto com a conexão e a sessão de verdade; os testes chamam com um
 * banco em memória e uma sessão em memória, e atravessam o mesmo roteador,
 * os mesmos controladores e os mesmos templates que o navegador atravessa.
 */
final class Montagem
{
    private function __construct()
    {
    }

    public static function roteador(PDO $conexao, Sessao $sessao, Visao $visao): Roteador
    {
        $obras = new RepositorioDeObrasEmSqlite($conexao);
        $servicos = new RepositorioDeServicosEmSqlite($conexao);
        $diarios = new RepositorioDeDiariosEmSqlite($conexao);
        $medicoes = new RepositorioDeMedicoesEmSqlite($conexao);
        $usuarios = new RepositorioDeUsuariosEmSqlite($conexao);

        $guarda = new Guarda($sessao, $usuarios, $visao);

        // Quem está logado e o token chegam a todo template pelo layout.
        $visao->definirUsuario($guarda->usuarioAtual());
        $visao->definirTokenDaSessao($sessao->token());

        $acesso = new ControladorDeAcesso(new Autenticador($usuarios), $visao, $sessao);

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

        // As permissões moram no enum Papel; aqui só se diz qual cada rota exige.
        $apontar = static fn (Papel $papel): bool => $papel->podeApontarDiario();
        $medir = static fn (Papel $papel): bool => $papel->podeMedir();

        $roteador->get('/', static fn (): Resposta => Resposta::redirecionar('/obras'));

        $roteador->get('/entrar', $acesso->formulario(...));
        $roteador->post('/entrar', $acesso->entrar(...));
        $roteador->post('/sair', $acesso->sair(...));

        $roteador->get('/obras', $guarda->autenticado($controladorDeObras->lista(...)));
        $roteador->get('/obras/{codigo}', $guarda->autenticado($controladorDeObras->detalhe(...)));

        $roteador->get('/obras/{codigo}/diarios', $guarda->autenticado($controladorDeDiarios->lista(...)));
        $roteador->get('/obras/{codigo}/diarios/novo', $guarda->com($apontar, $controladorDeDiarios->formulario(...)));
        $roteador->post('/obras/{codigo}/diarios', $guarda->com($apontar, $controladorDeDiarios->criar(...)));
        $roteador->post('/obras/{codigo}/diarios/{numero}/remover', $guarda->com($apontar, $controladorDeDiarios->remover(...)));

        $roteador->get('/obras/{codigo}/medicoes', $guarda->autenticado($controladorDeMedicoes->lista(...)));
        $roteador->get('/obras/{codigo}/medicoes/{numero}', $guarda->autenticado($controladorDeMedicoes->detalhe(...)));
        $roteador->post('/obras/{codigo}/medicoes', $guarda->com($medir, $controladorDeMedicoes->criar(...)));
        $roteador->post('/obras/{codigo}/medicoes/{numero}/fechar', $guarda->com($medir, $controladorDeMedicoes->fechar(...)));

        return $roteador;
    }
}
