<?php

declare(strict_types=1);

namespace GestaoObras\Infraestrutura\Banco;

use PDO;

/**
 * Fábrica de conexões PDO com SQLite.
 *
 * SQLite foi escolhido para o projeto rodar sem servidor de banco: quem clonar
 * consegue subir tudo com o PHP e mais nada. O código fala PDO, então trocar
 * por MySQL ou PostgreSQL depois mexe só nesta classe e no SQL das migrations.
 */
final class Conexao
{
    private function __construct()
    {
    }

    /** Caminho padrão do banco de trabalho. */
    public static function caminhoPadrao(): string
    {
        return dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'banco'
            . DIRECTORY_SEPARATOR . 'gestao-obras.sqlite';
    }

    public static function abrir(?string $caminho = null): PDO
    {
        return self::configurar(new PDO('sqlite:' . ($caminho ?? self::caminhoPadrao())));
    }

    /** Banco descartável em memória, usado pelos testes. */
    public static function emMemoria(): PDO
    {
        return self::configurar(new PDO('sqlite::memory:'));
    }

    private static function configurar(PDO $pdo): PDO
    {
        // Sem ERRMODE_EXCEPTION, um INSERT que falha devolve false em silêncio.
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

        /*
         * O SQLite ignora chave estrangeira por padrão, por compatibilidade com
         * versões antigas. Sem esta linha, o ON DELETE CASCADE dos serviços
         * simplesmente não acontece — e nada avisa.
         */
        $pdo->exec('PRAGMA foreign_keys = ON');

        // Melhor concorrência de leitura enquanto alguém escreve.
        $pdo->exec('PRAGMA journal_mode = WAL');

        return $pdo;
    }
}
