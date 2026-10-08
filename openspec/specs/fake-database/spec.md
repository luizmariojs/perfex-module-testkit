# fake-database Specification

## Purpose

Oferece um banco de dados falso em memória, compatível com o query builder do CodeIgniter 3 usado
pelos módulos, para testar models e controllers sem MySQL e com asserções sobre o que foi lido e
gravado.

## Requirements

### Requirement: CRUD simples em tabelas em memória
O banco falso SHALL armazenar tabelas como coleções de linhas e executar sobre elas:
- `insert` (com `insert_id` incremental), `update`, `delete` e `get`/`get_where`;
- filtros `where` (igualdade e operadores `<`, `>`, `<=`, `>=`, `!=` na chave), `where_in`,
  `or_where` e `like`;
- `order_by`, `limit`, `select` de colunas e `count_all_results`.

`affected_rows` MUST refletir a última escrita. O estado do builder MUST ser limpo após cada
execução, como no CI3.

#### Scenario: Inserir e ler
- **WHEN** o teste semeia a tabela `tblinvoices` com uma linha `id=1` e o código faz `where('id', 1)->get('tblinvoices')->row()`
- **THEN** o código recebe um objeto com os campos da linha

#### Scenario: Atualizar com filtro
- **WHEN** o código executa `where('invoice_id', 5)->update('tblx', ['status' => 'success'])`
- **THEN** só as linhas com `invoice_id = 5` mudam e `affected_rows()` devolve a quantidade alterada

#### Scenario: Builder limpo entre consultas
- **WHEN** o código faz `where('a', 1)->get('t')` e em seguida `get('t')`
- **THEN** a segunda consulta não herda o filtro da primeira

### Requirement: Formatos de resultado
O resultado de `get` SHALL oferecer `row()`, `row_array()`, `result()`, `result_array()` e
`num_rows()`. Sem linhas, `row()` MUST devolver `null` e `result()` MUST devolver array vazio.

#### Scenario: Nenhuma linha
- **WHEN** a consulta não encontra linhas
- **THEN** `row()` devolve `null` e `num_rows()` devolve 0

### Requirement: Existência de tabelas e colunas
`table_exists` e `field_exists` SHALL responder conforme as tabelas e colunas declaradas pelo teste,
para que o código que verifica o schema antes de usar possa ser testado nos dois ramos.

#### Scenario: Coluna de outro módulo ausente
- **WHEN** o teste declara `tblinvoices` sem a coluna `asaas_cobranca_id`
- **THEN** `field_exists('asaas_cobranca_id', 'tblinvoices')` devolve falso

### Requirement: Respostas programadas para SQL cru e consultas complexas
Para `query()` com SQL cru e para consultas com `join`, `group_by` ou `select_sum`, o banco falso
MUST NOT tentar interpretar SQL. O teste SHALL programar respostas associadas a um padrão (texto
ou expressão regular) da consulta. Uma consulta complexa sem resposta programada MUST falhar o
teste, mostrando a consulta montada.

#### Scenario: SQL cru programado
- **WHEN** o teste programa a resposta `[{total: 3}]` para consultas que casam com `/COUNT\(\*\)/` e o código executa um `query()` correspondente
- **THEN** o código recebe um resultado com `total = 3`

#### Scenario: Consulta não programada
- **WHEN** o código executa um `join` sem resposta programada
- **THEN** o teste falha com mensagem que inclui a consulta montada

### Requirement: Registro de operações
Toda operação executada (tabela, tipo, filtros, dados, SQL montado) SHALL ser registrada em ordem.
O kit MUST oferecer asserções sobre esse registro: linha existe com campos, nenhuma escrita
ocorreu, consulta executada.

#### Scenario: Nenhuma escrita
- **WHEN** o código rejeita uma requisição antes de tocar o banco
- **THEN** a asserção "nenhuma escrita" passa

#### Scenario: Linha gravada
- **WHEN** o código grava um registro com `status = canceled`
- **THEN** a asserção de que existe uma linha com esse status na tabela passa
