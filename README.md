# Gestão de Obras

Sistema para acompanhar obras de construção civil a partir do que acontece no canteiro: o diário de obra, o avanço físico de cada serviço e a medição do que já pode ser faturado.

## O problema

Toda obra no Brasil precisa manter um **Diário de Obra** — o RDO. É documento contratual e, em obra pública, exigência legal. Na prática ele costuma ser um caderno no barracão ou uma planilha que alguém preenche de memória no fim da semana.

Isso custa caro em três frentes:

- **A medição vira discussão.** Quanto de alvenaria foi executado até dia 20? Se o registro é impreciso, cliente e construtora divergem, e a fatura atrasa.
- **O atraso aparece tarde.** Sem avanço físico diário, o desvio de cronograma só fica visível quando já não dá para reagir.
- **A obra não se defende.** Choveu 11 dias no mês e o serviço parou? Sem registro diário de clima e condição de trabalho, não há como pleitear prorrogação de prazo.

O sistema ataca isso registrando o dia a dia de forma estruturada e derivando dele o avanço e a medição, em vez de pedir que alguém digite os três separadamente.

## Requisitos

**PHP 8.1 ou superior** — o projeto usa enums, `readonly` e `match`.

```bash
php -v
```

Se ainda não tiver PHP no Windows: baixe o ZIP "Thread Safe" em [windows.php.net](https://windows.php.net/download), extraia em `C:\php` e adicione essa pasta ao PATH. O [XAMPP](https://www.apachefriends.org) também serve.

A extensão `pdo_sqlite` já vem habilitada nas distribuições oficiais. Confira com `php -m | findstr sqlite`.

Não é preciso Composer nem servidor de banco de dados.

## Como rodar

```bash
php ferramentas/migrar.php
```

```bash
php ferramentas/semear.php
```

O banco é criado em `banco/gestao-obras.sqlite`, fora do controle de versão. As duas ferramentas podem rodar quantas vezes for preciso: a migração pula o que já aplicou e a carga usa `ON CONFLICT DO NOTHING`.

## Como rodar os testes

```bash
php testes/executar.php
```

Sai com código 0 quando tudo passa e 1 quando algo falha. Os testes de infraestrutura sobem um SQLite **em memória** e aplicam as migrations reais, então não deixam arquivo para trás nem dependem do banco de trabalho.

## Estrutura

```text
gestao-obras/
├── banco/
│   ├── migrations/           # SQL versionado, aplicado em ordem
│   └── seeds/                # dados de demonstração
├── ferramentas/
│   ├── migrar.php
│   └── semear.php
├── src/
│   ├── autoload.php          # autoloader PSR-4 sem Composer
│   ├── Dominio/
│   │   ├── Regras.php        # validações compartilhadas
│   │   ├── ExcecaoDeDominio.php
│   │   ├── Obra/             # Obra, Endereco, SituacaoDaObra, interface do repositório
│   │   └── Servico/          # Servico, Unidade, interface do repositório
│   └── Infraestrutura/
│       ├── Banco/            # Conexao, Migrador
│       └── Repositorio/      # implementações em SQLite
├── testes/
│   ├── executar.php          # ponto de entrada
│   ├── Executor.php          # executor de testes mínimo
│   ├── ajuda.php             # banco em memória e objetos de exemplo
│   ├── dominio/
│   └── infraestrutura/
├── composer.json
└── README.md
```

## O que o domínio já garante

| Regra | Onde |
| --- | --- |
| Obra nasce planejada e só muda de situação por caminho permitido | `SituacaoDaObra::podeMudarPara` |
| Obra concluída é estado final, não volta atrás | `SituacaoDaObra::ehFinal` |
| Só obra em andamento aceita apontamento de execução | `SituacaoDaObra::aceitaExecucao` |
| O dia de início conta dentro do prazo | `Obra::dataPrevistaDeTermino` |
| Obra concluída não acumula atraso | `Obra::diasDeAtraso` |
| UF precisa existir; CEP precisa ter 8 dígitos | `Endereco` |
| Executado nunca ultrapassa o previsto sem aditivo | `Servico::registrarExecucao` |
| Unidades como `un` e `vb` não aceitam fração | `Unidade::aceitaFracao` |

### Sobre o "não ultrapassa o previsto"

É a regra com mais consequência do domínio. Apontar mais do que o contratado não é engano de digitação: significa que a obra executou além do escopo, e isso vira **aditivo contratual** — decisão comercial, não apontamento de campo. Por isso o serviço recusa, e a mensagem diz quanto ainda cabe:

> O serviço ALV-01 tem apenas 20,00 m² de saldo, e foram apontados 25,00 m². Para ultrapassar o previsto é preciso um aditivo.

### Sobre comparar quantidades

Quantidade de obra é `float`, e `0.1 + 0.2` não é exatamente `0.3` em ponto flutuante. Sem folga, um serviço de 0,3 m³ executado em duas parcelas nunca fecharia em 100%. Todas as comparações passam por `Regras::maiorQue`, com tolerância de 0,001 — abaixo da precisão de qualquer medição de canteiro.

## Banco de dados

**SQLite**, para o projeto rodar sem servidor: quem clonar sobe tudo com o PHP e mais nada. O código fala PDO e o SQL é padrão, então trocar por MySQL ou PostgreSQL depois mexe na classe `Conexao` e nas migrations, não nas regras.

As migrations são arquivos `.sql` numerados, aplicados em ordem e uma vez cada, com registro na tabela `migrations_aplicadas`. Cada uma roda dentro de uma transação: aplica inteira ou não aplica.

Três detalhes valem destaque.

**O SQLite ignora chave estrangeira por padrão.** É compatibilidade com versões antigas, e ninguém avisa: o `ON DELETE CASCADE` simplesmente não acontece. A classe `Conexao` executa `PRAGMA foreign_keys = ON` em toda conexão, e há um teste que insere um serviço órfão só para garantir que esse PRAGMA não suma.

**A regra do previsto está nos dois lugares.** A classe `Servico` recusa apontamento acima do previsto, e a tabela `servicos` tem um `CHECK` dizendo o mesmo. Não é redundância desperdiçada: a validação da aplicação dá a mensagem boa para quem está usando, e a do banco garante que nenhum caminho escape — carga de dados, correção manual, ou dois apontamentos simultâneos.

**Valores em `REAL` são uma simplificação conhecida.** Dinheiro em ponto flutuante é imprecisão acumulada, e o certo em produção seria guardar centavos como inteiro ou usar `DECIMAL`. Aqui o domínio já trabalha com `float`, e trocar isso é refatoração de domínio, não de persistência — fica anotado para uma etapa futura, não escondido.

O repositório é interface no domínio e implementação na infraestrutura. O domínio pede uma obra pelo código e recebe uma `Obra`; que exista SQLite do outro lado é problema de quem implementa.

## Etapas do projeto

1. **Domínio: obra, serviços e regras** — concluída
2. **Persistência: SQLite, migrations versionadas e repositórios** — concluída
3. Diário de obra (RDO): clima, efetivo, atividades e a regra de um por dia
4. Interface web: roteador, listagens e formulários
5. Medição e avanço: curva física, previsto contra realizado
6. Autenticação por papel, integração contínua e documentação final

## Estado atual

Parte 2 concluída. O domínio persiste em SQLite com migrations versionadas, e os testes de repositório rodam contra o mesmo SQL que roda em produção — só que num banco em memória, criado e descartado a cada execução.
