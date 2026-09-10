<?php

declare(strict_types=1);

namespace GestaoObras\Dominio\Servico;

use GestaoObras\Dominio\ExcecaoDeDominio;
use GestaoObras\Dominio\Regras;

/**
 * Um item do orçamento da obra: "alvenaria de vedação, 320 m², R$ 78,50/m²".
 *
 * O serviço acumula o que já foi executado. A regra central é que o acumulado
 * não passa do previsto — estourar a quantidade contratada exige aditivo, que
 * é decisão comercial e não apontamento de campo.
 */
final class Servico
{
    public readonly string $codigo;
    public readonly string $descricao;
    public readonly Unidade $unidade;
    public readonly float $quantidadePrevista;
    public readonly float $precoUnitario;

    private float $quantidadeExecutada = 0.0;

    public function __construct(
        string $codigo,
        string $descricao,
        Unidade $unidade,
        float $quantidadePrevista,
        float $precoUnitario,
    ) {
        $this->codigo = strtoupper(Regras::textoObrigatorio($codigo, 'Código do serviço', 20));
        $this->descricao = Regras::textoObrigatorio($descricao, 'Descrição do serviço', 200);
        $this->unidade = $unidade;
        $this->quantidadePrevista = self::validarQuantidade(
            Regras::numeroPositivo($quantidadePrevista, 'Quantidade prevista'),
            $unidade,
        );
        $this->precoUnitario = Regras::numeroPositivo($precoUnitario, 'Preço unitário');
    }

    /**
     * Recria um serviço vindo do banco, com o que já estava apontado.
     *
     * Confere a consistência em vez de confiar: se o acumulado gravado passou
     * do previsto, alguém escreveu direto no banco contornando a regra, e é
     * melhor descobrir aqui do que propagar o número errado para a medição.
     */
    public static function reconstituir(
        string $codigo,
        string $descricao,
        Unidade $unidade,
        float $quantidadePrevista,
        float $precoUnitario,
        float $quantidadeExecutada,
    ): self {
        $servico = new self($codigo, $descricao, $unidade, $quantidadePrevista, $precoUnitario);

        Regras::naoNegativo($quantidadeExecutada, 'Quantidade executada');

        if (Regras::maiorQue($quantidadeExecutada, $quantidadePrevista)) {
            throw new ExcecaoDeDominio(sprintf(
                'O serviço %s está com %s apontado, acima do previsto de %s.',
                $servico->codigo,
                $unidade->formatar($quantidadeExecutada),
                $unidade->formatar($quantidadePrevista),
            ));
        }

        $servico->quantidadeExecutada = $quantidadeExecutada;

        return $servico;
    }

    public function quantidadeExecutada(): float
    {
        return $this->quantidadeExecutada;
    }

    /**
     * Aponta execução de campo. Recusa o que passar do previsto, e informa
     * quanto ainda cabe — a mensagem precisa dizer o que fazer, não só que
     * deu errado.
     */
    public function registrarExecucao(float $quantidade): void
    {
        self::validarQuantidade(Regras::numeroPositivo($quantidade, 'Quantidade executada'), $this->unidade);

        $novoAcumulado = $this->quantidadeExecutada + $quantidade;

        if (Regras::maiorQue($novoAcumulado, $this->quantidadePrevista)) {
            throw new ExcecaoDeDominio(sprintf(
                'O serviço %s tem apenas %s de saldo, e foram apontados %s. '
                . 'Para ultrapassar o previsto é preciso um aditivo.',
                $this->codigo,
                $this->unidade->formatar($this->saldo()),
                $this->unidade->formatar($quantidade),
            ));
        }

        $this->quantidadeExecutada = $novoAcumulado;
    }

    /** Desfaz um apontamento — usado quando um RDO é corrigido. */
    public function estornarExecucao(float $quantidade): void
    {
        Regras::numeroPositivo($quantidade, 'Quantidade estornada');

        if (Regras::maiorQue($quantidade, $this->quantidadeExecutada)) {
            throw new ExcecaoDeDominio(sprintf(
                'Não é possível estornar %s do serviço %s: só há %s apontado.',
                $this->unidade->formatar($quantidade),
                $this->codigo,
                $this->unidade->formatar($this->quantidadeExecutada),
            ));
        }

        $this->quantidadeExecutada -= $quantidade;
    }

    public function saldo(): float
    {
        return $this->quantidadePrevista - $this->quantidadeExecutada;
    }

    /** Avanço físico, de 0 a 100. */
    public function percentualExecutado(): float
    {
        return round($this->quantidadeExecutada / $this->quantidadePrevista * 100, 2);
    }

    public function valorPrevisto(): float
    {
        return round($this->quantidadePrevista * $this->precoUnitario, 2);
    }

    public function valorExecutado(): float
    {
        return round($this->quantidadeExecutada * $this->precoUnitario, 2);
    }

    public function estaConcluido(): bool
    {
        return !Regras::maiorQue($this->saldo(), 0.0);
    }

    private static function validarQuantidade(float $quantidade, Unidade $unidade): float
    {
        if (!$unidade->aceitaFracao() && !Regras::ehInteiro($quantidade)) {
            throw new ExcecaoDeDominio(sprintf(
                'A unidade "%s" não aceita fração: %s não é uma quantidade válida.',
                $unidade->descricao(),
                rtrim(rtrim(number_format($quantidade, 3, ',', ''), '0'), ','),
            ));
        }

        return $quantidade;
    }
}
