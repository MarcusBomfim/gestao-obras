<?php

declare(strict_types=1);

namespace GestaoObras\Dominio\Diario;

use GestaoObras\Dominio\ExcecaoDeDominio;
use GestaoObras\Dominio\Regras;

/** Um fato do dia que não é produção: acidente, visita, paralisação, entrega. */
final class Ocorrencia
{
    /** Descrição mínima para os tipos graves, em caracteres. */
    private const DETALHE_MINIMO = 30;

    public readonly TipoDeOcorrencia $tipo;
    public readonly string $descricao;

    public function __construct(TipoDeOcorrencia $tipo, string $descricao)
    {
        $this->tipo = $tipo;
        $this->descricao = Regras::textoObrigatorio($descricao, 'Descrição da ocorrência', 1000);

        if ($tipo->exigeDescricaoDetalhada() && mb_strlen($this->descricao) < self::DETALHE_MINIMO) {
            throw new ExcecaoDeDominio(sprintf(
                'Ocorrência do tipo "%s" precisa de ao menos %d caracteres de descrição. '
                . 'Registre o que houve, onde e quais providências foram tomadas.',
                $tipo->rotulo(),
                self::DETALHE_MINIMO,
            ));
        }
    }
}
