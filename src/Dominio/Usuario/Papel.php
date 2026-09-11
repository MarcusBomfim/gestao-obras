<?php

declare(strict_types=1);

namespace GestaoObras\Dominio\Usuario;

/**
 * Quem faz o quê no sistema.
 *
 * Os três papéis espelham quem de fato circula numa obra: o engenheiro
 * responde pelo contrato, o mestre de obras responde pelo canteiro, e o
 * cliente acompanha sem mexer. As permissões ficam aqui, num lugar só, e não
 * espalhadas em ifs pelos controladores.
 */
enum Papel: string
{
    case Engenheiro = 'engenheiro';
    case MestreDeObras = 'mestre_de_obras';
    case Cliente = 'cliente';

    public function rotulo(): string
    {
        return match ($this) {
            self::Engenheiro => 'Engenheiro',
            self::MestreDeObras => 'Mestre de obras',
            self::Cliente => 'Cliente',
        };
    }

    /** Registrar e remover diário: quem está no canteiro. */
    public function podeApontarDiario(): bool
    {
        return $this !== self::Cliente;
    }

    /**
     * Gerar e fechar medição: só o engenheiro. Medição é base de fatura, e
     * fatura é responsabilidade contratual — não é decisão de canteiro.
     */
    public function podeMedir(): bool
    {
        return $this === self::Engenheiro;
    }

    /** Todo mundo lê. O cliente existe justamente para acompanhar. */
    public function podeConsultar(): bool
    {
        return true;
    }

    public function descricaoDasPermissoes(): string
    {
        return match ($this) {
            self::Engenheiro => 'Acesso completo: diários, medições e fechamento de competência.',
            self::MestreDeObras => 'Registra e corrige diários. Não gera nem fecha medição.',
            self::Cliente => 'Somente leitura: acompanha obras, diários e medições.',
        };
    }
}
