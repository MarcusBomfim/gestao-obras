<?php

declare(strict_types=1);

namespace GestaoObras\Dominio\Obra;

/**
 * Situação da obra e as transições permitidas entre elas.
 *
 * As transições ficam aqui, e não espalhadas em ifs pelo sistema, porque é a
 * própria situação que sabe para onde pode ir.
 */
enum SituacaoDaObra: string
{
    case Planejada = 'planejada';
    case EmAndamento = 'em_andamento';
    case Paralisada = 'paralisada';
    case Concluida = 'concluida';

    public function rotulo(): string
    {
        return match ($this) {
            self::Planejada => 'Planejada',
            self::EmAndamento => 'Em andamento',
            self::Paralisada => 'Paralisada',
            self::Concluida => 'Concluída',
        };
    }

    /** @return self[] */
    public function destinosPermitidos(): array
    {
        return match ($this) {
            self::Planejada => [self::EmAndamento],
            self::EmAndamento => [self::Paralisada, self::Concluida],
            self::Paralisada => [self::EmAndamento],
            self::Concluida => [],
        };
    }

    public function podeMudarPara(self $destino): bool
    {
        return in_array($destino, $this->destinosPermitidos(), true);
    }

    /** Uma obra concluída não volta atrás: é o estado final. */
    public function ehFinal(): bool
    {
        return $this->destinosPermitidos() === [];
    }

    /** Só obra em andamento aceita apontamento de execução. */
    public function aceitaExecucao(): bool
    {
        return $this === self::EmAndamento;
    }
}
