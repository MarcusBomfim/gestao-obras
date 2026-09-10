<?php

declare(strict_types=1);

use GestaoObras\Web\Requisicao;
use GestaoObras\Web\Resposta;
use GestaoObras\Web\Roteador;

function pedido(string $metodo, string $caminho, array $corpo = []): Requisicao
{
    return new Requisicao($metodo, $caminho, [], $corpo);
}

/** Ação que devolve os parâmetros capturados, para o teste inspecionar. */
function acaoQueEcoa(): callable
{
    return static fn (Requisicao $requisicao): Resposta => Resposta::html(
        json_encode($requisicao->parametros, JSON_THROW_ON_ERROR)
    );
}

grupo('Roteador: casamento de rotas');

teste('casa uma rota sem parâmetro', function (): void {
    $roteador = new Roteador();
    $roteador->get('/obras', static fn (): Resposta => Resposta::html('lista'));

    $resposta = $roteador->despachar(pedido('GET', '/obras'));

    igual(200, $resposta->status);
    igual('lista', $resposta->corpo);
});

teste('captura o parâmetro do caminho', function (): void {
    $roteador = new Roteador();
    $roteador->get('/obras/{codigo}', acaoQueEcoa());

    igual('{"codigo":"OBR-2026-001"}', $roteador->despachar(pedido('GET', '/obras/OBR-2026-001'))->corpo);
});

teste('captura dois parâmetros', function (): void {
    $roteador = new Roteador();
    $roteador->post('/obras/{codigo}/diarios/{numero}/remover', acaoQueEcoa());

    igual(
        '{"codigo":"OBR-1","numero":"47"}',
        $roteador->despachar(pedido('POST', '/obras/OBR-1/diarios/47/remover'))->corpo,
    );
});

teste('o parâmetro não atravessa a barra', function (): void {
    // Sem isto, /obras/{codigo} engoliria /obras/OBR-1/diarios inteiro.
    $roteador = new Roteador();
    $roteador->get('/obras/{codigo}', acaoQueEcoa());

    igual(404, $roteador->despachar(pedido('GET', '/obras/OBR-1/diarios'))->status);
});

teste('rota específica e rota com parâmetro convivem', function (): void {
    $roteador = new Roteador();
    $roteador->get('/obras/{codigo}', static fn (): Resposta => Resposta::html('detalhe'));
    $roteador->get('/obras/{codigo}/diarios/novo', static fn (): Resposta => Resposta::html('formulario'));

    igual('detalhe', $roteador->despachar(pedido('GET', '/obras/OBR-1'))->corpo);
    igual('formulario', $roteador->despachar(pedido('GET', '/obras/OBR-1/diarios/novo'))->corpo);
});

teste('barra final não muda a rota', function (): void {
    $roteador = new Roteador();
    $roteador->get('/obras', static fn (): Resposta => Resposta::html('lista'));

    igual(200, $roteador->despachar(pedido('GET', '/obras/'))->status);
});

teste('decodifica o parâmetro vindo da URL', function (): void {
    $roteador = new Roteador();
    $roteador->get('/obras/{codigo}', acaoQueEcoa());

    igual(
        '{"codigo":"OBR 2026 001"}',
        $roteador->despachar(pedido('GET', '/obras/OBR%202026%20001'))->corpo,
    );
});

teste('a raiz é uma rota válida', function (): void {
    $roteador = new Roteador();
    $roteador->get('/', static fn (): Resposta => Resposta::redirecionar('/obras'));

    $resposta = $roteador->despachar(pedido('GET', '/'));

    igual(303, $resposta->status);
    igual('/obras', $resposta->cabecalhos['Location'] ?? null);
});

grupo('Roteador: 404 e 405');

teste('caminho desconhecido devolve 404', function (): void {
    $roteador = new Roteador();
    $roteador->get('/obras', static fn (): Resposta => Resposta::html('lista'));

    igual(404, $roteador->despachar(pedido('GET', '/inexistente'))->status);
});

teste('verbo errado em caminho existente devolve 405', function (): void {
    // É a diferença que separa um roteador de brinquedo de um de verdade:
    // "não existe" e "existe mas não aceita esse verbo" não são a mesma coisa.
    $roteador = new Roteador();
    $roteador->get('/obras', static fn (): Resposta => Resposta::html('lista'));

    igual(405, $roteador->despachar(pedido('DELETE', '/obras'))->status);
});

teste('o 405 informa quais verbos são aceitos', function (): void {
    $roteador = new Roteador();
    $roteador->get('/obras/{codigo}/diarios', static fn (): Resposta => Resposta::html('lista'));
    $roteador->post('/obras/{codigo}/diarios', static fn (): Resposta => Resposta::html('criado'));

    $resposta = $roteador->despachar(pedido('PUT', '/obras/OBR-1/diarios'));

    igual(405, $resposta->status);
    igual('GET, POST', $resposta->cabecalhos['Allow'] ?? null);
});

teste('roteador vazio devolve 404 para tudo', function (): void {
    igual(404, (new Roteador())->despachar(pedido('GET', '/qualquer'))->status);
});

grupo('Requisição: leitura de campos');

teste('lê campo de texto aparando espaços', function (): void {
    igual('Marcus', pedido('POST', '/', ['nome' => '  Marcus  '])->campo('nome'));
});

teste('devolve o padrão quando o campo não veio', function (): void {
    igual('anônimo', pedido('POST', '/', [])->campo('nome', 'anônimo'));
});

teste('converte decimal com vírgula', function (): void {
    // O formulário em português manda "12,50"; o PHP quer ponto.
    igualAproximado(12.5, pedido('POST', '/', ['quantidade' => '12,50'])->campoDecimal('quantidade'));
});

teste('converte inteiro e usa o padrão em branco', function (): void {
    igual(8, pedido('POST', '/', ['efetivo' => '8'])->campoInteiro('efetivo'));
    igual(0, pedido('POST', '/', ['efetivo' => ''])->campoInteiro('efetivo'));
});

teste('lê linhas de um campo repetido', function (): void {
    $requisicao = pedido('POST', '/', [
        'atividades' => [
            ['servico' => 'ALV-01', 'quantidade' => ' 40 '],
            ['servico' => 'COB-01', 'quantidade' => '12'],
        ],
    ]);

    $linhas = $requisicao->linhas('atividades');

    igual(2, count($linhas));
    igual('ALV-01', $linhas[0]['servico']);
    igual('40', $linhas[0]['quantidade'], 'espaços aparados');
});

teste('campo repetido ausente devolve lista vazia', function (): void {
    igual([], pedido('POST', '/', [])->linhas('atividades'));
});

grupo('Resposta');

teste('html vem com content-type e 200', function (): void {
    $resposta = Resposta::html('<p>oi</p>');

    igual(200, $resposta->status);
    igual('text/html; charset=UTF-8', $resposta->cabecalhos['Content-Type'] ?? null);
});

teste('redirecionamento usa 303 depois de POST', function (): void {
    // 303 força o navegador a fazer GET no destino: é o POST-Redirect-GET,
    // que impede o "reenviar formulário?" ao atualizar a página.
    igual(303, Resposta::redirecionar('/obras')->status);
});

teste('não encontrado devolve 404 com corpo', function (): void {
    $resposta = Resposta::naoEncontrado('sumiu');

    igual(404, $resposta->status);
    igual('sumiu', $resposta->corpo);
});
