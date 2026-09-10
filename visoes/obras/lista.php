<h1 class="titulo">Obras</h1>

<?php if ($resumos === []): ?>
    <section class="cartao cartao--vazio">
        <p>Nenhuma obra cadastrada ainda.</p>
        <p class="dica">
            Carregue os dados de demonstração com
            <code>php ferramentas/semear.php</code> e recarregue esta página.
        </p>
    </section>
<?php else: ?>
    <div class="grade-obras">
        <?php foreach ($resumos as $resumo): ?>
            <?php $obra = $resumo->obra; $atraso = $obra->diasDeAtraso($hoje); ?>
            <article class="cartao cartao--obra">
                <div class="cartao__topo">
                    <span class="codigo"><?= e($obra->codigo) ?></span>
                    <span class="etiqueta etiqueta--<?= e($obra->situacao()->value) ?>">
                        <?= e($obra->situacao()->rotulo()) ?>
                    </span>
                </div>

                <h2><a href="/obras/<?= e(rawurlencode($obra->codigo)) ?>"><?= e($obra->nome) ?></a></h2>
                <p class="cartao__cliente"><?= e($obra->cliente) ?></p>
                <p class="cartao__local"><?= e($obra->endereco->cidade) ?>/<?= e($obra->endereco->uf) ?></p>

                <div class="avanco">
                    <div class="avanco__trilho">
                        <div class="avanco__barra" style="width: <?= e(barraDeAvanco($resumo->percentualFisico())) ?>%"></div>
                    </div>
                    <span class="avanco__numero"><?= e(numeroBr($resumo->percentualFisico())) ?>%</span>
                </div>

                <dl class="fatos">
                    <div>
                        <dt>Previsto</dt>
                        <dd><?= e(reais($resumo->valorPrevisto())) ?></dd>
                    </div>
                    <div>
                        <dt>Executado</dt>
                        <dd><?= e(reais($resumo->valorExecutado())) ?></dd>
                    </div>
                    <div>
                        <dt>Serviços</dt>
                        <dd><?= e($resumo->servicosConcluidos()) ?>/<?= e($resumo->totalDeServicos()) ?> concluídos</dd>
                    </div>
                    <div>
                        <dt>Prazo</dt>
                        <dd>
                            <?php if ($atraso > 0): ?>
                                <span class="atraso"><?= e($atraso) ?> dia<?= $atraso > 1 ? 's' : '' ?> de atraso</span>
                            <?php else: ?>
                                até <?= e(dataBr($obra->dataPrevistaDeTermino())) ?>
                            <?php endif ?>
                        </dd>
                    </div>
                </dl>
            </article>
        <?php endforeach ?>
    </div>
<?php endif ?>
