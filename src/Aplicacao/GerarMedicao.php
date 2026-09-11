<?php

declare(strict_types=1);

namespace GestaoObras\Aplicacao;

use DateTimeImmutable;
use GestaoObras\Dominio\Diario\RepositorioDeDiarios;
use GestaoObras\Dominio\ExcecaoDeDominio;
use GestaoObras\Dominio\Medicao\ItemDeMedicao;
use GestaoObras\Dominio\Medicao\Medicao;
use GestaoObras\Dominio\Medicao\RepositorioDeMedicoes;
use GestaoObras\Dominio\Obra\RepositorioDeObras;
use GestaoObras\Dominio\Servico\RepositorioDeServicos;
use PDO;
use Throwable;

/**
 * Monta a medição de um período a partir dos diários.
 *
 * A conta não vem do acumulado guardado no serviço: é refeita somando os
 * diários. Assim cada linha da memória de cálculo é rastreável até o dia que a
 * originou — que é exatamente o que o cliente pede quando questiona a fatura.
 */
final class GerarMedicao
{
    public function __construct(
        private readonly PDO $conexao,
        private readonly RepositorioDeObras $obras,
        private readonly RepositorioDeServicos $servicos,
        private readonly RepositorioDeDiarios $diarios,
        private readonly RepositorioDeMedicoes $medicoes,
    ) {
    }

    public function executar(
        string $obraCodigo,
        DateTimeImmutable $inicio,
        DateTimeImmutable $fim,
    ): Medicao {
        $obra = $this->obras->porCodigo($obraCodigo);

        if ($obra === null) {
            throw new ExcecaoDeDominio("A obra {$obraCodigo} não foi encontrada.");
        }

        $medicao = new Medicao($obra->codigo, $inicio, $fim);

        $this->exigirPeriodoConsecutivo($obra->codigo, $medicao, $obra->dataDeInicio);

        $servicos = $this->servicos->daObra($obra->codigo);

        if ($servicos === []) {
            throw new ExcecaoDeDominio(
                "A obra {$obra->codigo} não tem serviços no orçamento, então não há o que medir."
            );
        }

        // Duas somas: tudo até a véspera do período, e o que entrou no período.
        $anteriores = $this->diarios->somaPorServico(
            $obra->codigo,
            null,
            $medicao->inicio->modify('-1 day'),
        );
        $noPeriodo = $this->diarios->somaPorServico($obra->codigo, $medicao->inicio, $medicao->fim);

        foreach ($servicos as $servico) {
            $medicao->adicionarItem(new ItemDeMedicao(
                $servico->codigo,
                $servico->descricao,
                $servico->unidade,
                $servico->quantidadePrevista,
                $servico->precoUnitario,
                $anteriores[$servico->codigo] ?? 0.0,
                $noPeriodo[$servico->codigo] ?? 0.0,
            ));
        }

        $this->conexao->beginTransaction();

        try {
            $numero = $this->medicoes->salvar($medicao);
            $this->conexao->commit();

            $medicao->definirNumero($numero);

            return $medicao;
        } catch (Throwable $erro) {
            $this->conexao->rollBack();

            throw $erro;
        }
    }

    /**
     * A medição seguinte começa no dia após o fim da anterior.
     *
     * É o que substitui, no SQLite, a constraint de exclusão que o PostgreSQL
     * teria sobre o intervalo de datas. Mais restritivo — e, por sorte, é
     * assim que se mede de verdade: competência após competência, sem buraco
     * e sem sobreposição.
     */
    private function exigirPeriodoConsecutivo(
        string $obraCodigo,
        Medicao $medicao,
        DateTimeImmutable $inicioDaObra,
    ): void {
        $ultima = $this->medicoes->ultima($obraCodigo);

        if ($ultima === null) {
            if ($medicao->inicio < $inicioDaObra) {
                throw new ExcecaoDeDominio(sprintf(
                    'A primeira medição não pode começar antes da obra, em %s.',
                    $inicioDaObra->format('d/m/Y'),
                ));
            }

            return;
        }

        $esperado = $ultima->fim->modify('+1 day');

        if ($medicao->inicio->format('Y-m-d') !== $esperado->format('Y-m-d')) {
            throw new ExcecaoDeDominio(sprintf(
                'A medição nº %d terminou em %s, então a próxima precisa começar em %s. '
                . 'Medições são consecutivas: sem buraco e sem sobreposição.',
                $ultima->numero(),
                $ultima->fim->format('d/m/Y'),
                $esperado->format('d/m/Y'),
            ));
        }
    }
}
