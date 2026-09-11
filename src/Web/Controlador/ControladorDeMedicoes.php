<?php

declare(strict_types=1);

namespace GestaoObras\Web\Controlador;

use DateTimeImmutable;
use GestaoObras\Aplicacao\FecharMedicao;
use GestaoObras\Aplicacao\GerarMedicao;
use GestaoObras\Dominio\ExcecaoDeDominio;
use GestaoObras\Dominio\Medicao\RepositorioDeMedicoes;
use GestaoObras\Dominio\Obra\RepositorioDeObras;
use GestaoObras\Web\Requisicao;
use GestaoObras\Web\Resposta;
use GestaoObras\Web\Sessao;
use GestaoObras\Web\Visao;
use Throwable;

final class ControladorDeMedicoes
{
    public function __construct(
        private readonly RepositorioDeObras $obras,
        private readonly RepositorioDeMedicoes $medicoes,
        private readonly GerarMedicao $gerar,
        private readonly FecharMedicao $fechar,
        private readonly Visao $visao,
        private readonly Sessao $sessao,
    ) {
    }

    public function lista(Requisicao $requisicao): Resposta
    {
        $obra = $this->obras->porCodigo($requisicao->parametro('codigo'));

        if ($obra === null) {
            return $this->obraNaoEncontrada($requisicao->parametro('codigo'));
        }

        $ultima = $this->medicoes->ultima($obra->codigo);

        // O próximo período já vem preenchido: começa no dia seguinte ao
        // fechamento anterior, ou no início da obra se for a primeira.
        $proximoInicio = $ultima === null
            ? $obra->dataDeInicio
            : $ultima->fim->modify('+1 day');

        return Resposta::html($this->visao->renderizar('medicoes.lista', [
            'obra' => $obra,
            'medicoes' => $this->medicoes->daObra($obra->codigo),
            'proximoInicio' => $proximoInicio->format('Y-m-d'),
            'hoje' => (new DateTimeImmutable('today'))->format('Y-m-d'),
            'token' => $this->sessao->token(),
            'mensagem' => $this->sessao->tirarMensagem(),
            'erro' => $this->sessao->tirarErro(),
        ], 'Medições — ' . $obra->nome));
    }

    public function detalhe(Requisicao $requisicao): Resposta
    {
        $obra = $this->obras->porCodigo($requisicao->parametro('codigo'));

        if ($obra === null) {
            return $this->obraNaoEncontrada($requisicao->parametro('codigo'));
        }

        $medicao = $this->medicoes->porNumero($obra->codigo, (int) $requisicao->parametro('numero'));

        if ($medicao === null) {
            return Resposta::naoEncontrado($this->visao->renderizar('erro', [
                'titulo' => 'Medição não encontrada',
                'detalhe' => 'Essa medição não existe nesta obra.',
            ], 'Medição não encontrada'));
        }

        return Resposta::html($this->visao->renderizar('medicoes.detalhe', [
            'obra' => $obra,
            'medicao' => $medicao,
            'token' => $this->sessao->token(),
            'mensagem' => $this->sessao->tirarMensagem(),
            'erro' => $this->sessao->tirarErro(),
        ], "Medição nº {$medicao->numero()} — " . $obra->nome));
    }

    public function criar(Requisicao $requisicao): Resposta
    {
        $codigo = $requisicao->parametro('codigo');
        $destino = "/obras/{$codigo}/medicoes";

        if (!$this->sessao->tokenValido($requisicao->campo('token'))) {
            $this->sessao->guardarErro('A sessão expirou. Tente de novo.');

            return Resposta::redirecionar($destino);
        }

        try {
            $medicao = $this->gerar->executar(
                $codigo,
                new DateTimeImmutable($requisicao->campo('inicio')),
                new DateTimeImmutable($requisicao->campo('fim')),
            );

            $this->sessao->guardarMensagem(sprintf(
                'Medição nº %d gerada: %s no período.',
                $medicao->numero(),
                'R$ ' . number_format($medicao->valorNoPeriodo(), 2, ',', '.'),
            ));

            return Resposta::redirecionar("{$destino}/{$medicao->numero()}");
        } catch (ExcecaoDeDominio $erro) {
            $this->sessao->guardarErro($erro->getMessage());
        } catch (Throwable $erro) {
            $this->sessao->guardarErro('Não foi possível gerar a medição. Confira as datas.');
        }

        return Resposta::redirecionar($destino);
    }

    public function fechar(Requisicao $requisicao): Resposta
    {
        $codigo = $requisicao->parametro('codigo');
        $numero = (int) $requisicao->parametro('numero');
        $destino = "/obras/{$codigo}/medicoes/{$numero}";

        if (!$this->sessao->tokenValido($requisicao->campo('token'))) {
            $this->sessao->guardarErro('A sessão expirou. Tente de novo.');

            return Resposta::redirecionar($destino);
        }

        try {
            $this->fechar->executar($codigo, $numero);

            $this->sessao->guardarMensagem(
                "Medição nº {$numero} fechada. A partir daqui ela é documento."
            );
        } catch (ExcecaoDeDominio $erro) {
            $this->sessao->guardarErro($erro->getMessage());
        }

        return Resposta::redirecionar($destino);
    }

    private function obraNaoEncontrada(string $codigo): Resposta
    {
        return Resposta::naoEncontrado($this->visao->renderizar('erro', [
            'titulo' => 'Obra não encontrada',
            'detalhe' => "Nenhuma obra com o código {$codigo}.",
        ], 'Obra não encontrada'));
    }
}
