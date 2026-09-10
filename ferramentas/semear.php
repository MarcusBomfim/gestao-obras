<?php

declare(strict_types=1);

/*
 * Carrega os dados de demonstração.
 *
 *   php ferramentas/semear.php
 *
 * É idempotente: o SQL usa ON CONFLICT DO NOTHING, então rodar de novo não
 * duplica nada nem apaga apontamento existente.
 */

require __DIR__ . '/../src/autoload.php';

use GestaoObras\Infraestrutura\Banco\Conexao;
use GestaoObras\Infraestrutura\Banco\Migrador;

$conexao = Conexao::abrir();

if (Migrador::padrao($conexao)->pendentes() !== []) {
    fwrite(STDERR, 'Há migrations pendentes. Rode php ferramentas/migrar.php antes.' . PHP_EOL);
    exit(1);
}

$arquivo = __DIR__ . '/../banco/seeds/exemplo.sql';
$sql = file_get_contents($arquivo);

if ($sql === false) {
    fwrite(STDERR, "Não foi possível ler {$arquivo}." . PHP_EOL);
    exit(1);
}

try {
    $conexao->exec($sql);
} catch (Throwable $erro) {
    fwrite(STDERR, 'Erro ao carregar os dados: ' . $erro->getMessage() . PHP_EOL);
    exit(1);
}

$obras = (int) $conexao->query('SELECT COUNT(*) FROM obras')->fetchColumn();
$servicos = (int) $conexao->query('SELECT COUNT(*) FROM servicos')->fetchColumn();

printf('Banco carregado: %d obras e %d serviços.%s', $obras, $servicos, PHP_EOL);
