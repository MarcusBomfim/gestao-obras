<?php

declare(strict_types=1);

namespace GestaoObras\Dominio\Diario;

use DateTimeImmutable;

interface RepositorioDeDiarios
{
    /**
     * Grava o diário e devolve o número sequencial atribuído a ele.
     *
     * Diário já gravado não é reescrito por aqui: documento contratual não se
     * altera em silêncio. Corrigir passa por remover e registrar de novo, com
     * o estorno das quantidades — que é o que o caso de uso faz.
     */
    public function salvar(DiarioDeObra $diario): int;

    public function porData(string $obraCodigo, DateTimeImmutable $data): ?DiarioDeObra;

    public function porNumero(string $obraCodigo, int $numero): ?DiarioDeObra;

    /** @return DiarioDeObra[] do mais recente para o mais antigo */
    public function daObra(string $obraCodigo): array;

    /** @return DiarioDeObra[] dentro do período, incluindo as duas pontas */
    public function noPeriodo(
        string $obraCodigo,
        DateTimeImmutable $inicio,
        DateTimeImmutable $fim,
    ): array;

    public function existeParaData(string $obraCodigo, DateTimeImmutable $data): bool;

    /** Quantos dias do período foram registrados como impraticáveis. */
    public function diasImpraticaveis(
        string $obraCodigo,
        DateTimeImmutable $inicio,
        DateTimeImmutable $fim,
    ): int;

    public function remover(string $obraCodigo, int $numero): void;
}
