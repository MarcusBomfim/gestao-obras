<?php

declare(strict_types=1);

/*
 * Ponto de entrada dos testes.
 *
 *   php testes/executar.php
 *
 * Sai com código 0 quando tudo passa e 1 quando algo falha, para servir de
 * porta na integração contínua.
 *
 * Os testes de infraestrutura sobem um SQLite em memória e aplicam as
 * migrations reais, então não deixam arquivo para trás nem exigem preparo.
 */

require __DIR__ . '/../src/autoload.php';
require __DIR__ . '/Executor.php';
require __DIR__ . '/ajuda.php';

$pastas = ['dominio', 'infraestrutura', 'aplicacao'];
$arquivos = [];

foreach ($pastas as $pasta) {
    $encontrados = glob(__DIR__ . '/' . $pasta . '/*.php') ?: [];
    sort($encontrados);

    $arquivos = [...$arquivos, ...$encontrados];
}

foreach ($arquivos as $arquivo) {
    require $arquivo;
}

exit(resumo());
