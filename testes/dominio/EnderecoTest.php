<?php

declare(strict_types=1);

use GestaoObras\Dominio\ExcecaoDeDominio;
use GestaoObras\Dominio\Obra\Endereco;

function enderecoValido(string $uf = 'SP', string $cep = '11310-000'): Endereco
{
    return new Endereco('Avenida Ayrton Senna', '1500', 'Itararé', 'São Vicente', $uf, $cep);
}

grupo('Endereço do canteiro');

teste('aceita um endereço completo', function (): void {
    $endereco = enderecoValido();

    igual('Avenida Ayrton Senna', $endereco->logradouro);
    igual('São Vicente', $endereco->cidade);
    igual('SP', $endereco->uf);
});

teste('normaliza a UF para maiúsculas', function (): void {
    igual('SP', enderecoValido('sp')->uf);
});

teste('recusa UF que não existe', function (): void {
    lanca(ExcecaoDeDominio::class, static fn () => enderecoValido('XX'), 'UF inválida');
});

teste('guarda o CEP só com dígitos', function (): void {
    igual('11310000', enderecoValido('SP', '11310-000')->cep);
    igual('11310000', enderecoValido('SP', '11.310-000')->cep);
});

teste('formata o CEP na exibição', function (): void {
    igual('11310-000', enderecoValido()->cepFormatado());
});

teste('recusa CEP com quantidade errada de dígitos', function (): void {
    lanca(ExcecaoDeDominio::class, static fn () => enderecoValido('SP', '1131-000'), 'CEP inválido');
});

teste('recusa campo obrigatório em branco', function (): void {
    lanca(
        ExcecaoDeDominio::class,
        static fn () => new Endereco('   ', '100', 'Centro', 'Santos', 'SP', '11010-000'),
        'Logradouro é obrigatório',
    );
});

teste('monta o endereço em uma linha', function (): void {
    igual(
        'Avenida Ayrton Senna, 1500 - Itararé, São Vicente/SP - CEP 11310-000',
        enderecoValido()->emUmaLinha(),
    );
});

teste('compara dois endereços pelo conteúdo', function (): void {
    verdadeiro(enderecoValido()->ehIgualA(enderecoValido()), 'mesmos campos');
    falso(enderecoValido()->ehIgualA(enderecoValido('RJ')), 'UF diferente');
});
