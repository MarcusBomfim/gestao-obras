<?php

declare(strict_types=1);

use GestaoObras\Dominio\Usuario\Papel;
use GestaoObras\Dominio\Usuario\Usuario;
use GestaoObras\Infraestrutura\Repositorio\RepositorioDeUsuariosEmSqlite;
use GestaoObras\Web\Montagem;
use GestaoObras\Web\Requisicao;
use GestaoObras\Web\Resposta;
use GestaoObras\Web\Sessao;
use GestaoObras\Web\Visao;

/*
 * A aplicação inteira, sem servidor: banco em memória, sessão em memória e a
 * mesma Montagem que o public/index.php usa. Cada requisição atravessa o
 * roteador, a guarda, o controlador e o template de verdade — é o mais perto
 * de "abriu no navegador" que um teste chega sem abrir o navegador.
 *
 * Qualquer aviso do PHP durante a renderização vira falha: um template que
 * lê variável indefinida não pode passar em silêncio.
 */

/** @return array{requisitar: callable(string, string, array=): Resposta, app: array, sessao: Sessao} */
function aplicacaoWeb(): array
{
    $app = ambienteDeObraEmAndamento();
    $sessao = Sessao::emMemoria();
    $visao = Visao::padrao();

    $usuarios = new RepositorioDeUsuariosEmSqlite($app['conexao']);
    $usuarios->salvar(Usuario::criar('eng@teste.dev', 'Engenheira de Teste', Papel::Engenheiro, 'SenhaDeTeste1'));
    $usuarios->salvar(Usuario::criar('cliente@teste.dev', 'Cliente de Teste', Papel::Cliente, 'SenhaDeTeste1'));

    $requisitar = static function (string $metodo, string $caminho, array $corpo = []) use ($app, $sessao, $visao): Resposta {
        $anterior = set_error_handler(static function (int $nivel, string $mensagem, string $arquivo, int $linha): bool {
            throw new ErrorException($mensagem, 0, $nivel, $arquivo, $linha);
        });

        try {
            // A montagem é por requisição, como no index.php: a guarda lê o
            // usuário da sessão a cada pedido.
            $roteador = Montagem::roteador($app['conexao'], $sessao, $visao);

            return $roteador->despachar(new Requisicao($metodo, $caminho, [], $corpo));
        } finally {
            set_error_handler($anterior);
        }
    };

    return ['requisitar' => $requisitar, 'app' => $app, 'sessao' => $sessao];
}

/** Faz o login pela rota, como o navegador faria, e devolve o token da sessão. */
function entrarComo(callable $requisitar, Sessao $sessao, string $email): string
{
    $resposta = $requisitar('POST', '/entrar', [
        'token' => $sessao->token(),
        'email' => $email,
        'senha' => 'SenhaDeTeste1',
    ]);

    igual(303, $resposta->status, 'login redireciona');
    igual('/obras', $resposta->cabecalhos['Location'] ?? null, 'login volta para as obras');

    return $sessao->token();
}

function contem(string $trecho, string $texto, string $contexto): void
{
    verdadeiro(str_contains($texto, $trecho), $contexto . " — esperava encontrar \"{$trecho}\"");
}

grupo('Fluxo web: acesso');

teste('sem login, a lista de obras redireciona para o login guardando o destino', function (): void {
    ['requisitar' => $requisitar] = aplicacaoWeb();

    $resposta = $requisitar('GET', '/obras');

    igual(303, $resposta->status);
    igual('/entrar?voltar=%2Fobras', $resposta->cabecalhos['Location'] ?? null);
});

teste('a página de login renderiza com o formulário', function (): void {
    ['requisitar' => $requisitar] = aplicacaoWeb();

    $resposta = $requisitar('GET', '/entrar');

    igual(200, $resposta->status);
    contem('name="email"', $resposta->corpo, 'campo de e-mail');
    contem('name="senha"', $resposta->corpo, 'campo de senha');
    contem('name="token"', $resposta->corpo, 'token anti-CSRF no formulário');
});

