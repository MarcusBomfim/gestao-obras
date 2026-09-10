<?php

declare(strict_types=1);

namespace GestaoObras\Dominio\Obra;

/**
 * A interface fica no domínio, e quem implementa fica na infraestrutura.
 *
 * Assim o domínio não sabe que existe SQLite: ele pede uma obra pelo código e
 * recebe uma Obra. Trocar o banco, ou usar uma implementação em memória nos
 * testes, não encosta em nenhuma regra de negócio.
 */
interface RepositorioDeObras
{
    /** Grava a obra, criando ou atualizando conforme o código já exista. */
    public function salvar(Obra $obra): void;

    public function porCodigo(string $codigo): ?Obra;

    /** @return Obra[] ordenadas por código */
    public function todas(): array;

    /** @return Obra[] */
    public function porSituacao(SituacaoDaObra $situacao): array;

    public function existe(string $codigo): bool;

    /** Remove a obra e, em cascata, os serviços dela. */
    public function remover(string $codigo): void;
}
