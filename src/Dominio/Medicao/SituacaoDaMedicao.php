<?php

declare(strict_types=1);

namespace GestaoObras\Dominio\Medicao;

/**
 * Medição aberta ainda pode ser revista; fechada é documento.
 *
 * Fechar é o ato que transforma o levantamento em base de fatura. Depois
 * disso, corrigir exige medição de acerto na competência seguinte — que é
 * como o setor funciona, e não uma limitação do sistema.
 */
enum SituacaoDaMedicao: string
{
    case Aberta = 'aberta';
    case Fechada = 'fechada';

    public function rotulo(): string
    {
        return match ($this) {
            self::Aberta => 'Aberta',
            self::Fechada => 'Fechada',
        };
    }

    public function permiteAlteracao(): bool
    {
        return $this === self::Aberta;
    }
}
