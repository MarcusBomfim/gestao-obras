<nav class="trilha">
    <a href="/obras">Obras</a> ›
    <a href="/obras/<?= e(rawurlencode($obra->codigo)) ?>"><?= e($obra->codigo) ?></a> ›
    <a href="/obras/<?= e(rawurlencode($obra->codigo)) ?>/diarios">Diários</a> ›
    Novo
</nav>

<h1 class="titulo">Novo diário de obra</h1>
<p class="cabecalho-obra__cliente"><?= e($obra->nome) ?></p>

<form class="formulario" method="post"
      action="/obras/<?= e(rawurlencode($obra->codigo)) ?>/diarios">
    <input type="hidden" name="token" value="<?= e($token) ?>">

    <fieldset>
        <legend>Identificação</legend>

        <div class="campos">
            <label>
                <span>Data</span>
                <input type="date" name="data" value="<?= e($hoje) ?>" max="<?= e($hoje) ?>" required>
            </label>

            <label class="campo--largo">
                <span>Responsável pelo diário</span>
                <input type="text" name="responsavel" maxlength="160"
                       value="<?= e($obra->responsavelTecnico) ?>" required>
            </label>
        </div>
    </fieldset>

    <fieldset>
        <legend>Clima e condição de trabalho</legend>
        <p class="dica">
            Clima e condição são registros separados: chuva fraca pode ser praticável
            para serviço interno e impraticável para concretagem. Dia impraticável nos
            três períodos não aceita serviço executado.
        </p>

        <div class="grade-clima">
            <?php foreach (['manha' => 'Manhã', 'tarde' => 'Tarde', 'noite' => 'Noite'] as $chave => $rotulo): ?>
                <div class="periodo">
                    <h3><?= e($rotulo) ?></h3>

                    <label>
                        <span>Tempo</span>
                        <select name="clima_<?= e($chave) ?>">
                            <?php foreach ($climas as $clima): ?>
                                <option value="<?= e($clima->value) ?>"><?= e($clima->rotulo()) ?></option>
                            <?php endforeach ?>
                        </select>
                    </label>

                    <label>
                        <span>Condição</span>
                        <select name="condicao_<?= e($chave) ?>">
                            <option value="praticavel">Praticável</option>
                            <option value="impraticavel">Impraticável</option>
                        </select>
                    </label>
                </div>
            <?php endforeach ?>
        </div>
    </fieldset>

    <fieldset>
        <legend>Efetivo presente</legend>
        <p class="dica">Deixe em branco ou zero as funções que não estiveram no canteiro.</p>

        <div class="grade-efetivo">
            <?php foreach ($funcoes as $funcao): ?>
                <label>
                    <span><?= e($funcao->rotulo()) ?><?= $funcao->ehIndireta() ? ' *' : '' ?></span>
                    <input type="number" min="0" max="999" step="1"
                           name="efetivo_<?= e($funcao->value) ?>" placeholder="0">
                </label>
            <?php endforeach ?>
        </div>

        <p class="dica">* mão de obra indireta, não contabilizada como produção.</p>
    </fieldset>

    <fieldset>
        <legend>Serviços executados</legend>

        <?php if ($servicos === []): ?>
            <p class="dica">Esta obra não tem serviços no orçamento, então não há o que apontar.</p>
        <?php else: ?>
            <p class="dica">
                A quantidade apontada aqui entra direto no avanço do serviço. Nenhum
                percentual é digitado à mão em lugar nenhum do sistema.
            </p>

            <div class="rolagem">
                <table class="tabela tabela--formulario">
                    <thead>
                        <tr>
                            <th>Serviço</th>
                            <th class="numerico">Quantidade</th>
                            <th>Observação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for ($linha = 0; $linha < 5; $linha++): ?>
                            <tr>
                                <td>
                                    <select name="atividades[<?= e($linha) ?>][servico]">
                                        <option value="">—</option>
                                        <?php foreach ($servicos as $servico): ?>
                                            <?php if ($servico->estaConcluido()) { continue; } ?>
                                            <option value="<?= e($servico->codigo) ?>">
                                                <?= e($servico->codigo) ?> —
                                                <?= e($servico->descricao) ?>
                                                (saldo <?= e($servico->unidade->formatar($servico->saldo())) ?>)
                                            </option>
                                        <?php endforeach ?>
                                    </select>
                                </td>
                                <td>
                                    <input type="text" inputmode="decimal"
                                           name="atividades[<?= e($linha) ?>][quantidade]"
                                           placeholder="0,00">
                                </td>
                                <td>
                                    <input type="text" maxlength="500"
                                           name="atividades[<?= e($linha) ?>][observacao]"
                                           placeholder="Frente de serviço, pavimento, trecho…">
                                </td>
                            </tr>
                        <?php endfor ?>
                    </tbody>
                </table>
            </div>
        <?php endif ?>
    </fieldset>

    <fieldset>
        <legend>Ocorrências</legend>
        <p class="dica">
            Acidente e paralisação exigem relato de ao menos 30 caracteres: o que houve,
            onde e quais providências foram tomadas.
        </p>

        <?php for ($linha = 0; $linha < 2; $linha++): ?>
            <div class="campos">
                <label>
                    <span>Tipo</span>
                    <select name="ocorrencias[<?= e($linha) ?>][tipo]">
                        <?php foreach ($tiposDeOcorrencia as $tipo): ?>
                            <option value="<?= e($tipo->value) ?>"><?= e($tipo->rotulo()) ?></option>
                        <?php endforeach ?>
                    </select>
                </label>

                <label class="campo--largo">
                    <span>Descrição</span>
                    <input type="text" maxlength="1000"
                           name="ocorrencias[<?= e($linha) ?>][descricao]"
                           placeholder="Deixe em branco se não houve ocorrência">
                </label>
            </div>
        <?php endfor ?>
    </fieldset>

    <div class="acoes-formulario">
        <button type="submit" class="botao botao--primario">Registrar diário</button>
        <a class="botao" href="/obras/<?= e(rawurlencode($obra->codigo)) ?>/diarios">Cancelar</a>
    </div>

    <p class="dica">
        Um diário por obra por dia. Se já existir diário para a data escolhida, o
        registro é recusado — para corrigir, remova o existente e registre de novo.
    </p>
</form>
