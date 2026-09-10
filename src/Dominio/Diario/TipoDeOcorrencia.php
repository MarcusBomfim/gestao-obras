<?php

declare(strict_types=1);

namespace GestaoObras\Dominio\Diario;

/** Categorias do que se registra no campo de ocorrências do diário. */
enum TipoDeOcorrencia: string
{
    case Acidente = 'acidente';
    case Paralisacao = 'paralisacao';
    case Visita = 'visita';
    case Inspecao = 'inspecao';
    case EntregaDeMaterial = 'entrega_material';
    case Outro = 'outro';

    public function rotulo(): string
    {
        return match ($this) {
            self::Acidente => 'Acidente',
            self::Paralisacao => 'Paralisação',
            self::Visita => 'Visita',
            self::Inspecao => 'Inspeção',
            self::EntregaDeMaterial => 'Entrega de material',
            self::Outro => 'Outro',
        };
    }

    /**
     * Ocorrências graves exigem descrição mais completa: são elas que viram
     * documento em fiscalização e em processo trabalhista.
     */
    public function exigeDescricaoDetalhada(): bool
    {
        return match ($this) {
            self::Acidente, self::Paralisacao => true,
            default => false,
        };
    }
}
