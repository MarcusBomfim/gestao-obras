<?php

declare(strict_types=1);

namespace GestaoObras\Web\Controlador;

use DateTimeImmutable;
use GestaoObras\Aplicacao\RegistrarDiarioDeObra;
use GestaoObras\Aplicacao\RemoverDiarioDeObra;
use GestaoObras\Dominio\Diario\AtividadeExecutada;
use GestaoObras\Dominio\Diario\Clima;
use GestaoObras\Dominio\Diario\ClimaDoDia;
use GestaoObras\Dominio\Diario\CondicaoDeTrabalho;
use GestaoObras\Dominio\Diario\DiarioDeObra;
use GestaoObras\Dominio\Diario\Efetivo;
use GestaoObras\Dominio\Diario\FuncaoDeMaoDeObra;
use GestaoObras\Dominio\Diario\Ocorrencia;
use GestaoObras\Dominio\Diario\RepositorioDeDiarios;
use GestaoObras\Dominio\Diario\TipoDeOcorrencia;
use GestaoObras\Dominio\ExcecaoDeDominio;
use GestaoObras\Dominio\Obra\RepositorioDeObras;
use GestaoObras\Dominio\Servico\RepositorioDeServicos;
use GestaoObras\Web\Requisicao;
use GestaoObras\Web\Resposta;
use GestaoObras\Web\Sessao;
use GestaoObras\Web\Visao;
use Throwable;

final class ControladorDeDiarios
{
    public function __construct(
        private readonly RepositorioDeObras $obras,
        private readonly RepositorioDeServicos $servicos,
        private readonly RepositorioDeDiarios $diarios,
        private readonly RegistrarDiarioDeObra $registrar,
        private readonly RemoverDiarioDeObra $remover,
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

        return Resposta::html($this->visao->renderizar('diarios.lista', [
            'obra' => $obra,
            'diarios' => $this->diarios->daObra($obra->codigo),
            'token' => $this->sessao->token(),
            'mensagem' => $this->sessao->tirarMensagem(),
            'erro' => $this->sessao->tirarErro(),
        ], 'Diários — ' . $obra->nome));
    }

    public function formulario(Requisicao $requisicao): Resposta
    {
        $obra = $this->obras->porCodigo($requisicao->parametro('codigo'));

        if ($obra === null) {
            return $this->obraNaoEncontrada($requisicao->parametro('codigo'));
        }

        return Resposta::html($this->visao->renderizar('diarios.formulario', [
            'obra' => $obra,
            'servicos' => $this->servicos->daObra($obra->codigo),
            'funcoes' => FuncaoDeMaoDeObra::cases(),
            'climas' => Clima::cases(),
            'tiposDeOcorrencia' => TipoDeOcorrencia::cases(),
            'hoje' => (new DateTimeImmutable('today'))->format('Y-m-d'),
            'token' => $this->sessao->token(),
            'erro' => $this->sessao->tirarErro(),
        ], 'Novo diário — ' . $obra->nome));
    }

    public function criar(Requisicao $requisicao): Resposta
    {
        $codigo = $requisicao->parametro('codigo');
        $destinoDoFormulario = "/obras/{$codigo}/diarios/novo";

        if (!$this->sessao->tokenValido($requisicao->campo('token'))) {
            $this->sessao->guardarErro(
                'A sessão expirou ou o formulário foi enviado de outra página. Tente de novo.'
            );

            return Resposta::redirecionar($destinoDoFormulario);
        }

        try {
            $diario = $this->montarDiario($requisicao, $codigo);
            $numero = $this->registrar->executar($diario);

            $this->sessao->guardarMensagem("Diário nº {$numero} registrado.");

            return Resposta::redirecionar("/obras/{$codigo}/diarios");
        } catch (ExcecaoDeDominio $erro) {
            // Regra de negócio: a mensagem já está escrita para quem vai ler.
            $this->sessao->guardarErro($erro->getMessage());

            return Resposta::redirecionar($destinoDoFormulario);
        } catch (Throwable $erro) {
            $this->sessao->guardarErro(
                'Não foi possível registrar o diário. Confira os dados e tente novamente.'
            );

            return Resposta::redirecionar($destinoDoFormulario);
        }
    }

