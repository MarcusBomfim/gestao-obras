<?php

declare(strict_types=1);

namespace GestaoObras\Dominio\Obra;

use DateInterval;
use DateTimeImmutable;
use GestaoObras\Dominio\ExcecaoDeDominio;
use GestaoObras\Dominio\Regras;

/**
 * Uma obra em execução: o contrato, o canteiro e o prazo.
 *
 * As propriedades de identidade são readonly. Em PHP dá para atribuir uma
 * propriedade readonly uma única vez de dentro da classe — por isso elas são
 * declaradas aqui e atribuídas no construtor, em vez de promovidas: assim o
 * valor pode passar pela validação antes de ser gravado.
 */
final class Obra
{
    public readonly string $codigo;
    public readonly string $nome;
    public readonly string $cliente;
    public readonly Endereco $endereco;
    public readonly DateTimeImmutable $dataDeInicio;
    public readonly int $prazoEmDias;
    public readonly string $responsavelTecnico;
    public readonly string $registroProfissional;

    private SituacaoDaObra $situacao;

    public function __construct(
        string $codigo,
        string $nome,
        string $cliente,
        Endereco $endereco,
        DateTimeImmutable $dataDeInicio,
        int $prazoEmDias,
        string $responsavelTecnico,
        string $registroProfissional,
    ) {
        $this->codigo = strtoupper(Regras::textoObrigatorio($codigo, 'Código da obra', 20));
        $this->nome = Regras::textoObrigatorio($nome, 'Nome da obra', 160);
        $this->cliente = Regras::textoObrigatorio($cliente, 'Cliente', 160);
        $this->endereco = $endereco;
        $this->prazoEmDias = Regras::inteiroPositivo($prazoEmDias, 'Prazo em dias');
        $this->responsavelTecnico = Regras::textoObrigatorio(
            $responsavelTecnico,
            'Responsável técnico',
            160,
        );
        $this->registroProfissional = self::validarRegistro($registroProfissional);

        // A hora não interessa: uma obra começa em um dia, não em um instante.
        $this->dataDeInicio = $dataDeInicio->setTime(0, 0);

        $this->situacao = SituacaoDaObra::Planejada;
    }

    public function situacao(): SituacaoDaObra
    {
        return $this->situacao;
    }

    public function iniciar(): void
    {
        $this->mudarPara(SituacaoDaObra::EmAndamento);
    }

    public function paralisar(): void
    {
        $this->mudarPara(SituacaoDaObra::Paralisada);
    }

    public function retomar(): void
    {
        $this->mudarPara(SituacaoDaObra::EmAndamento);
    }

    public function concluir(): void
    {
        $this->mudarPara(SituacaoDaObra::Concluida);
    }

    /** O prazo conta a partir do primeiro dia, então o dia de início já entra. */
    public function dataPrevistaDeTermino(): DateTimeImmutable
    {
        return $this->dataDeInicio->add(new DateInterval('P' . ($this->prazoEmDias - 1) . 'D'));
    }

    /** Dias corridos desde o início, contando o dia de hoje. */
    public function diasDecorridos(DateTimeImmutable $referencia): int
    {
        $dias = $this->dataDeInicio->diff($referencia->setTime(0, 0))->days;

        if ($dias === false || $referencia->setTime(0, 0) < $this->dataDeInicio) {
            return 0;
        }

        return $dias + 1;
    }

    /** Zero quando ainda está dentro do prazo ou já foi concluída. */
    public function diasDeAtraso(DateTimeImmutable $referencia): int
    {
        if ($this->situacao === SituacaoDaObra::Concluida) {
            return 0;
        }

        $termino = $this->dataPrevistaDeTermino();
        $hoje = $referencia->setTime(0, 0);

        if ($hoje <= $termino) {
            return 0;
        }

        return (int) $termino->diff($hoje)->days;
    }

    public function estaAtrasada(DateTimeImmutable $referencia): bool
    {
        return $this->diasDeAtraso($referencia) > 0;
    }

    private function mudarPara(SituacaoDaObra $destino): void
    {
        if (!$this->situacao->podeMudarPara($destino)) {
            throw new ExcecaoDeDominio(sprintf(
                'Não é possível mudar a obra de "%s" para "%s".',
                $this->situacao->rotulo(),
                $destino->rotulo(),
            ));
        }

        $this->situacao = $destino;
    }

    /**
     * CREA e CAU seguem formatos diferentes por estado, então a validação aqui
     * é só de forma: letras, dígitos, traços e barras, entre 5 e 30 caracteres.
     * Conferir se o registro existe de verdade é consulta a órgão externo.
     */
    private static function validarRegistro(string $registro): string
    {
        $limpo = strtoupper(Regras::textoObrigatorio($registro, 'Registro profissional', 30));

        if (preg_match('/^[A-Z0-9\-\/\.]{5,30}$/', $limpo) !== 1) {
            throw new ExcecaoDeDominio(
                "Registro profissional inválido: {$registro}. Informe o CREA ou o CAU."
            );
        }

        return $limpo;
    }
}
