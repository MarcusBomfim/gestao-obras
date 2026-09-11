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

use GestaoObras\Dominio\Usuario\Papel;
use GestaoObras\Dominio\Usuario\Usuario;
use GestaoObras\Infraestrutura\Banco\Conexao;
use GestaoObras\Infraestrutura\Banco\Migrador;
use GestaoObras\Infraestrutura\Repositorio\RepositorioDeUsuariosEmSqlite;

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

/*
 * As contas ficam fora do SQL de propósito: o hash da senha precisa ser gerado
 * pelo password_hash() do PHP, com sal aleatório, e não copiado de um arquivo.
 * Conta que já existe não é sobrescrita — se você trocou a senha, ela fica.
 */
$usuarios = new RepositorioDeUsuariosEmSqlite($conexao);

$contas = [
    ['engenheiro@obras.dev', 'Marcus Bomfim', Papel::Engenheiro, 'Engenheiro@123'],
    ['mestre@obras.dev', 'Helena Duarte', Papel::MestreDeObras, 'Mestre@123'],
    ['cliente@obras.dev', 'Rafael Nunes', Papel::Cliente, 'Cliente@123'],
];

$criadas = 0;

foreach ($contas as [$email, $nome, $papel, $senha]) {
    if ($usuarios->existe($email)) {
        continue;
    }

    $usuarios->salvar(Usuario::criar($email, $nome, $papel, $senha));
    $criadas++;
}

$obras = (int) $conexao->query('SELECT COUNT(*) FROM obras')->fetchColumn();
$servicos = (int) $conexao->query('SELECT COUNT(*) FROM servicos')->fetchColumn();

printf(
    'Banco carregado: %d obras, %d serviços e %d conta(s) nova(s).%s',
    $obras,
    $servicos,
    $criadas,
    PHP_EOL,
);
