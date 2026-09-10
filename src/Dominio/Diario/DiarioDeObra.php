<?php

declare(strict_types=1);

namespace GestaoObras\Dominio\Diario;

use DateTimeImmutable;
use GestaoObras\Dominio\ExcecaoDeDominio;
use GestaoObras\Dominio\Regras;

/**
 * O Relatório Diário de Obra: uma entrada por obra por dia.
 *
 * Documento contratual, e em obra pública exigência legal. Registra clima,
 * condição de trabalho, efetivo presente, o que foi executado e o que
 * aconteceu de relevante. É dele que saem o avanço físico e a medição — por
 * isso o percentual da obra nunca é digitado à mão.
 */
final class DiarioDeObra
{
    public readonly string $obraCodigo;
    public readonly DateTimeImmutable $data;
    public readonly ClimaDoDia $clima;
    public readonly string $responsavel;

    /** Sequencial dentro da obra. Zero enquanto o diário não foi gravado. */
    private int $numero = 0;

    private Efetivo $efetivo;

    /** @var AtividadeExecutada[] */
    private array $atividades = [];

    /** @var Ocorrencia[] */
    private array $ocorrencias = [];

    public function __construct(
        string $obraCodigo,
        DateTimeImmutable $data,
        ClimaDoDia $clima,
        string $responsavel,
        ?DateTimeImmutable $hoje = null,
    ) {
        $this->obraCodigo = strtoupper(Regras::textoObrigatorio($obraCodigo, 'Código da obra', 20));
        $this->clima = $clima;
        $this->responsavel = Regras::textoObrigatorio($responsavel, 'Responsável pelo diário', 160);
        $this->efetivo = new Efetivo();

        $dia = $data->setTime(0, 0);
        $limite = ($hoje ?? new DateTimeImmutable('today'))->setTime(0, 0);

        if ($dia > $limite) {
            throw new ExcecaoDeDominio(sprintf(
                'Não é possível abrir diário para %s: a data ainda não chegou.',
                $dia->format('d/m/Y'),
            ));
        }

        $this->data = $dia;
    }

    /**
     * Recria um diário vindo do banco, com o número que já tinha. Não valida a
     * data contra hoje: um diário de três meses atrás continua válido.
     */
    public static function reconstituir(
        string $obraCodigo,
        int $numero,
        DateTimeImmutable $data,
        ClimaDoDia $clima,
        string $responsavel,
    ): self {
        $diario = new self($obraCodigo, $data, $clima, $responsavel, $data);
        $diario->definirNumero($numero);

        return $diario;
    }

    public function numero(): int
    {
        return $this->numero;
    }

    public function definirNumero(int $numero): void
    {
        $this->numero = Regras::inteiroPositivo($numero, 'Número do diário');
    }

    public function efetivo(): Efetivo
    {
        return $this->efetivo;
    }

    public function definirEfetivo(Efetivo $efetivo): void
    {
        $this->efetivo = $efetivo;
    }

    /**
     * Aponta execução de um serviço.
     *
     * Recusa em dia totalmente impraticável: se ninguém pôde trabalhar em
     * nenhum dos três períodos, não há como ter produzido. É a checagem que
     * impede o diário de contradizer a si mesmo — e é justamente essa
     * coerência que sustenta o pedido de prorrogação de prazo depois.
     */
    public function registrarAtividade(AtividadeExecutada $atividade): void
    {
        if ($this->clima->ehDiaPerdido()) {
            throw new ExcecaoDeDominio(sprintf(
                'O dia %s está registrado como impraticável nos três períodos, '
                . 'então não pode ter serviço executado. Corrija a condição de '
                . 'trabalho ou remova a atividade.',
                $this->data->format('d/m/Y'),
            ));
        }

        $this->atividades[] = $atividade;
    }

    public function registrarOcorrencia(Ocorrencia $ocorrencia): void
    {
        $this->ocorrencias[] = $ocorrencia;
    }

    /** @return AtividadeExecutada[] */
    public function atividades(): array
    {
        return $this->atividades;
    }

    /** @return Ocorrencia[] */
    public function ocorrencias(): array
    {
        return $this->ocorrencias;
    }

    /**
     * Total apontado por serviço no dia. O mesmo serviço pode aparecer em mais
     * de uma linha — manhã e tarde, com observações diferentes — e quem aplica
     * na medição precisa do total.
     *
     * @return array<string, float> código do serviço => quantidade
     */
    public function quantidadePorServico(): array
    {
        $totais = [];

        foreach ($this->atividades as $atividade) {
            $totais[$atividade->servicoCodigo] =
                ($totais[$atividade->servicoCodigo] ?? 0.0) + $atividade->quantidade;
        }

        ksort($totais);

        return $totais;
    }

    public function ehDiaPerdido(): bool
    {
        return $this->clima->ehDiaPerdido();
    }

    /**
     * Um diário sem atividade e sem ocorrência não diz nada — e dia sem
     * registro é dia que ninguém consegue explicar depois. Se não houve
     * produção, o motivo precisa estar escrito.
     */
    public function exigirConsistencia(): void
    {
        if ($this->atividades !== [] || $this->ocorrencias !== []) {
            return;
        }

        throw new ExcecaoDeDominio(
            'O diário precisa registrar ao menos uma atividade executada ou uma '
            . 'ocorrência. Dia sem produção exige o motivo por escrito.'
        );
    }

    public function resumo(): string
    {
        return sprintf(
            'RDO %d - %s - efetivo %d - %d atividade(s), %d ocorrência(s)',
            $this->numero,
            $this->data->format('d/m/Y'),
            $this->efetivo->total(),
            count($this->atividades),
            count($this->ocorrencias),
        );
    }
}
