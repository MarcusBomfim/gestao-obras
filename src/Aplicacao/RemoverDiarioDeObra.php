<?php

declare(strict_types=1);

namespace GestaoObras\Aplicacao;

use GestaoObras\Dominio\Diario\RepositorioDeDiarios;
use GestaoObras\Dominio\ExcecaoDeDominio;
use GestaoObras\Dominio\Servico\RepositorioDeServicos;
use PDO;
use Throwable;

/**
 * Remove um diário e devolve ao orçamento o que ele tinha apontado.
 *
 * Existe porque diário se corrige: o apontamento saiu errado, o serviço estava
 * trocado, a quantidade foi digitada com um zero a mais. Apagar sem estornar
 * deixaria o avanço físico inflado para sempre, e a medição sairia errada sem
 * que ninguém soubesse por quê.
 */
final class RemoverDiarioDeObra
{
    public function __construct(
        private readonly PDO $conexao,
        private readonly RepositorioDeServicos $servicos,
        private readonly RepositorioDeDiarios $diarios,
    ) {
    }

    public function executar(string $obraCodigo, int $numero): void
    {
        $diario = $this->diarios->porNumero($obraCodigo, $numero);

        if ($diario === null) {
            throw new ExcecaoDeDominio(
                "Não existe diário de número {$numero} na obra {$obraCodigo}."
            );
        }

        $this->conexao->beginTransaction();

        try {
            foreach ($diario->quantidadePorServico() as $codigo => $quantidade) {
                $servico = $this->servicos->porCodigo($diario->obraCodigo, $codigo);

                if ($servico === null) {
                    continue;
                }

                $servico->estornarExecucao($quantidade);

                $this->servicos->salvar($diario->obraCodigo, $servico);
            }

            $this->diarios->remover($diario->obraCodigo, $diario->numero());

            $this->conexao->commit();
        } catch (Throwable $erro) {
            $this->conexao->rollBack();

            throw $erro;
        }
    }
}
