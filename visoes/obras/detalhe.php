<?php $obra = $resumo->obra; $atraso = $obra->diasDeAtraso($hoje); ?>

<nav class="trilha"><a href="/obras">Obras</a> › <?= e($obra->codigo) ?></nav>

<header class="cabecalho-obra">
    <div>
        <h1 class="titulo"><?= e($obra->nome) ?></h1>
        <p class="cabecalho-obra__cliente"><?= e($obra->cliente) ?></p>
        <p class="cabecalho-obra__endereco"><?= e($obra->endereco->emUmaLinha()) ?></p>
        <p class="cabecalho-obra__responsavel">
            Responsável técnico: <?= e($obra->responsavelTecnico) ?>
            (<?= e($obra->registroProfissional) ?>)
        </p>
    </div>

    <div class="cabecalho-obra__acoes">
        <span class="etiqueta etiqueta--<?= e($obra->situacao()->value) ?>">
            <?= e($obra->situacao()->rotulo()) ?>
        </span>
        <a class="botao" href="/obras/<?= e(rawurlencode($obra->codigo)) ?>/diarios">Diários</a>
        <a class="botao" href="/obras/<?= e(rawurlencode($obra->codigo)) ?>/medicoes">Medições</a>
        <?php if ($obra->situacao()->aceitaExecucao()): ?>
            <a class="botao botao--primario"
               href="/obras/<?= e(rawurlencode($obra->codigo)) ?>/diarios/novo">Novo diário</a>
        <?php endif ?>
    </div>
</header>

<section class="painel">
    <article class="indicador">
        <span class="indicador__rotulo">Avanço físico</span>
        <strong class="indicador__valor"><?= e(numeroBr($resumo->percentualFisico())) ?>%</strong>
        <span class="indicador__nota">ponderado pelo valor dos serviços</span>
    </article>
    <article class="indicador">
        <span class="indicador__rotulo">Valor previsto</span>
        <strong class="indicador__valor"><?= e(reais($resumo->valorPrevisto())) ?></strong>
    </article>
    <article class="indicador">
        <span class="indicador__rotulo">Executado</span>
        <strong class="indicador__valor"><?= e(reais($resumo->valorExecutado())) ?></strong>
        <span class="indicador__nota">saldo de <?= e(reais($resumo->saldoAExecutar())) ?></span>
    </article>
    <article class="indicador">
        <span class="indicador__rotulo">Prazo</span>
        <strong class="indicador__valor">
            <?= $atraso > 0 ? e($atraso) . ' dia' . ($atraso > 1 ? 's' : '') : 'em dia' ?>
        </strong>
        <span class="indicador__nota">
            <?= e(dataBr($obra->dataDeInicio)) ?> a <?= e(dataBr($obra->dataPrevistaDeTermino())) ?>
        </span>
    </article>
</section>

<h2 class="subtitulo">Curva de avanço</h2>

<?php if (!$curva->temMovimento()): ?>
    <section class="cartao cartao--vazio">
        <p>Nenhum serviço executado ainda — a curva começa com o primeiro diário.</p>
    </section>
<?php else: ?>
    <?php
    $largura = 720;
    $altura = 200;
    $esperado = $curva->avancoLinearEsperado($hoje);
    $yEsperado = $altura - 4 - ($esperado / 100) * ($altura - 8);
    ?>
    <section class="cartao">
        <div class="rolagem">
            <svg class="curva" viewBox="0 0 <?= e($largura) ?> <?= e($altura) ?>"
                 width="100%" height="<?= e($altura) ?>" role="img"
                 aria-label="Curva de avanço acumulado da obra, chegando a <?= e(numeroBr($curva->percentualFinal())) ?> por cento">
                <?php foreach ([0, 25, 50, 75, 100] as $marca): ?>
                    <?php $y = $altura - 4 - ($marca / 100) * ($altura - 8); ?>
                    <line class="curva__grade" x1="0" y1="<?= e($y) ?>"
                          x2="<?= e($largura) ?>" y2="<?= e($y) ?>"></line>
                    <text class="curva__rotulo" x="4" y="<?= e($y - 3) ?>"><?= e($marca) ?>%</text>
                <?php endforeach ?>

                <line class="curva__esperado" x1="0" y1="<?= e($altura - 4) ?>"
                      x2="<?= e($largura) ?>" y2="<?= e($yEsperado) ?>"></line>

                <polyline class="curva__linha" fill="none"
                          points="<?= e($curva->polilinha($largura, $altura)) ?>"></polyline>
            </svg>
        </div>

        <dl class="fatos">
            <div>
                <dt>Executado até hoje</dt>
                <dd><?= e(numeroBr($curva->percentualFinal())) ?>%</dd>
            </div>
            <div>
                <dt>Ritmo linear esperado</dt>
                <dd><?= e(numeroBr($esperado)) ?>%</dd>
            </div>
        </dl>

        <p class="dica">
            A linha reta é a referência mais ingênua possível: supõe ritmo constante do
            primeiro ao último dia do prazo. Serve de comparação enquanto o sistema não
            tem cronograma físico-financeiro de verdade.
        </p>
    </section>