teste('senha errada volta para o login com a mensagem de erro', function (): void {
    ['requisitar' => $requisitar, 'sessao' => $sessao] = aplicacaoWeb();

    $resposta = $requisitar('POST', '/entrar', [
        'token' => $sessao->token(),
        'email' => 'eng@teste.dev',
        'senha' => 'senha-errada',
    ]);

    igual(303, $resposta->status);
    igual('/entrar', $resposta->cabecalhos['Location'] ?? null);

    $login = $requisitar('GET', '/entrar');
    contem('aviso--erro', $login->corpo, 'o erro aparece na página seguinte');
    igual(null, $sessao->emailAtual(), 'ninguém logado');
});

teste('login sem token é recusado', function (): void {
    ['requisitar' => $requisitar, 'sessao' => $sessao] = aplicacaoWeb();

    $resposta = $requisitar('POST', '/entrar', ['email' => 'eng@teste.dev', 'senha' => 'SenhaDeTeste1']);

    igual(303, $resposta->status);
    igual(null, $sessao->emailAtual(), 'ninguém logado');
});

teste('login certo abre a lista de obras com o usuário no topo', function (): void {
    ['requisitar' => $requisitar, 'sessao' => $sessao] = aplicacaoWeb();
    entrarComo($requisitar, $sessao, 'eng@teste.dev');

    $resposta = $requisitar('GET', '/obras');

    igual(200, $resposta->status);
    contem('Reforma estrutural do galpão 3', $resposta->corpo, 'a obra está na lista');
    contem('Engenheira de Teste', $resposta->corpo, 'o nome de quem entrou');
});

teste('sair encerra a sessão', function (): void {
    ['requisitar' => $requisitar, 'sessao' => $sessao] = aplicacaoWeb();
    $token = entrarComo($requisitar, $sessao, 'eng@teste.dev');

    $resposta = $requisitar('POST', '/sair', ['token' => $token]);

    igual(303, $resposta->status);
    igual(null, $sessao->emailAtual());
    igual(303, $requisitar('GET', '/obras')->status, 'a lista volta a pedir login');
});

grupo('Fluxo web: obra e diário');

teste('a página da obra renderiza com serviços, e a curva aparece com o primeiro diário', function (): void {
    ['requisitar' => $requisitar, 'sessao' => $sessao] = aplicacaoWeb();
    $token = entrarComo($requisitar, $sessao, 'eng@teste.dev');

    $antes = $requisitar('GET', '/obras/OBR-2026-001');

    igual(200, $antes->status);
    contem('ALV-01', $antes->corpo, 'o serviço do orçamento');
    contem('a curva começa com o primeiro diário', $antes->corpo, 'sem diário não há curva, e a página diz isso');

    $requisitar('POST', '/obras/OBR-2026-001/diarios', [
        'token' => $token,
        'data' => '2026-02-10',
        'responsavel' => 'Helena Duarte',
        'atividades' => [['servico' => 'ALV-01', 'quantidade' => '32', 'observacao' => '']],
    ]);

    $depois = $requisitar('GET', '/obras/OBR-2026-001');

    igual(200, $depois->status);
    contem('<svg', $depois->corpo, 'a curva de avanço é desenhada no servidor');
    contem('polyline', $depois->corpo, 'com a linha do executado');
});

teste('obra inexistente devolve 404 com página de erro', function (): void {
    ['requisitar' => $requisitar, 'sessao' => $sessao] = aplicacaoWeb();
    entrarComo($requisitar, $sessao, 'eng@teste.dev');

    $resposta = $requisitar('GET', '/obras/NAO-EXISTE');

    igual(404, $resposta->status);
    contem('Obra não encontrada', $resposta->corpo, 'título da página de erro');
});

