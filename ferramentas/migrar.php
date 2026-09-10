<?php

declare(strict_types=1);

/*
 * Aplica as migrations pendentes no banco de trabalho.
 *
 *   php ferramentas/migrar.php
 *
 * Rodar duas vezes não repete nada: o que já foi aplicado fica registrado na
 * tabela migrations_aplicadas.
 */

require __DIR__ . '/../src/autoload.php';

use GestaoObras\Infraestrutura\Banco\Conexao;
use GestaoObras\Infraestrutura\Banco\Migrador;

$caminho = Conexao::caminhoPadrao();
$conexao = Conexao::abrir($caminho);
$migrador = Migrador::padrao($conexao);

$pendentes = $migrador->pendentes();

if ($pendentes === []) {
    echo 'Nada a aplicar: o banco já está atualizado.', PHP_EOL;
    exit(0);
}

echo 'Banco: ', $caminho, PHP_EOL;

try {
    foreach ($migrador->aplicar() as $nome) {
        echo '  aplicada  ', $nome, PHP_EOL;
    }
} catch (Throwable $erro) {
    fwrite(STDERR, 'Erro: ' . $erro->getMessage() . PHP_EOL);
    exit(1);
}

echo PHP_EOL, 'Pronto.', PHP_EOL;