    public function remover(Requisicao $requisicao): Resposta
    {
        $codigo = $requisicao->parametro('codigo');
        $destino = "/obras/{$codigo}/diarios";

        if (!$this->sessao->tokenValido($requisicao->campo('token'))) {
            $this->sessao->guardarErro('A sessão expirou. Tente de novo.');

            return Resposta::redirecionar($destino);
        }

        try {
            $numero = (int) $requisicao->parametro('numero');
            $this->remover->executar($codigo, $numero);

            $this->sessao->guardarMensagem(
                "Diário nº {$numero} removido e as quantidades estornadas do orçamento."
            );
        } catch (ExcecaoDeDominio $erro) {
            $this->sessao->guardarErro($erro->getMessage());
        }

        return Resposta::redirecionar($destino);
    }

    /** Traduz os campos do formulário no agregado do domínio. */
    private function montarDiario(Requisicao $requisicao, string $codigoDaObra): DiarioDeObra
    {
        $data = $requisicao->campo('data');

        if ($data === '') {
            throw new ExcecaoDeDominio('Informe a data do diário.');
        }

        $diario = new DiarioDeObra(
            $codigoDaObra,
            new DateTimeImmutable($data),
            $this->montarClima($requisicao),
            $requisicao->campo('responsavel'),
        );

        $diario->definirEfetivo($this->montarEfetivo($requisicao));

        foreach ($requisicao->linhas('atividades') as $linha) {
            $servico = $linha['servico'] ?? '';
            $quantidade = (float) str_replace(',', '.', $linha['quantidade'] ?? '');

            // Linha em branco é normal: o formulário oferece mais campos do
            // que a maioria dos dias usa.
            if ($servico === '' || $quantidade <= 0.0) {
                continue;
            }

            $diario->registrarAtividade(new AtividadeExecutada(
                $servico,
                $quantidade,
                ($linha['observacao'] ?? '') === '' ? null : $linha['observacao'],
            ));
        }

        foreach ($requisicao->linhas('ocorrencias') as $linha) {
            $descricao = $linha['descricao'] ?? '';

            if ($descricao === '') {
                continue;
            }

            $diario->registrarOcorrencia(new Ocorrencia(
                TipoDeOcorrencia::from($linha['tipo'] ?? 'outro'),
                $descricao,
            ));
        }

        return $diario;
    }

    private function montarClima(Requisicao $requisicao): ClimaDoDia
    {
        $clima = static fn (string $periodo): Clima => Clima::tryFrom(
            $requisicao->campo("clima_{$periodo}")
        ) ?? Clima::Bom;

        $condicao = static fn (string $periodo): CondicaoDeTrabalho => CondicaoDeTrabalho::tryFrom(
            $requisicao->campo("condicao_{$periodo}")
        ) ?? CondicaoDeTrabalho::Praticavel;

        return new ClimaDoDia(
            $clima('manha'),
            $condicao('manha'),
            $clima('tarde'),
            $condicao('tarde'),
            $clima('noite'),
            $condicao('noite'),
        );
    }

    private function montarEfetivo(Requisicao $requisicao): Efetivo
    {
        $efetivo = new Efetivo();

        foreach (FuncaoDeMaoDeObra::cases() as $funcao) {
            $quantidade = $requisicao->campoInteiro("efetivo_{$funcao->value}");

            if ($quantidade > 0) {
                $efetivo->definir($funcao, $quantidade);
            }
        }

        return $efetivo;
    }

    private function obraNaoEncontrada(string $codigo): Resposta
    {
        return Resposta::naoEncontrado($this->visao->renderizar('erro', [
            'titulo' => 'Obra não encontrada',
            'detalhe' => "Nenhuma obra com o código {$codigo}.",
        ], 'Obra não encontrada'));
    }
}