teste('registra um diário pelo formulário e o serviço avança', function (): void {
    ['requisitar' => $requisitar, 'sessao' => $sessao, 'app' => $app] = aplicacaoWeb();
    $token = entrarComo($requisitar, $sessao, 'eng@teste.dev');

    igual(200, $requisitar('GET', '/obras/OBR-2026-001/diarios/novo')->status, 'o formulário abre');

    $resposta = $requisitar('POST', '/obras/OBR-2026-001/diarios', [
        'token' => $token,
        'data' => '2026-02-10',
        'responsavel' => 'Helena Duarte',
        'clima_manha' => 'bom', 'condicao_manha' => 'praticavel',
        'clima_tarde' => 'bom', 'condicao_tarde' => 'praticavel',
        'clima_noite' => 'bom', 'condicao_noite' => 'praticavel',
        'efetivo_pedreiro' => '4',
        'efetivo_servente' => '6',
        'atividades' => [
            ['servico' => 'ALV-01', 'quantidade' => '40,5', 'observacao' => 'Eixo 3'],
            ['servico' => '', 'quantidade' => '', 'observacao' => ''],
        ],
        'ocorrencias' => [
            ['tipo' => 'visita', 'descricao' => 'Visita do cliente'],
            ['tipo' => 'outro', 'descricao' => ''],
        ],
    ]);

    igual(303, $resposta->status);
    igual('/obras/OBR-2026-001/diarios', $resposta->cabecalhos['Location'] ?? null);

    $lista = $requisitar('GET', '/obras/OBR-2026-001/diarios');
    igual(200, $lista->status);
    contem('Diário nº 1 registrado', $lista->corpo, 'a mensagem de sucesso');

    igualAproximado(40.5, $app['servicos']->porCodigo('OBR-2026-001', 'ALV-01')?->quantidadeExecutada() ?? 0.0);
});

teste('diário com token errado não é gravado', function (): void {
    ['requisitar' => $requisitar, 'sessao' => $sessao, 'app' => $app] = aplicacaoWeb();
    entrarComo($requisitar, $sessao, 'eng@teste.dev');

    $resposta = $requisitar('POST', '/obras/OBR-2026-001/diarios', [
        'token' => 'forjado',
        'data' => '2026-02-10',
        'responsavel' => 'Helena Duarte',
        'atividades' => [['servico' => 'ALV-01', 'quantidade' => '10', 'observacao' => '']],
    ]);

    igual(303, $resposta->status);
    igual('/obras/OBR-2026-001/diarios/novo', $resposta->cabecalhos['Location'] ?? null, 'volta ao formulário');
    igual([], $app['diarios']->daObra('OBR-2026-001'), 'nada gravado');
});

teste('a recusa do domínio chega à tela como mensagem, não como erro 500', function (): void {
    ['requisitar' => $requisitar, 'sessao' => $sessao] = aplicacaoWeb();
    $token = entrarComo($requisitar, $sessao, 'eng@teste.dev');

    // 400 m² contra 320 previstos: o serviço recusa.
    $requisitar('POST', '/obras/OBR-2026-001/diarios', [
        'token' => $token,
        'data' => '2026-02-10',
        'responsavel' => 'Helena Duarte',
        'atividades' => [['servico' => 'ALV-01', 'quantidade' => '400', 'observacao' => '']],
    ]);

    $formulario = $requisitar('GET', '/obras/OBR-2026-001/diarios/novo');
    contem('aviso--erro', $formulario->corpo, 'o erro aparece no formulário');
    contem('saldo', $formulario->corpo, 'a mensagem é a do domínio, com o saldo');
});

