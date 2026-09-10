<?php

declare(strict_types=1);

namespace GestaoObras\Dominio\Diario;

use GestaoObras\Dominio\ExcecaoDeDominio;

/**
 * Clima e condição de trabalho nos três períodos do dia.
 *
 * É o que transforma "choveu muito esse mês" em documento: com o registro
 * diário, contar os dias impraticáveis vira consulta, e o pedido de
 * prorrogação de prazo passa a ter base.
 */
final class ClimaDoDia
{
    /** @var array<string, array{clima: Clima, condicao: CondicaoDeTrabalho}> */
    private array $periodos;

    public function __construct(
        Clima $manha,
        CondicaoDeTrabalho $condicaoManha,
        Clima $tarde,
        CondicaoDeTrabalho $condicaoTarde,
        Clima $noite,
        CondicaoDeTrabalho $condicaoNoite,
    ) {
        $this->periodos = [
            PeriodoDoDia::Manha->value => ['clima' => $manha, 'condicao' => $condicaoManha],
            PeriodoDoDia::Tarde->value => ['clima' => $tarde, 'condicao' => $condicaoTarde],
            PeriodoDoDia::Noite->value => ['clima' => $noite, 'condicao' => $condicaoNoite],
        ];
    }

    /** Atalho para o caso mais comum: dia bom e trabalhável do começo ao fim. */
    public static function diaTrabalhavel(Clima $clima = Clima::Bom): self
    {
        return new self(
            $clima,
            CondicaoDeTrabalho::Praticavel,
            $clima,
            CondicaoDeTrabalho::Praticavel,
            $clima,
            CondicaoDeTrabalho::Praticavel,
        );
    }

    /** Dia inteiro perdido: chuva forte, interdição, o que for. */
    public static function diaPerdido(Clima $clima = Clima::Chuvoso): self
    {
        return new self(
            $clima,
            CondicaoDeTrabalho::Impraticavel,
            $clima,
            CondicaoDeTrabalho::Impraticavel,
            $clima,
            CondicaoDeTrabalho::Impraticavel,
        );
    }

    public function clima(PeriodoDoDia $periodo): Clima
    {
        return $this->periodos[$periodo->value]['clima'];
    }

    public function condicao(PeriodoDoDia $periodo): CondicaoDeTrabalho
    {
        return $this->periodos[$periodo->value]['condicao'];
    }

    /** Basta um período praticável para o dia render alguma coisa. */
    public function houvePeriodoTrabalhavel(): bool
    {
        foreach (PeriodoDoDia::cases() as $periodo) {
            if ($this->condicao($periodo)->permiteTrabalho()) {
                return true;
            }
        }

        return false;
    }

    public function ehDiaPerdido(): bool
    {
        return !$this->houvePeriodoTrabalhavel();
    }

    /** @return PeriodoDoDia[] */
    public function periodosImpraticaveis(): array
    {
        return array_values(array_filter(
            PeriodoDoDia::cases(),
            fn (PeriodoDoDia $periodo): bool => !$this->condicao($periodo)->permiteTrabalho(),
        ));
    }

    public function resumo(): string
    {
        $partes = array_map(
            fn (PeriodoDoDia $periodo): string => sprintf(
                '%s: %s (%s)',
                $periodo->rotulo(),
                $this->clima($periodo)->rotulo(),
                $this->condicao($periodo)->rotulo(),
            ),
            PeriodoDoDia::cases(),
        );

        return implode(' | ', $partes);
    }

    /** @return array<string, string> pronto para gravar, uma chave por coluna */
    public function paraColunas(): array
    {
        $colunas = [];

        foreach (PeriodoDoDia::cases() as $periodo) {
            $colunas["clima_{$periodo->value}"] = $this->clima($periodo)->value;
            $colunas["condicao_{$periodo->value}"] = $this->condicao($periodo)->value;
        }

        return $colunas;
    }

    /** @param array<string, mixed> $linha */
    public static function deColunas(array $linha): self
    {
        $exigir = static function (string $chave) use ($linha): string {
            if (!isset($linha[$chave])) {
                throw new ExcecaoDeDominio("Falta a coluna {$chave} no registro de clima.");
            }

            return (string) $linha[$chave];
        };

        return new self(
            Clima::from($exigir('clima_manha')),
            CondicaoDeTrabalho::from($exigir('condicao_manha')),
            Clima::from($exigir('clima_tarde')),
            CondicaoDeTrabalho::from($exigir('condicao_tarde')),
            Clima::from($exigir('clima_noite')),
            CondicaoDeTrabalho::from($exigir('condicao_noite')),
        );
    }
}
