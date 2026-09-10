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

Depois, suba a aplicação:

```bash
php -S localhost:8000 -t public public/index.php
```

E abra <http://localhost:8000>. Não precisa de Apache nem nginx: o servidor embutido do PHP dá conta do desenvolvimento.

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
├── public/                   # raiz do servidor web
│   ├── index.php             # ponto de entrada e montagem das rotas
│   └── estilo.css
├── visoes/                   # templates PHP
│   ├── layout.php
│   ├── obras/
│   └── diarios/
├── src/
│   ├── autoload.php          # autoloader PSR-4 sem Composer
│   ├── ajudantes.php         # e(), reais(), dataBr() para os templates
│   ├── Dominio/
│   │   ├── Regras.php        # validações compartilhadas
│   │   ├── ExcecaoDeDominio.php
│   │   ├── Obra/             # Obra, Endereco, SituacaoDaObra
│   │   ├── Servico/          # Servico, Unidade
│   │   └── Diario/           # DiarioDeObra, ClimaDoDia, Efetivo, Ocorrencia
│   ├── Aplicacao/            # casos de uso e ResumoDaObra
│   ├── Infraestrutura/
│   │   ├── Banco/            # Conexao, Migrador
│   │   └── Repositorio/      # implementações em SQLite
│   └── Web/                  # Roteador, Requisicao, Resposta, Visao, Sessao
├── testes/
│   ├── executar.php          # ponto de entrada
│   ├── Executor.php          # executor de testes mínimo
│   ├── ajuda.php             # banco em memória e objetos de exemplo
│   ├── dominio/
│   ├── infraestrutura/
│   ├── aplicacao/
│   └── web/
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
| Um diário por obra por dia | `UNIQUE (obra_codigo, data)` |
| Diário não pode ter data futura | `DiarioDeObra::__construct` |
| Dia impraticável nos três períodos não tem produção | `DiarioDeObra::registrarAtividade` |
| Diário sem atividade nem ocorrência é recusado | `DiarioDeObra::exigirConsistencia` |
| Acidente e paralisação exigem descrição detalhada | `TipoDeOcorrencia::exigeDescricaoDetalhada` |
| Só obra em andamento aceita diário | `RegistrarDiarioDeObra` |
| Diário e avanço entram juntos ou não entram | transação em `RegistrarDiarioDeObra` |

## O diário de obra

É o centro do sistema. Cada dia da obra gera um RDO com clima e condição de trabalho nos três períodos, efetivo por função, serviços executados e ocorrências.

O avanço físico **nunca é digitado**: ele é a soma do que os diários apontaram. Registrar o diário e aplicar o avanço no orçamento acontecem na mesma transação — se um serviço estourar o previsto no meio do caminho, o diário inteiro é desfeito. O contrário deixaria um diário gravado cujo avanço não entrou, e ninguém descobriria até a medição não fechar.

### Um diário por dia

A regra que sustenta tudo é uma linha de SQL:

```sql
UNIQUE (obra_codigo, data)
```

Poderia estar na aplicação: consultar se já existe diário do dia antes de gravar. Mas duas requisições simultâneas leem antes de qualquer uma escrever, as duas passam na verificação e as duas gravam. Com a restrição no banco, a segunda recebe erro de unicidade, e o caso de uso traduz para uma mensagem legível. Deixa de ser improvável e passa a ser impossível.

### Por que clima e condição são campos separados

Chuva fraca pode ser praticável para serviço interno e impraticável para concretagem. Quem está no canteiro é que julga, e é esse julgamento que sustenta pedido de prorrogação de prazo. Com o registro diário, contar os dias perdidos vira uma consulta:

```php
$diarios->diasImpraticaveis($obra, $inicio, $fim);
```

### Corrigir um diário

Diário gravado não é reescrito em silêncio — é documento contratual. Corrigir passa por remover e registrar de novo, e `RemoverDiarioDeObra` estorna do orçamento o que aquele diário tinha apontado. Apagar sem estornar deixaria o avanço inflado para sempre.

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
3. **Diário de obra: clima, efetivo, atividades e a regra de um por dia** — concluída
4. **Interface web: roteador, listagens e formulários** — concluída
5. Medição e avanço: curva física, previsto contra realizado
6. Autenticação por papel, integração contínua e documentação final

## Interface web

Sem framework: um roteador próprio de cem linhas, templates em PHP e uma folha de estilo. É deliberado — o objetivo aqui é entender o ciclo da requisição, não delegá-lo.

**O roteador distingue 404 de 405.** "O caminho não existe" e "o caminho existe mas não aceita esse verbo" são coisas diferentes, e o segundo caso responde com o cabeçalho `Allow` dizendo quais verbos servem. É o detalhe que separa um roteador de brinquedo de um de verdade.

**Escapar é obrigação do template.** A função `e()` mora no namespace global, em `src/ajudantes.php`, e envolve todo valor interpolado. O nome é de uma letra de propósito: qualquer atrito aqui vira desculpa para esquecer, e é assim que nasce um XSS.

**Toda alteração é POST com token.** Sem o token anti-CSRF, qualquer página externa poderia enviar um formulário em nome de quem está autenticado — apagar um diário, por exemplo. O navegador manda os cookies de qualquer jeito; o que o site de terceiro não consegue é adivinhar o token. A comparação usa `hash_equals`, não `===`, para o tempo da comparação não revelar quantos caracteres estavam certos.

**Depois do POST vem redirecionamento, não HTML.** Status 303, que força o navegador a fazer GET no destino. É o padrão POST-Redirect-GET, e é o que impede o "reenviar formulário?" ao atualizar a página — que aqui significaria registrar o mesmo diário duas vezes.

### O avanço da obra é ponderado

`ResumoDaObra::percentualFisico()` divide valor executado por valor previsto, e não tira média dos percentuais dos serviços. Um serviço de R$ 84 mil concluído e outro de R$ 500 parado não são "50% da obra" — a média simples diria exatamente isso.

## Estado atual

Parte 4 concluída. O sistema tem interface: lista de obras com avanço, detalhe com os serviços do orçamento e o formulário de diário do dia. O que se digita ali entra direto no avanço físico — nenhum percentual é informado à mão em lugar nenhum.
