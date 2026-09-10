<?php

declare(strict_types=1);

namespace GestaoObras\Dominio\Diario;

/**
 * Se deu para trabalhar no período.
 *
 * É registro separado do clima de propósito: chuva fraca pode ser praticável
 * para serviço interno e impraticável para concretagem. Quem está no canteiro
 * é que julga, e é esse julgamento que sustenta pedido de prorrogação de prazo.
 */
enum CondicaoDeTrabalho: string
{
    case Praticavel = 'praticavel';
    case Impraticavel = 'impraticavel';

    public function rotulo(): string
    {
        return match ($this) {
            self::Praticavel => 'Praticável',
            self::Impraticavel => 'Impraticável',
        };
    }

    public function permiteTrabalho(): bool
    {
        return $this === self::Praticavel;
    }
}
