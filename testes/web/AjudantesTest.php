<?php

declare(strict_types=1);

/*
 * As funções que os templates chamam. São pequenas, mas uma delas já mostrou
 * que erro pequeno em template é erro visível: a barra de avanço passou uma
 * versão inteira cheia em toda obra porque a largura ia para o CSS com vírgula.
 */

grupo('Ajudantes dos templates');

teste('escapa HTML', function (): void {
    igual('&lt;b&gt;&amp;&quot;', e('<b>&"'));
});

teste('formata número e dinheiro no padrão brasileiro', function (): void {
    igual('1.234,56', numeroBr(1234.56));
    igual('R$ 517.248,00', reais(517248.0));
});

teste('a largura da barra vai para o CSS com ponto, e presa entre 0 e 100', function (): void {
    // "27,3%" não é um width válido: o navegador ignora e a barra fica cheia.
    igual('27.3', barraDeAvanco(27.25));
    igual('0.0', barraDeAvanco(-5.0));
    igual('100.0', barraDeAvanco(140.0));
});

teste('formata a data como dia/mês/ano', function (): void {
    igual('16/09/2026', dataBr(new DateTimeImmutable('2026-09-16')));
});
