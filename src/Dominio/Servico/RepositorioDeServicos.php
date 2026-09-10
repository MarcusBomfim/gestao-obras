<?php

declare(strict_types=1);

namespace GestaoObras\Dominio\Servico;

/**
 * O serviço não guarda a qual obra pertence: essa ligação é responsabilidade
 * de quem persiste, e por isso o código da obra viaja como parâmetro. Mantém a
 * entidade enxuta e o vínculo explícito em cada chamada.
 */
interface RepositorioDeServicos
{
    public function salvar(string $obraCodigo, Servico $servico): void;

    /** @return Servico[] ordenados por código */
    public function daObra(string $obraCodigo): array;

    public function porCodigo(string $obraCodigo, string $codigo): ?Servico;

    public function remover(string $obraCodigo, string $codigo): void;
}
