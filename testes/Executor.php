<?php

declare(strict_types=1);

/*
 * Executor de testes mínimo, em PHP puro.
 *
 * Existe porque o projeto ainda não depende do Composer, e portanto não tem
 * PHPUnit. São umas 80 linhas e cobrem o necessário: agrupar, comparar,
 * verificar exceção e devolver código de saída — que é o que a integração
 * contínua vai olhar mais adiante.
 */

final class Executor
{
    public static int $total = 0;

    /** @var string[] */
    public static array $falhas = [];

    public static string $grupo = '';

    private function __construct()
    {
    }

    /** Localiza a linha do teste que falhou, ignorando os quadros do executor. */
    public static function origem(): string
    {
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $quadro) {
            $arquivo = $quadro['file'] ?? '';

            if ($arquivo !== '' && basename($arquivo) !== 'Executor.php') {
                return basename($arquivo) . ':' . ($quadro['line'] ?? 0);
            }
        }

        return 'origem desconhecida';
    }

    public static function registrarFalha(string $motivo): void
    {
        self::$falhas[] = sprintf('%s > %s (%s)', self::$grupo, $motivo, self::origem());
    }

    public static function comoTexto(mixed $valor): string
    {
        if (is_float($valor)) {
            return var_export($valor, true);
        }

        if (is_bool($valor) || is_null($valor) || is_scalar($valor)) {
            return var_export($valor, true);
        }

        if ($valor instanceof BackedEnum) {
            return $valor::class . '::' . $valor->name;
        }

        return get_debug_type($valor);
    }
}

function grupo(string $nome): void
{
    Executor::$grupo = $nome;
    echo PHP_EOL, $nome, PHP_EOL;
}

function teste(string $descricao, callable $caso): void
{
    Executor::$total++;
    $falhasAntes = count(Executor::$falhas);

    try {
        $caso();
    } catch (Throwable $erro) {
        Executor::$falhas[] = sprintf(
            '%s > %s lançou %s: %s',
            Executor::$grupo,
            $descricao,
            $erro::class,
            $erro->getMessage(),
        );
    }

    $passou = count(Executor::$falhas) === $falhasAntes;
    echo '  ', $passou ? 'ok  ' : 'FALHOU  ', $descricao, PHP_EOL;
}

function igual(mixed $esperado, mixed $obtido, string $contexto = ''): void
{
    if ($esperado === $obtido) {
        return;
    }

    Executor::registrarFalha(sprintf(
        '%sesperado %s, obtido %s',
        $contexto === '' ? '' : $contexto . ': ',
        Executor::comoTexto($esperado),
        Executor::comoTexto($obtido),
    ));
}

/** Comparação de float com tolerância, para não brigar com ponto flutuante. */
function igualAproximado(float $esperado, float $obtido, string $contexto = ''): void
{
    if (abs($esperado - $obtido) <= 0.0001) {
        return;
    }

    Executor::registrarFalha(sprintf(
        '%sesperado %s, obtido %s',
        $contexto === '' ? '' : $contexto . ': ',
        var_export($esperado, true),
        var_export($obtido, true),
    ));
}

function verdadeiro(bool $condicao, string $contexto): void
{
    if (!$condicao) {
        Executor::registrarFalha($contexto . ': esperava verdadeiro');
    }
}

function falso(bool $condicao, string $contexto): void
{
    if ($condicao) {
        Executor::registrarFalha($contexto . ': esperava falso');
    }
}

/** Verifica que a ação lança a exceção esperada, opcionalmente com um trecho da mensagem. */
function lanca(string $classeEsperada, callable $acao, string $trechoDaMensagem = ''): void
{
    try {
        $acao();
    } catch (Throwable $erro) {
        if (!($erro instanceof $classeEsperada)) {
            Executor::registrarFalha(sprintf(
                'esperava %s, veio %s: %s',
                $classeEsperada,
                $erro::class,
                $erro->getMessage(),
            ));

            return;
        }

        if ($trechoDaMensagem !== '' && !str_contains($erro->getMessage(), $trechoDaMensagem)) {
            Executor::registrarFalha(sprintf(
                'mensagem "%s" não contém "%s"',
                $erro->getMessage(),
                $trechoDaMensagem,
            ));
        }

        return;
    }

    Executor::registrarFalha("esperava {$classeEsperada}, mas nada foi lançado");
}

function resumo(): int
{
    $falhas = count(Executor::$falhas);

    echo PHP_EOL, str_repeat('-', 60), PHP_EOL;

    foreach (Executor::$falhas as $falha) {
        echo '  ', $falha, PHP_EOL;
    }

    if ($falhas > 0) {
        echo PHP_EOL;
    }

    printf('%d testes, %d falha(s)%s', Executor::$total, $falhas, PHP_EOL);

    return $falhas === 0 ? 0 : 1;
}
