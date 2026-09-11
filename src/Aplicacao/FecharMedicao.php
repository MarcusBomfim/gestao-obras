<?php

declare(strict_types=1);

namespace GestaoObras\Aplicacao;

use GestaoObras\Dominio\ExcecaoDeDominio;
use GestaoObras\Dominio\Medicao\Medicao;
use GestaoObras\Dominio\Medicao\RepositorioDeMedicoes;

/**
 * Fecha a medição, transformando o levantamento em base de fatura.
 *
 * Só a última medição pode ser fechada, e só se todas as anteriores já
 * estiverem fechadas — faturar a competência de março com a de fevereiro ainda
 * aberta produziria duas faturas disputando o mesmo período.
 */
final class FecharMedicao
{
    public function __construct(private readonly RepositorioDeMedicoes $medicoes)
    {
    }

    public function executar(string $obraCodigo, int $numero): Medicao
    {
        $medicao = $this->medicoes->porNumero($obraCodigo, $numero);

        if ($medicao === null) {
            throw new ExcecaoDeDominio(
                "Não existe medição de número {$numero} na obra {$obraCodigo}."
            );
        }

        if ($medicao->estaFechada()) {
            throw new ExcecaoDeDominio("A medição nº {$numero} já está fechada.");
        }

        $this->exigirAnterioresFechadas($obraCodigo, $numero);

        // A validação de "não há o que medir" mora na entidade.
        $medicao->fechar();

        $this->medicoes->fechar($obraCodigo, $numero);

        return $medicao;
    }

    private function exigirAnterioresFechadas(string $obraCodigo, int $numero): void
    {
        foreach ($this->medicoes->daObra($obraCodigo) as $outra) {
            if ($outra->numero() < $numero && !$outra->estaFechada()) {
                throw new ExcecaoDeDominio(sprintf(
                    'A medição nº %d ainda está aberta. Feche as competências em ordem.',
                    $outra->numero(),
                ));
            }
        }
    }
}
