<?php

declare(strict_types=1);

namespace GestaoObras\Infraestrutura\Repositorio;

use DateTimeImmutable;
use GestaoObras\Dominio\Obra\Endereco;
use GestaoObras\Dominio\Obra\Obra;
use GestaoObras\Dominio\Obra\RepositorioDeObras;
use GestaoObras\Dominio\Obra\SituacaoDaObra;
use PDO;

final class RepositorioDeObrasEmSqlite implements RepositorioDeObras
{
    private const COLUNAS = 'codigo, nome, cliente, logradouro, numero, bairro, cidade, uf, cep,
        data_de_inicio, prazo_em_dias, responsavel_tecnico, registro_profissional, situacao';

    public function __construct(private readonly PDO $conexao)
    {
    }

    public function salvar(Obra $obra): void
    {
        /*
         * Um comando só resolve criar e atualizar. Evita o "consulta, decide,
         * grava" — entre a consulta e a gravação outra requisição pode ter
         * criado a mesma obra, e aí o INSERT falharia.
         */
        $comando = $this->conexao->prepare(
            'INSERT INTO obras (' . self::COLUNAS . ')
             VALUES (:codigo, :nome, :cliente, :logradouro, :numero, :bairro, :cidade, :uf, :cep,
                     :data_de_inicio, :prazo_em_dias, :responsavel_tecnico, :registro_profissional,
                     :situacao)
             ON CONFLICT (codigo) DO UPDATE SET
                nome                  = excluded.nome,
                cliente               = excluded.cliente,
                logradouro            = excluded.logradouro,
                numero                = excluded.numero,
                bairro                = excluded.bairro,
                cidade                = excluded.cidade,
                uf                    = excluded.uf,
                cep                   = excluded.cep,
                data_de_inicio        = excluded.data_de_inicio,
                prazo_em_dias         = excluded.prazo_em_dias,
                responsavel_tecnico   = excluded.responsavel_tecnico,
                registro_profissional = excluded.registro_profissional,
                situacao              = excluded.situacao,
                atualizado_em         = datetime(\'now\')'
        );

        $comando->execute([
            ':codigo' => $obra->codigo,
            ':nome' => $obra->nome,
            ':cliente' => $obra->cliente,
            ':logradouro' => $obra->endereco->logradouro,
            ':numero' => $obra->endereco->numero,
            ':bairro' => $obra->endereco->bairro,
            ':cidade' => $obra->endereco->cidade,
            ':uf' => $obra->endereco->uf,
            ':cep' => $obra->endereco->cep,
            ':data_de_inicio' => $obra->dataDeInicio->format('Y-m-d'),
            ':prazo_em_dias' => $obra->prazoEmDias,
            ':responsavel_tecnico' => $obra->responsavelTecnico,
            ':registro_profissional' => $obra->registroProfissional,
            ':situacao' => $obra->situacao()->value,
        ]);
    }

    public function porCodigo(string $codigo): ?Obra
    {
        $consulta = $this->conexao->prepare(
            'SELECT ' . self::COLUNAS . ' FROM obras WHERE codigo = :codigo'
        );
        $consulta->execute([':codigo' => strtoupper(trim($codigo))]);

        $linha = $consulta->fetch();

        return $linha === false ? null : self::montar($linha);
    }

    public function todas(): array
    {
        $consulta = $this->conexao->query(
            'SELECT ' . self::COLUNAS . ' FROM obras ORDER BY codigo'
        );

        return array_map(self::montar(...), $consulta === false ? [] : $consulta->fetchAll());
    }

    public function porSituacao(SituacaoDaObra $situacao): array
    {
        $consulta = $this->conexao->prepare(
            'SELECT ' . self::COLUNAS . ' FROM obras WHERE situacao = :situacao ORDER BY codigo'
        );
        $consulta->execute([':situacao' => $situacao->value]);

        return array_map(self::montar(...), $consulta->fetchAll());
    }

    public function existe(string $codigo): bool
    {
        $consulta = $this->conexao->prepare('SELECT 1 FROM obras WHERE codigo = :codigo');
        $consulta->execute([':codigo' => strtoupper(trim($codigo))]);

        return $consulta->fetchColumn() !== false;
    }

    public function remover(string $codigo): void
    {
        $comando = $this->conexao->prepare('DELETE FROM obras WHERE codigo = :codigo');
        $comando->execute([':codigo' => strtoupper(trim($codigo))]);
    }

    /** @param array<string, mixed> $linha */
    private static function montar(array $linha): Obra
    {
        return Obra::reconstituir(
            (string) $linha['codigo'],
            (string) $linha['nome'],
            (string) $linha['cliente'],
            new Endereco(
                (string) $linha['logradouro'],
                (string) $linha['numero'],
                (string) $linha['bairro'],
                (string) $linha['cidade'],
                (string) $linha['uf'],
                (string) $linha['cep'],
            ),
            new DateTimeImmutable((string) $linha['data_de_inicio']),
            (int) $linha['prazo_em_dias'],
            (string) $linha['responsavel_tecnico'],
            (string) $linha['registro_profissional'],
            SituacaoDaObra::from((string) $linha['situacao']),
        );
    }
}