<?php endif ?>

<h2 class="subtitulo">Serviços do orçamento</h2>

<?php if ($servicos === []): ?>
    <section class="cartao cartao--vazio">
        <p>Esta obra ainda não tem serviços no orçamento.</p>
    </section>
<?php else: ?>
    <div class="rolagem">
        <table class="tabela">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Descrição</th>
                    <th class="numerico">Previsto</th>
                    <th class="numerico">Executado</th>
                    <th class="numerico">Saldo</th>
                    <th class="avanco-coluna">Avanço</th>
                    <th class="numerico">Valor executado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($servicos as $servico): ?>
                    <tr<?= $servico->estaConcluido() ? ' class="linha--concluida"' : '' ?>>
                        <td class="codigo"><?= e($servico->codigo) ?></td>
                        <td><?= e($servico->descricao) ?></td>
                        <td class="numerico"><?= e($servico->unidade->formatar($servico->quantidadePrevista)) ?></td>
                        <td class="numerico"><?= e($servico->unidade->formatar($servico->quantidadeExecutada())) ?></td>
                        <td class="numerico"><?= e($servico->unidade->formatar($servico->saldo())) ?></td>
                        <td>
                            <div class="avanco avanco--linha">
                                <div class="avanco__trilho">
                                    <div class="avanco__barra" style="width: <?= e(barraDeAvanco($servico->percentualExecutado())) ?>%"></div>
                                </div>
                                <span class="avanco__numero"><?= e(numeroBr($servico->percentualExecutado())) ?>%</span>
                            </div>
                        </td>
                        <td class="numerico"><?= e(reais($servico->valorExecutado())) ?></td>
                    </tr>
                <?php endforeach ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="6">Total</th>
                    <td class="numerico"><?= e(reais($resumo->valorExecutado())) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
<?php endif ?>

<h2 class="subtitulo">Últimos diários</h2>

<?php if ($ultimosDiarios === []): ?>
    <section class="cartao cartao--vazio">
        <p>Nenhum diário registrado para esta obra.</p>
    </section>
<?php else: ?>
    <ul class="lista-diarios">
        <?php foreach ($ultimosDiarios as $diario): ?>
            <li class="lista-diarios__item<?= $diario->ehDiaPerdido() ? ' lista-diarios__item--perdido' : '' ?>">
                <span class="lista-diarios__numero">RDO <?= e($diario->numero()) ?></span>
                <span class="lista-diarios__data"><?= e(dataBr($diario->data)) ?></span>
                <span class="lista-diarios__efetivo"><?= e($diario->efetivo()->total()) ?> pessoas</span>
                <span class="lista-diarios__resumo">
                    <?php if ($diario->ehDiaPerdido()): ?>
                        dia impraticável
                    <?php else: ?>
                        <?= e(count($diario->atividades())) ?> atividade(s)
                    <?php endif ?>
                </span>
            </li>
        <?php endforeach ?>
    </ul>
    <p><a href="/obras/<?= e(rawurlencode($obra->codigo)) ?>/diarios">Ver todos os diários</a></p>
<?php endif ?>
