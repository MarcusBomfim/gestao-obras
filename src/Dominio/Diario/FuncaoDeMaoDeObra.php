<?php

declare(strict_types=1);

namespace GestaoObras\Dominio\Diario;

/**
 * Funções apontadas no efetivo diário.
 *
 * A lista é fechada de propósito: efetivo digitado em texto livre vira
 * "pedreiro", "Pedreiro", "pedrero" e nenhuma consulta fecha no fim do mês.
 */
enum FuncaoDeMaoDeObra: string
{
    case Engenheiro = 'engenheiro';
    case Encarregado = 'encarregado';
    case TecnicoDeSeguranca = 'tecnico_seguranca';
    case Pedreiro = 'pedreiro';
    case Servente = 'servente';
    case Carpinteiro = 'carpinteiro';
    case Armador = 'armador';
    case Eletricista = 'eletricista';
    case Encanador = 'encanador';
    case Pintor = 'pintor';
    case OperadorDeEquipamento = 'operador_equipamento';

    public function rotulo(): string
    {
        return match ($this) {
            self::Engenheiro => 'Engenheiro',
            self::Encarregado => 'Encarregado',
            self::TecnicoDeSeguranca => 'Técnico de segurança',
            self::Pedreiro => 'Pedreiro',
            self::Servente => 'Servente',
            self::Carpinteiro => 'Carpinteiro',
            self::Armador => 'Armador',
            self::Eletricista => 'Eletricista',
            self::Encanador => 'Encanador',
            self::Pintor => 'Pintor',
            self::OperadorDeEquipamento => 'Operador de equipamento',
        };
    }

    /** Funções de supervisão não contam como mão de obra direta na produção. */
    public function ehIndireta(): bool
    {
        return match ($this) {
            self::Engenheiro, self::Encarregado, self::TecnicoDeSeguranca => true,
            default => false,
        };
    }
}
