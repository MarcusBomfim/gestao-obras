<?php

declare(strict_types=1);

use GestaoObras\Aplicacao\Autenticador;
use GestaoObras\Dominio\ExcecaoDeDominio;
use GestaoObras\Dominio\Usuario\Papel;
use GestaoObras\Dominio\Usuario\Usuario;
use GestaoObras\Infraestrutura\Repositorio\RepositorioDeUsuariosEmSqlite;

grupo('Papel: permissões');

teste('engenheiro faz tudo', function (): void {
    verdadeiro(Papel::Engenheiro->podeConsultar(), 'consulta');
    verdadeiro(Papel::Engenheiro->podeApontarDiario(), 'aponta');
    verdadeiro(Papel::Engenheiro->podeMedir(), 'mede');
});

teste('mestre de obras aponta mas não mede', function (): void {
    verdadeiro(Papel::MestreDeObras->podeApontarDiario(), 'aponta');
    falso(Papel::MestreDeObras->podeMedir(), 'não mede');
});

teste('cliente só consulta', function (): void {
    verdadeiro(Papel::Cliente->podeConsultar(), 'consulta');
    falso(Papel::Cliente->podeApontarDiario(), 'não aponta');
    falso(Papel::Cliente->podeMedir(), 'não mede');
});

grupo('Usuário');

teste('cria a conta com hash, nunca com a senha', function (): void {
    $usuario = Usuario::criar('Marcus@Obras.dev', 'Marcus', Papel::Engenheiro, 'Segredo@123');

    igual('marcus@obras.dev', $usuario->email, 'e-mail em minúsculas');
    verdadeiro(str_starts_with($usuario->hashDaSenha(), '$2y$'), 'formato bcrypt');
    falso(str_contains($usuario->hashDaSenha(), 'Segredo@123'), 'senha não aparece no hash');
});

teste('confere a senha certa e recusa a errada', function (): void {
    $usuario = Usuario::criar('a@obras.dev', 'A', Papel::Cliente, 'Segredo@123');

    verdadeiro($usuario->senhaConfere('Segredo@123'), 'senha certa');
    falso($usuario->senhaConfere('segredo@123'), 'diferença de caixa');
    falso($usuario->senhaConfere(''), 'vazia');
});

teste('recusa senha curta', function (): void {
    lanca(
        ExcecaoDeDominio::class,
        static fn () => Usuario::criar('a@obras.dev', 'A', Papel::Cliente, '1234567'),
        'ao menos 8 caracteres',
    );
});

teste('recusa e-mail inválido', function (): void {
    lanca(
        ExcecaoDeDominio::class,
        static fn () => Usuario::criar('nao-e-email', 'A', Papel::Cliente, 'Segredo@123'),
        'E-mail inválido',
    );
});

teste('hash novo não precisa de atualização', function (): void {
    $usuario = Usuario::criar('a@obras.dev', 'A', Papel::Cliente, 'Segredo@123');

    falso($usuario->hashPrecisaAtualizar(), 'custo atual');
});

grupo('Repositório de usuários');

teste('grava e encontra pelo e-mail em qualquer caixa', function (): void {
    $repositorio = new RepositorioDeUsuariosEmSqlite(bancoDeTeste());
    $repositorio->salvar(Usuario::criar('marcus@obras.dev', 'Marcus', Papel::Engenheiro, 'Segredo@123'));

    $lido = $repositorio->porEmail('MARCUS@OBRAS.DEV');

    verdadeiro($lido !== null, 'encontrado');
    igual('Marcus', $lido?->nome);
    igual(Papel::Engenheiro, $lido?->papel);
    verdadeiro($lido?->senhaConfere('Segredo@123') ?? false, 'hash sobreviveu ao banco');
});

teste('preserva conta desativada', function (): void {
    $repositorio = new RepositorioDeUsuariosEmSqlite(bancoDeTeste());

    $usuario = Usuario::criar('a@obras.dev', 'A', Papel::Cliente, 'Segredo@123');
    $usuario->desativar();
    $repositorio->salvar($usuario);

    falso($repositorio->porEmail('a@obras.dev')?->estaAtivo() ?? true, 'continua desativada');
});

teste('salvar de novo atualiza em vez de duplicar', function (): void {
    $conexao = bancoDeTeste();
    $repositorio = new RepositorioDeUsuariosEmSqlite($conexao);

    $repositorio->salvar(Usuario::criar('a@obras.dev', 'Nome antigo', Papel::Cliente, 'Segredo@123'));
    $repositorio->salvar(Usuario::criar('a@obras.dev', 'Nome novo', Papel::Engenheiro, 'Segredo@123'));

    igual(1, (int) $conexao->query('SELECT COUNT(*) FROM usuarios')->fetchColumn());
    igual('Nome novo', $repositorio->porEmail('a@obras.dev')?->nome);
});

grupo('Autenticador');

function autenticadorComConta(bool $ativa = true): Autenticador
{
    $repositorio = new RepositorioDeUsuariosEmSqlite(bancoDeTeste());

    $usuario = Usuario::criar('marcus@obras.dev', 'Marcus', Papel::Engenheiro, 'Segredo@123');

    if (!$ativa) {
        $usuario->desativar();
    }

    $repositorio->salvar($usuario);

    return new Autenticador($repositorio);
}

teste('autentica com e-mail e senha corretos', function (): void {
    $usuario = autenticadorComConta()->autenticar('marcus@obras.dev', 'Segredo@123');

    verdadeiro($usuario !== null, 'autenticado');
    igual(Papel::Engenheiro, $usuario?->papel);
});

teste('recusa senha errada', function (): void {
    igual(null, autenticadorComConta()->autenticar('marcus@obras.dev', 'errada'));
});

teste('recusa e-mail inexistente sem lançar exceção', function (): void {
    // Tem que devolver null igualzinho à senha errada: qualquer diferença de
    // comportamento entregaria a lista de contas a quem ficasse tentando.
    igual(null, autenticadorComConta()->autenticar('ninguem@obras.dev', 'Segredo@123'));
});

teste('recusa conta desativada mesmo com a senha certa', function (): void {
    igual(null, autenticadorComConta(false)->autenticar('marcus@obras.dev', 'Segredo@123'));
});

teste('e-mail inexistente e senha errada custam tempo parecido', function (): void {
    // Não é um teste de segurança rigoroso — é um alarme: se a isca sumir e
    // a conta inexistente passar a responder em microssegundos, isto acusa.
    $autenticador = autenticadorComConta();

    $inicio = hrtime(true);
    $autenticador->autenticar('marcus@obras.dev', 'senha-errada');
    $comConta = hrtime(true) - $inicio;

    $inicio = hrtime(true);
    $autenticador->autenticar('ninguem@obras.dev', 'senha-errada');
    $semConta = hrtime(true) - $inicio;

    // bcrypt leva dezenas de milissegundos; a busca no SQLite, microssegundos.
    // Se a conta inexistente não passar pelo bcrypt, a razão dispara.
    $razao = $semConta / max(1, $comConta);

    verdadeiro($razao > 0.2 && $razao < 5.0, "razão de tempo fora do esperado: {$razao}");
});
