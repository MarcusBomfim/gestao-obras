<?php

declare(strict_types=1);

/*
 * Ponto de entrada dos testes.
 *
 *   php testes/executar.php
 *
 * Sai com código 0 quando tudo passa e 1 quando algo falha, para servir de
 * porta na integração contínua.
 */

require __DIR__ . '/../src/autoload.php';
require __DIR__ . '/Executor.php';

$arquivos = glob(__DIR__ . '/dominio/*.php') ?: [];
sort($arquivos);

foreach ($arquivos as $arquivo) {
    require $arquivo;
}

exit(resumo());
