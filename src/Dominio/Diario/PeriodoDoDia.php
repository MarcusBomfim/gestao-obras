<?php

declare(strict_types=1);

namespace GestaoObras\Dominio\Diario;

/** Os três períodos que o diário de obra registra separadamente. */
enum PeriodoDoDia: string
{
    case Manha = 'manha';
    case Tarde = 'tarde';
    case Noite = 'noite';

    public function rotulo(): string
    {
        return match ($this) {
            self::Manha => 'Manhã',
            self::Tarde => 'Tarde',
            self::Noite => 'Noite',
        };
    }
}
