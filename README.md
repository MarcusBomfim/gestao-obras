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

Não é preciso Composer nem banco de dados para a Parte 1.

## Como rodar os testes

```bash
php testes/executar.php
```

Sai com código 0 quando tudo passa e 1 quando algo falha.

## Estrutura

```text
gestao-obras/
├── src/
│   ├── autoload.php          # autoloader PSR-4 sem Composer
│   └── Dominio/
│       ├── Regras.php        # validações compartilhadas
│       ├── ExcecaoDeDominio.php
│       ├── Obra/             # Obra, Endereco, SituacaoDaObra
│       └── Servico/          # Servico, Unidade
├── testes/
│   ├── executar.php          # ponto de entrada
│   ├── Executor.php          # executor de testes mínimo
│   └── dominio/
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

## Etapas do projeto

1. **Domínio: obra, serviços e regras** — concluída
2. Persistência em SQLite, migrations versionadas e repositórios
3. Diário de obra (RDO): clima, efetivo, atividades e a regra de um por dia
4. Interface web: roteador, listagens e formulários
5. Medição e avanço: curva física, previsto contra realizado
6. Autenticação por papel, integração contínua e documentação final

## Estado atual

Parte 1 concluída. O domínio está modelado e coberto por testes, sem depender de banco, framework ou Composer — dá para clonar e rodar `php testes/executar.php` com o PHP instalado e mais nada.
