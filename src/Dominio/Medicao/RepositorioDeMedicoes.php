<?php

declare(strict_types=1);

namespace GestaoObras\Dominio\Medicao;

interface RepositorioDeMedicoes
{
    /** Grava a medição com seus itens e devolve o número sequencial. */
    public function salvar(Medicao $medicao): int;

    public function porNumero(string $obraCodigo, int $numero): ?Medicao;

    /** @return Medicao[] da mais recente para a mais antiga */
    public function daObra(string $obraCodigo): array;

    public function ultima(string $obraCodigo): ?Medicao;

    /** Fecha a medição no banco, sem reescrever os itens. */
    public function fechar(string $obraCodigo, int $numero): void;

    public function remover(string $obraCodigo, int $numero): void;
}
