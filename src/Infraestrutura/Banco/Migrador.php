<?php

declare(strict_types=1);

namespace GestaoObras\Infraestrutura\Banco;

use PDO;
use RuntimeException;

/**
 * Aplica as migrations em ordem, uma vez cada.
 *
 * O controle é uma tabela no próprio banco: o que já rodou fica registrado, e
 * rodar de novo não repete nada. Cada migration vai dentro de uma transação —
 * ou aplica inteira, ou não aplica.
 */
final class Migrador
{
    public function __construct(
        private readonly PDO $conexao,
        private readonly string $diretorio,
    ) {
    }

    public static function padrao(PDO $conexao): self
    {
        return new self($conexao, dirname(__DIR__, 3) . '/banco/migrations');
    }

    /**
     * @return string[] nomes das migrations aplicadas nesta execução
     */
    public function aplicar(): array
    {
        $this->criarControle();

        $aplicadas = [];

        foreach ($this->arquivos() as $arquivo) {
            $nome = basename($arquivo);

            if ($this->jaAplicada($nome)) {
                continue;
            }

            $sql = file_get_contents($arquivo);

            if ($sql === false) {
                throw new RuntimeException("Não foi possível ler a migration {$nome}.");
            }

            $this->conexao->beginTransaction();

            try {
                $this->conexao->exec($sql);

                $registro = $this->conexao->prepare(
                    'INSERT INTO migrations_aplicadas (nome) VALUES (:nome)'
                );
                $registro->execute([':nome' => $nome]);

                $this->conexao->commit();
            } catch (\Throwable $erro) {
                $this->conexao->rollBack();

                throw new RuntimeException(
                    "Falha ao aplicar a migration {$nome}: {$erro->getMessage()}",
                    0,
                    $erro,
                );
            }

            $aplicadas[] = $nome;
        }

        return $aplicadas;
    }

    /** @return string[] */
    public function pendentes(): array
    {
        $this->criarControle();

        return array_values(array_filter(
            array_map(static fn (string $caminho): string => basename($caminho), $this->arquivos()),
            fn (string $nome): bool => !$this->jaAplicada($nome),
        ));
    }

    /**
     * Ordena pelo nome, e por isso o prefixo numérico importa: 002 precisa
     * rodar depois de 001, senão a chave estrangeira aponta para o vazio.
     *
     * @return string[]
     */
    private function arquivos(): array
    {
        $arquivos = glob($this->diretorio . DIRECTORY_SEPARATOR . '*.sql') ?: [];
        sort($arquivos);

        return $arquivos;
    }

    private function criarControle(): void
    {
        $this->conexao->exec(
            'CREATE TABLE IF NOT EXISTS migrations_aplicadas (
                nome        TEXT NOT NULL PRIMARY KEY,
                aplicada_em TEXT NOT NULL DEFAULT (datetime(\'now\'))
            )'
        );
    }

    private function jaAplicada(string $nome): bool
    {
        $consulta = $this->conexao->prepare(
            'SELECT 1 FROM migrations_aplicadas WHERE nome = :nome'
        );
        $consulta->execute([':nome' => $nome]);

        return $consulta->fetchColumn() !== false;
    }
}
