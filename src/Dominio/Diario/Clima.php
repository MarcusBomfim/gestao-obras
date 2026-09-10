<?php

declare(strict_types=1);

namespace GestaoObras\Dominio\Diario;

/** Tempo observado em um período do dia, como se anota no diário. */
enum Clima: string
{
    case Bom = 'bom';
    case Nublado = 'nublado';
    case Chuvoso = 'chuvoso';

    public function rotulo(): string
    {
        return match ($this) {
            self::Bom => 'Bom',
            self::Nublado => 'Nublado',
            self::Chuvoso => 'Chuvoso',
        };
    }
}
