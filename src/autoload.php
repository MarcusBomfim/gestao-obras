<?php

declare(strict_types=1);

/*
 * Autoloader PSR-4 mínimo, para o projeto rodar só com o PHP instalado.
 *
 * O composer.json na raiz já declara o mesmo mapeamento. Quando você instalar
 * o Composer, rode `composer install` e troque a inclusão deste arquivo por
 * `vendor/autoload.php` — nada mais muda, porque a convenção é a mesma.
 */

// Funções de apoio aos templates, no namespace global.
require __DIR__ . DIRECTORY_SEPARATOR . 'ajudantes.php';

spl_autoload_register(static function (string $classe): void {
    $prefixo = 'GestaoObras\\';
    $baseDeDiretorios = __DIR__ . DIRECTORY_SEPARATOR;

    if (!str_starts_with($classe, $prefixo)) {
        return;
    }

    $relativo = substr($classe, strlen($prefixo));
    $caminho = $baseDeDiretorios . str_replace('\\', DIRECTORY_SEPARATOR, $relativo) . '.php';

    if (is_file($caminho)) {
        require $caminho;
    }
});
