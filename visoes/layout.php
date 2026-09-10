<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(($titulo ?? '') !== '' ? $titulo . ' — Gestão de Obras' : 'Gestão de Obras') ?></title>
    <link rel="stylesheet" href="/estilo.css">
</head>
<body>
    <header class="topo">
        <a class="topo__marca" href="/obras">
            <span class="topo__sigla">GO</span>
            <span>Gestão de Obras</span>
        </a>
        <p class="topo__legenda">Diário de obra, avanço físico e medição</p>
    </header>

    <main class="pagina">
        <?php if (($mensagem ?? null) !== null): ?>
            <p class="aviso aviso--ok" role="status"><?= e($mensagem) ?></p>
        <?php endif ?>

        <?php if (($erro ?? null) !== null): ?>
            <p class="aviso aviso--erro" role="alert"><?= e($erro) ?></p>
        <?php endif ?>

        <?php
        /*
         * Sem escape aqui, e de propósito: $conteudo é o template interno já
         * renderizado, onde cada valor passou por e(). Escapar de novo
         * mostraria as tags como texto.
         */
        echo $conteudo;
        ?>
    </main>

    <footer class="rodape">
        <p>Sistema de acompanhamento de obras · PHP <?= e(PHP_VERSION) ?></p>
    </footer>
</body>
</html>
