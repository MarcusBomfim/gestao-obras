<?php

declare(strict_types=1);

namespace GestaoObras\Aplicacao;

use GestaoObras\Dominio\Diario\DiarioDeObra;
use GestaoObras\Dominio\Diario\RepositorioDeDiarios;
use GestaoObras\Dominio\ExcecaoDeDominio;
use GestaoObras\Dominio\Obra\RepositorioDeObras;
use GestaoObras\Dominio\Servico\RepositorioDeServicos;
use PDO;
use PDOException;
use Throwable;

/**
 * Registra o diário do dia e aplica no orçamento o que foi executado.
 *
 * As duas coisas acontecem na mesma transação. Se um serviço estourar o
 * previsto no meio do caminho, o diário inteiro é desfeito — o contrário
 * deixaria o sistema com um diário gravado cujo avanço não entrou, e ninguém
 * descobriria até a medição não fechar.
 */
final class RegistrarDiarioDeObra
{
    public function __construct(
        private readonly PDO $conexao,
        private readonly RepositorioDeObras $obras,
        private readonly RepositorioDeServicos $servicos,
        private readonly RepositorioDeDiarios $diarios,
    ) {
    }

    /** @return int o número sequencial do diário dentro da obra */
    public function executar(DiarioDeObra $diario): int
    {
        $diario->exigirConsistencia();

        $obra = $this->obras->porCodigo($diario->obraCodigo);

        if ($obra === null) {
            throw new ExcecaoDeDominio("A obra {$diario->obraCodigo} não foi encontrada.");
        }

        if (!$obra->situacao()->aceitaExecucao()) {
            throw new ExcecaoDeDominio(sprintf(
                'A obra %s está %s e não aceita diário. Inicie ou retome a obra antes.',
                $obra->codigo,
                mb_strtolower($obra->situacao()->rotulo()),
            ));
        }

        if ($diario->data < $obra->dataDeInicio) {
            throw new ExcecaoDeDominio(sprintf(
                'O diário de %s é anterior ao início da obra, em %s.',
                $diario->data->format('d/m/Y'),
                $obra->dataDeInicio->format('d/m/Y'),
            ));
        }

        $this->conexao->beginTransaction();

        try {
            $numero = $this->diarios->salvar($diario);

            $this->aplicarNoOrcamento($diario);

            $this->conexao->commit();

            // Só depois do commit o número existe de fato.
            $diario->definirNumero($numero);

            return $numero;
        } catch (PDOException $erro) {
            $this->conexao->rollBack();

            throw $this->traduzir($erro, $diario);
        } catch (Throwable $erro) {
            $this->conexao->rollBack();

            throw $erro;
        }
    }

    /**
     * Soma no serviço o que o diário apontou. É aqui que a regra do previsto
     * entra em ação: quem recusa é a entidade Servico, não este caso de uso.
     */
    private function aplicarNoOrcamento(DiarioDeObra $diario): void
    {
        foreach ($diario->quantidadePorServico() as $codigo => $quantidade) {
            $servico = $this->servicos->porCodigo($diario->obraCodigo, $codigo);

            if ($servico === null) {
                throw new ExcecaoDeDominio(sprintf(
                    'O serviço %s não está no orçamento da obra %s.',
                    $codigo,
                    $diario->obraCodigo,
                ));
            }

            $servico->registrarExecucao($quantidade);

            $this->servicos->salvar($diario->obraCodigo, $servico);
        }
    }

    /**
     * Traduz erro de restrição do banco para mensagem de negócio.
     *
     * A restrição de unicidade é o que garante um diário por dia mesmo quando
     * duas pessoas gravam ao mesmo tempo — a verificação em PHP passaria nas
     * duas, porque as duas leem antes de qualquer uma escrever.
     */
    private function traduzir(PDOException $erro, DiarioDeObra $diario): Throwable
    {
        $mensagem = $erro->getMessage();

        if (str_contains($mensagem, 'diarios.obra_codigo, diarios.data')) {
            return new ExcecaoDeDominio(sprintf(
                'Já existe diário da obra %s para o dia %s. Um dia tem um diário só; '
                . 'para corrigir, remova o existente e registre de novo.',
                $diario->obraCodigo,
                $diario->data->format('d/m/Y'),
            ));
        }

        if (str_contains($mensagem, 'diarios.obra_codigo, diarios.numero')) {
            return new ExcecaoDeDominio(
                'Dois diários foram gravados ao mesmo tempo e receberam o mesmo número. '
                . 'Tente registrar novamente.'
            );
        }

        if (str_contains($mensagem, 'FOREIGN KEY')) {
            return new ExcecaoDeDominio(
                'Alguma atividade aponta um serviço que não pertence ao orçamento desta obra.'
            );
        }

        return $erro;
    }
}
