<?php

declare(strict_types=1);

namespace GestaoObras\Web\Controlador;

use GestaoObras\Aplicacao\ResumoDaObra;
use GestaoObras\Dominio\Diario\RepositorioDeDiarios;
use GestaoObras\Dominio\Obra\RepositorioDeObras;
use GestaoObras\Dominio\Servico\RepositorioDeServicos;
use GestaoObras\Web\Requisicao;
use GestaoObras\Web\Resposta;
use GestaoObras\Web\Sessao;
use GestaoObras\Web\Visao;

final class ControladorDeObras
{
    public function __construct(
        private readonly RepositorioDeObras $obras,
        private readonly RepositorioDeServicos $servicos,
        private readonly RepositorioDeDiarios $diarios,
        private readonly Visao $visao,
        private readonly Sessao $sessao,
    ) {
    }

    public function lista(Requisicao $requisicao): Resposta
    {
        $resumos = array_map(
            fn ($obra): ResumoDaObra => new ResumoDaObra(
                $obra,
                $this->servicos->daObra($obra->codigo),
            ),
            $this->obras->todas(),
        );

        return Resposta::html($this->visao->renderizar('obras.lista', [
            'resumos' => $resumos,
            'hoje' => new \DateTimeImmutable('today'),
            'mensagem' => $this->sessao->tirarMensagem(),
            'erro' => $this->sessao->tirarErro(),
        ], 'Obras'));
    }

    public function detalhe(Requisicao $requisicao): Resposta
    {
        $codigo = $requisicao->parametro('codigo');
        $obra = $this->obras->porCodigo($codigo);

        if ($obra === null) {
            return Resposta::naoEncontrado($this->visao->renderizar('erro', [
                'titulo' => 'Obra não encontrada',
                'detalhe' => "Nenhuma obra com o código {$codigo}.",
            ], 'Obra não encontrada'));
        }

        $servicos = $this->servicos->daObra($obra->codigo);

        return Resposta::html($this->visao->renderizar('obras.detalhe', [
            'resumo' => new ResumoDaObra($obra, $servicos),
            'servicos' => $servicos,
            // Os últimos diários dão o pulso da obra sem sair da página.
            'ultimosDiarios' => array_slice($this->diarios->daObra($obra->codigo), 0, 5),
            'hoje' => new \DateTimeImmutable('today'),
            'mensagem' => $this->sessao->tirarMensagem(),
            'erro' => $this->sessao->tirarErro(),
        ], $obra->nome));
    }
}
