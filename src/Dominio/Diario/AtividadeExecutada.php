<?php

declare(strict_types=1);

namespace GestaoObras\Dominio\Diario;

use GestaoObras\Dominio\Regras;

/**
 * Um serviço do orçamento e quanto dele foi executado naquele dia.
 *
 * É a linha que liga o diário à medição: em vez de alguém digitar "obra está
 * em 40%", o percentual passa a ser a soma do que foi apontado dia a dia.
 */
final class AtividadeExecutada
{
    public readonly string $servicoCodigo;
    public readonly float $quantidade;
    public readonly ?string $observacao;

    public function __construct(string $servicoCodigo, float $quantidade, ?string $observacao = null)
    {
        $this->servicoCodigo = strtoupper(
            Regras::textoObrigatorio($servicoCodigo, 'Código do serviço', 20)
        );
        $this->quantidade = Regras::numeroPositivo($quantidade, 'Quantidade executada');

        $limpa = $observacao === null ? '' : trim($observacao);
        $this->observacao = $limpa === ''
            ? null
            : Regras::textoObrigatorio($limpa, 'Observação da atividade', 500);
    }
}
