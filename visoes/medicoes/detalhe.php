<nav class="trilha">
    <a href="/obras">Obras</a> ›
    <a href="/obras/<?= e(rawurlencode($obra->codigo)) ?>"><?= e($obra->codigo) ?></a> ›
    <a href="/obras/<?= e(rawurlencode($obra->codigo)) ?>/medicoes">Medições</a> ›
    Nº <?= e($medicao->numero()) ?>
</nav>

<header class="cabecalho-obra">
    <div>
        <h1 class="titulo">Medição nº <?= e($medicao->numero()) ?></h1>
        <p class="cabecalho-obra__cliente"><?= e($obra->nome) ?> — <?= e($obra->cliente) ?></p>
        <p class="cabecalho-obra__endereco">
            Período de <?= e(dataBr($medicao->inicio)) ?> a <?= e(dataBr($medicao->fim)) ?>
            (<?= e($medicao->diasDoPeriodo()) ?> dias)
        </p>
    </div>

    <div class="cabecalho-obra__acoes">
        <span class="etiqueta etiqueta--<?= $medicao->estaFechada() ? 'concluida' : 'planejada' ?>">
            <?= e($medicao->situacao()->rotulo()) ?>
        </span>

        <?php if (!$medicao->estaFechada() && ($usuarioAtual?->papel->podeMedir() ?? false)): ?>
            <form method="post"
                  action="/obras/<?= e(rawurlencode($obra->codigo)) ?>/medicoes/<?= e($medicao->numero()) ?>/fechar"
                  onsubmit="return confirm('Fechar a medição nº <?= e($medicao->numero()) ?>? Depois disso ela vira documento e não pode mais ser alterada.');">
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <button type="submit" class="botao botao--primario">Fechar medição</button>
            </form>
        <?php endif ?>
    </div>
</header>

<section class="painel">
    <article class="indicador">
        <span class="indicador__rotulo">A faturar no período</span>
        <strong class="indicador__valor"><?= e(reais($medicao->valorNoPeriodo())) ?></strong>
    </article>
    <article class="indicador">
        <span class="indicador__rotulo">Acumulado da obra</span>
        <strong class="indicador__valor"><?= e(reais($medicao->valorAcumulado())) ?></strong>
        <span class="indicador__nota">de <?= e(reais($medicao->valorPrevisto())) ?> previstos</span>
    </article>
    <article class="indicador">
        <span class="indicador__rotulo">Avanço acumulado</span>
        <strong class="indicador__valor"><?= e(numeroBr($medicao->percentualAcumulado())) ?>%</strong>
        <span class="indicador__nota">ponderado pelo valor</span>
    </article>
    <article class="indicador">
        <span class="indicador__rotulo">Itens com movimento</span>
        <strong class="indicador__valor">
            <?= e(count($medicao->itensComMovimento())) ?>/<?= e(count($medicao->itens())) ?>
        </strong>
    </article>
</section>

<h2 class="subtitulo">Memória de cálculo</h2>

<div class="rolagem">
    <table class="tabela">
        <thead>
            <tr>
                <th>Código</th>
                <th>Descrição</th>
                <th class="numerico">Preço unit.</th>
                <th class="numerico">Previsto</th>
                <th class="numerico">Acum. anterior</th>
                <th class="numerico">No período</th>
                <th class="numerico">Acumulado</th>
                <th class="numerico">%</th>
                <th class="numerico">Valor no período</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($medicao->itens() as $item): ?>
                <tr<?= $item->teveMovimento() ? '' : ' class="linha--parada"' ?>>
                    <td class="codigo"><?= e($item->servicoCodigo) ?></td>
                    <td><?= e($item->descricao) ?></td>
                    <td class="numerico"><?= e(reais($item->precoUnitario)) ?></td>
                    <td class="numerico"><?= e($item->unidade->formatar($item->quantidadePrevista)) ?></td>
                    <td class="numerico"><?= e($item->unidade->formatar($item->acumuladoAnterior)) ?></td>
                    <td class="numerico"><strong><?= e($item->unidade->formatar($item->noPeriodo)) ?></strong></td>
                    <td class="numerico"><?= e($item->unidade->formatar($item->acumulado())) ?></td>
                    <td class="numerico"><?= e(numeroBr($item->percentualAcumulado())) ?>%</td>
                    <td class="numerico"><?= e(reais($item->valorNoPeriodo())) ?></td>
                </tr>
            <?php endforeach ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="8">Total a faturar nesta medição</th>
                <td class="numerico"><?= e(reais($medicao->valorNoPeriodo())) ?></td>
            </tr>
        </tfoot>
    </table>
</div>

<p class="dica">
    As linhas sem movimento no período aparecem mesmo assim: a memória de cálculo
    mostra o orçamento inteiro, e não só o que faturou, para o cliente conferir o
    acumulado de cada serviço sem precisar juntar medições.
</p>

<?php if ($medicao->estaFechada()): ?>
    <p class="dica">
        Medição fechada. Corrigir a partir daqui exige medição de acerto na competência
        seguinte — que é como o setor funciona, e não uma limitação do sistema.
    </p>
<?php endif ?>
