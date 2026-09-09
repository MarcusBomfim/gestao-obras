<?php

declare(strict_types=1);

namespace GestaoObras\Dominio\Obra;

use GestaoObras\Dominio\ExcecaoDeDominio;
use GestaoObras\Dominio\Regras;

/**
 * Endereço do canteiro. É objeto de valor: não tem identidade própria, dois
 * endereços com os mesmos campos são o mesmo endereço.
 */
final class Endereco
{
    /** As 26 unidades federativas mais o Distrito Federal. */
    private const UNIDADES_FEDERATIVAS = [
        'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO',
        'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI',
        'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO',
    ];

    public readonly string $logradouro;
    public readonly string $numero;
    public readonly string $bairro;
    public readonly string $cidade;
    public readonly string $uf;
    public readonly string $cep;

    public function __construct(
        string $logradouro,
        string $numero,
        string $bairro,
        string $cidade,
        string $uf,
        string $cep,
    ) {
        $this->logradouro = Regras::textoObrigatorio($logradouro, 'Logradouro', 160);
        $this->numero = Regras::textoObrigatorio($numero, 'Número', 20);
        $this->bairro = Regras::textoObrigatorio($bairro, 'Bairro', 80);
        $this->cidade = Regras::textoObrigatorio($cidade, 'Cidade', 80);
        $this->uf = self::validarUf($uf);
        $this->cep = self::validarCep($cep);
    }

    /** Devolve o CEP formatado como 00000-000. */
    public function cepFormatado(): string
    {
        return substr($this->cep, 0, 5) . '-' . substr($this->cep, 5);
    }

    public function emUmaLinha(): string
    {
        return sprintf(
            '%s, %s - %s, %s/%s - CEP %s',
            $this->logradouro,
            $this->numero,
            $this->bairro,
            $this->cidade,
            $this->uf,
            $this->cepFormatado(),
        );
    }

    public function ehIgualA(self $outro): bool
    {
        return $this->logradouro === $outro->logradouro
            && $this->numero === $outro->numero
            && $this->bairro === $outro->bairro
            && $this->cidade === $outro->cidade
            && $this->uf === $outro->uf
            && $this->cep === $outro->cep;
    }

    private static function validarUf(string $uf): string
    {
        $normalizada = strtoupper(trim($uf));

        if (!in_array($normalizada, self::UNIDADES_FEDERATIVAS, true)) {
            throw new ExcecaoDeDominio("UF inválida: {$uf}.");
        }

        return $normalizada;
    }

    /** Guarda só os 8 dígitos; a formatação é problema de quem exibe. */
    private static function validarCep(string $cep): string
    {
        $digitos = preg_replace('/\D/', '', $cep) ?? '';

        if (strlen($digitos) !== 8) {
            throw new ExcecaoDeDominio("CEP inválido: {$cep}. Informe 8 dígitos.");
        }

        return $digitos;
    }
}
