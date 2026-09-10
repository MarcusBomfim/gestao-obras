<nav class="trilha">
    <a href="/obras">Obras</a> ›
    <a href="/obras/<?= e(rawurlencode($obra->codigo)) ?>"><?= e($obra->codigo) ?></a> ›
    Diários
</nav>

<header class="cabecalho-obra">
    <div>
        <h1 class="titulo">Diários de obra</h1>
        <p class="cabecalho-obra__cliente"><?= e($obra->nome) ?></p>
    </div>

    <div class="cabecalho-obra__acoes">
        <?php if ($obra->situacao()->aceitaExecucao()): ?>
            <a class="botao botao--primario"
               href="/obras/<?= e(rawurlencode($obra->codigo)) ?>/diarios/novo">Novo diário</a>
        <?php else: ?>
            <span class="dica">
                A obra está <?= e(mb_strtolower($obra->situacao()->rotulo())) ?> e não aceita diário.
            </span>
        <?php endif ?>
    </div>
</header>

<?php if ($diarios === []): ?>
    <section class="cartao cartao--vazio">
        <p>Nenhum diário registrado.</p>
        <p class="dica">O primeiro diário abre o acompanhamento diário desta obra.</p>
    </section>
<?php else: ?>
    <div class="rolagem">
        <table class="tabela">
            <thead>
                <tr>
                    <th>RDO</th>
                    <th>Data</th>
                    <th>Clima e condição</th>
                    <th class="numerico">Efetivo</th>
                    <th>Executado</th>
                    <th>Ocorrências</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($diarios as $diario): ?>
                    <tr<?= $diario->ehDiaPerdido() ? ' class="linha--perdida"' : '' ?>>
                        <td class="codigo"><?= e($diario->numero()) ?></td>
                        <td><?= e(dataBr($diario->data)) ?></td>
                        <td class="clima"><?= e($diario->clima->resumo()) ?></td>
                        <td class="numerico">
                            <?= e($diario->efetivo()->total()) ?>
                            <span class="dica">(<?= e($diario->efetivo()->totalDireto()) ?> direto)</span>
                        </td>
                        <td>
                            <?php $totais = $diario->quantidadePorServico(); ?>
                            <?php if ($totais === []): ?>
                                <span class="dica">sem produção</span>
                            <?php else: ?>
                                <ul class="miudos">
                                    <?php foreach ($totais as $servicoCodigo => $quantidade): ?>
                                        <li><?= e($servicoCodigo) ?>: <?= e(numeroBr($quantidade)) ?></li>
                                    <?php endforeach ?>
                                </ul>
                            <?php endif ?>
                        </td>
                        <td>
                            <?php if ($diario->ocorrencias() === []): ?>
                                <span class="dica">—</span>
                            <?php else: ?>
                                <ul class="miudos">
                                    <?php foreach ($diario->ocorrencias() as $ocorrencia): ?>
                                        <li><strong><?= e($ocorrencia->tipo->rotulo()) ?>:</strong>
                                            <?= e($ocorrencia->descricao) ?></li>
                                    <?php endforeach ?>
                                </ul>
                            <?php endif ?>
                        </td>
                        <td>
                            <form method="post"
                                  action="/obras/<?= e(rawurlencode($obra->codigo)) ?>/diarios/<?= e($diario->numero()) ?>/remover"
                                  onsubmit="return confirm('Remover o RDO <?= e($diario->numero()) ?> e estornar as quantidades do orçamento?');">
                                <input type="hidden" name="token" value="<?= e($token) ?>">
                                <button type="submit" class="botao botao--perigo">Remover</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>

    <p class="dica">
        Remover um diário estorna do orçamento tudo o que ele tinha apontado.
        É assim que se corrige um lançamento errado.
    </p>
<?php endif ?>
