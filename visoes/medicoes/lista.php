<nav class="trilha">
    <a href="/obras">Obras</a> ›
    <a href="/obras/<?= e(rawurlencode($obra->codigo)) ?>"><?= e($obra->codigo) ?></a> ›
    Medições
</nav>

<header class="cabecalho-obra">
    <div>
        <h1 class="titulo">Medições</h1>
        <p class="cabecalho-obra__cliente"><?= e($obra->nome) ?></p>
    </div>
</header>

<?php if ($usuarioAtual?->papel->podeMedir() ?? false): ?>
<section class="cartao" style="margin-top:1.25rem">
    <h2 class="subtitulo" style="margin-top:0">Gerar medição do período</h2>
    <p class="dica">
        A medição soma o que os diários apontaram no período. Ela não lê o acumulado
        do serviço: refaz a conta a partir dos diários, e por isso cada linha da
        memória de cálculo é rastreável até o dia que a originou.
    </p>

    <form class="campos" method="post"
          action="/obras/<?= e(rawurlencode($obra->codigo)) ?>/medicoes">
        <input type="hidden" name="token" value="<?= e($token) ?>">

        <label>
            <span>Início</span>
            <input type="date" name="inicio" value="<?= e($proximoInicio) ?>"
                   max="<?= e($hoje) ?>" required>
        </label>

        <label>
            <span>Fim</span>
            <input type="date" name="fim" value="<?= e($hoje) ?>" max="<?= e($hoje) ?>" required>
        </label>

        <label>
            <span>&nbsp;</span>
            <button type="submit" class="botao botao--primario">Gerar medição</button>
        </label>
    </form>

    <p class="dica">
        Medições são consecutivas: a próxima começa no dia seguinte ao fim da anterior,
        sem buraco e sem sobreposição.
    </p>
</section>
<?php else: ?>
<p class="dica" style="margin-top:1rem">
    Gerar e fechar medição é atribuição do engenheiro responsável. Você pode consultar
    a memória de cálculo de cada uma.
</p>
<?php endif ?>

<h2 class="subtitulo">Medições da obra</h2>

<?php if ($medicoes === []): ?>
    <section class="cartao cartao--vazio">
        <p>Nenhuma medição gerada ainda.</p>
    </section>
<?php else: ?>
    <div class="rolagem">
        <table class="tabela">
            <thead>
                <tr>
                    <th>Nº</th>
                    <th>Período</th>
                    <th class="numerico">Dias</th>
                    <th class="numerico">No período</th>
                    <th class="numerico">Acumulado</th>
                    <th class="numerico">% da obra</th>
                    <th>Situação</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($medicoes as $medicao): ?>
                    <tr>
                        <td class="codigo"><?= e($medicao->numero()) ?></td>
                        <td><?= e(dataBr($medicao->inicio)) ?> a <?= e(dataBr($medicao->fim)) ?></td>
                        <td class="numerico"><?= e($medicao->diasDoPeriodo()) ?></td>
                        <td class="numerico"><?= e(reais($medicao->valorNoPeriodo())) ?></td>
                        <td class="numerico"><?= e(reais($medicao->valorAcumulado())) ?></td>
                        <td class="numerico"><?= e(numeroBr($medicao->percentualAcumulado())) ?>%</td>
                        <td>
                            <span class="etiqueta etiqueta--<?= $medicao->estaFechada() ? 'concluida' : 'planejada' ?>">
                                <?= e($medicao->situacao()->rotulo()) ?>
                            </span>
                        </td>
                        <td>
                            <a class="botao"
                               href="/obras/<?= e(rawurlencode($obra->codigo)) ?>/medicoes/<?= e($medicao->numero()) ?>">
                                Memória
                            </a>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>
