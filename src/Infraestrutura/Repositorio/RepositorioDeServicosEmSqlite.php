<?php

declare(strict_types=1);

namespace GestaoObras\Infraestrutura\Repositorio;

use GestaoObras\Dominio\Servico\RepositorioDeServicos;
use GestaoObras\Dominio\Servico\Servico;
use GestaoObras\Dominio\Servico\Unidade;
use PDO;

final class RepositorioDeServicosEmSqlite implements RepositorioDeServicos
{
    private const COLUNAS = 'obra_codigo, codigo, descricao, unidade,
        quantidade_prevista, preco_unitario, quantidade_executada';

    public function __construct(private readonly PDO $conexao)
    {
    }

    public function salvar(string $obraCodigo, Servico $servico): void
    {
        $comando = $this->conexao->prepare(
            'INSERT INTO servicos (' . self::COLUNAS . ')
             VALUES (:obra_codigo, :codigo, :descricao, :unidade,
                     :quantidade_prevista, :preco_unitario, :quantidade_executada)
             ON CONFLICT (obra_codigo, codigo) DO UPDATE SET
                descricao            = excluded.descricao,
                unidade              = excluded.unidade,
                quantidade_prevista  = excluded.quantidade_prevista,
                preco_unitario       = excluded.preco_unitario,
                quantidade_executada = excluded.quantidade_executada,
                atualizado_em        = datetime(\'now\')'
        );

        $comando->execute([
            ':obra_codigo' => self::normalizar($obraCodigo),
            ':codigo' => $servico->codigo,
            ':descricao' => $servico->descricao,
            ':unidade' => $servico->unidade->value,
            ':quantidade_prevista' => $servico->quantidadePrevista,
            ':preco_unitario' => $servico->precoUnitario,
            ':quantidade_executada' => $servico->quantidadeExecutada(),
        ]);
    }

    public function daObra(string $obraCodigo): array
    {
        $consulta = $this->conexao->prepare(
            'SELECT ' . self::COLUNAS . ' FROM servicos
             WHERE obra_codigo = :obra_codigo ORDER BY codigo'
        );
        $consulta->execute([':obra_codigo' => self::normalizar($obraCodigo)]);

        return array_map(self::montar(...), $consulta->fetchAll());
    }

    public function porCodigo(string $obraCodigo, string $codigo): ?Servico
    {
        $consulta = $this->conexao->prepare(
            'SELECT ' . self::COLUNAS . ' FROM servicos
             WHERE obra_codigo = :obra_codigo AND codigo = :codigo'
        );
        $consulta->execute([
            ':obra_codigo' => self::normalizar($obraCodigo),
            ':codigo' => self::normalizar($codigo),
        ]);

        $linha = $consulta->fetch();

        return $linha === false ? null : self::montar($linha);
    }

    public function remover(string $obraCodigo, string $codigo): void
    {
        $comando = $this->conexao->prepare(
            'DELETE FROM servicos WHERE obra_codigo = :obra_codigo AND codigo = :codigo'
        );
        $comando->execute([
            ':obra_codigo' => self::normalizar($obraCodigo),
            ':codigo' => self::normalizar($codigo),
        ]);
    }

    /** Os códigos são guardados em maiúsculas pelas entidades; a busca acompanha. */
    private static function normalizar(string $codigo): string
    {
        return strtoupper(trim($codigo));
    }

    /** @param array<string, mixed> $linha */
    private static function montar(array $linha): Servico
    {
        return Servico::reconstituir(
            (string) $linha['codigo'],
            (string) $linha['descricao'],
            Unidade::from((string) $linha['unidade']),
            (float) $linha['quantidade_prevista'],
            (float) $linha['preco_unitario'],
            (float) $linha['quantidade_executada'],
        );
    }
}