teste('remover o diário estorna o serviço', function (): void {
    ['requisitar' => $requisitar, 'sessao' => $sessao, 'app' => $app] = aplicacaoWeb();
    $token = entrarComo($requisitar, $sessao, 'eng@teste.dev');

    $requisitar('POST', '/obras/OBR-2026-001/diarios', [
        'token' => $token,
        'data' => '2026-02-10',
        'responsavel' => 'Helena Duarte',
        'atividades' => [['servico' => 'ALV-01', 'quantidade' => '25', 'observacao' => '']],
    ]);

    $resposta = $requisitar('POST', '/obras/OBR-2026-001/diarios/1/remover', ['token' => $token]);

    igual(303, $resposta->status);
    igualAproximado(0.0, $app['servicos']->porCodigo('OBR-2026-001', 'ALV-01')?->quantidadeExecutada() ?? -1.0);
});

grupo('Fluxo web: medição');

teste('gera, abre e fecha a medição pelas rotas', function (): void {
    ['requisitar' => $requisitar, 'sessao' => $sessao] = aplicacaoWeb();
    $token = entrarComo($requisitar, $sessao, 'eng@teste.dev');

    $requisitar('POST', '/obras/OBR-2026-001/diarios', [
        'token' => $token,
        'data' => '2026-02-10',
        'responsavel' => 'Helena Duarte',
        'atividades' => [['servico' => 'ALV-01', 'quantidade' => '32', 'observacao' => '']],
    ]);

    $criada = $requisitar('POST', '/obras/OBR-2026-001/medicoes', [
        'token' => $token,
        'inicio' => '2026-02-02',
        'fim' => '2026-02-28',
    ]);

    igual(303, $criada->status);
    igual('/obras/OBR-2026-001/medicoes/1', $criada->cabecalhos['Location'] ?? null);

    $detalhe = $requisitar('GET', '/obras/OBR-2026-001/medicoes/1');
    igual(200, $detalhe->status);
    contem('Memória de cálculo', $detalhe->corpo, 'a memória de cálculo está na página');
    contem('ALV-01', $detalhe->corpo, 'o item medido');
    contem('Fechar medição', $detalhe->corpo, 'o engenheiro vê o botão de fechar');

    $fechada = $requisitar('POST', '/obras/OBR-2026-001/medicoes/1/fechar', ['token' => $token]);
    igual(303, $fechada->status);

    $depois = $requisitar('GET', '/obras/OBR-2026-001/medicoes/1');
    contem('Fechada', $depois->corpo, 'a situação mudou');
    falso(str_contains($depois->corpo, 'Fechar medição'), 'o botão some depois de fechar');

    igual(200, $requisitar('GET', '/obras/OBR-2026-001/medicoes')->status, 'a lista de medições abre');
});

grupo('Fluxo web: papéis');

teste('cliente consulta mas não aponta diário nem mede', function (): void {
    ['requisitar' => $requisitar, 'sessao' => $sessao] = aplicacaoWeb();
    $token = entrarComo($requisitar, $sessao, 'cliente@teste.dev');

    igual(200, $requisitar('GET', '/obras/OBR-2026-001')->status, 'consulta liberada');

    $formulario = $requisitar('GET', '/obras/OBR-2026-001/diarios/novo');
    igual(403, $formulario->status, 'o formulário de diário é proibido');
    contem('Sem permissão', $formulario->corpo, 'página de erro explicando');

    $post = $requisitar('POST', '/obras/OBR-2026-001/diarios', ['token' => $token, 'data' => '2026-02-10']);
    igual(403, $post->status, 'o POST direto também é barrado no servidor');

    igual(403, $requisitar('POST', '/obras/OBR-2026-001/medicoes', ['token' => $token])->status, 'medir é proibido');
});

teste('a interface esconde do cliente o que ele não pode fazer', function (): void {
    ['requisitar' => $requisitar, 'sessao' => $sessao] = aplicacaoWeb();
    entrarComo($requisitar, $sessao, 'cliente@teste.dev');

    $detalhe = $requisitar('GET', '/obras/OBR-2026-001');

    falso(str_contains($detalhe->corpo, 'diarios/novo'), 'sem link para novo diário');
});
