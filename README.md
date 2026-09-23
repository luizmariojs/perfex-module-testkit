# perfex-module-testkit

Kit compartilhado de testes automatizados para módulos do **Perfex CRM** (CodeIgniter 3).

Com ele, o código de um módulo (models, libraries, helpers, controllers, webhooks, migrations) roda
dentro do PHPUnit **sem Perfex instalado, sem MySQL e sem rede**. O kit fornece:

| Peça | O que faz |
|---|---|
| **Stubs do runtime** | `get_instance()`, loader, `CI_Controller`/`App_Model`/`AdminController`/`App_module_migration`, `$this->input`, options, `_l`, URLs, permissões, `log_activity`, `set_alert`, `hooks()` |
| **Banco falso** | Compatível com o query builder do CI3: CRUD simples em memória; SQL cru e consultas complexas por respostas programadas |
| **HTTP** | Requisição simulada (`$_SERVER`, `$_GET`/`$_POST`, `php://input`), captura de `http_response_code()` e da saída, transporte HTTP falso para chamadas de saída |
| **`PerfexTestCase`** | Zera o estado entre testes e traz asserções prontas |
| **Workflow reutilizável** | GitHub Actions com matriz de PHP, chamado com poucas linhas |

Nenhum código do Perfex CRM é distribuído aqui, apenas substitutos escritos para teste. Requer PHP ≥ 8.1.

---

## Instalação num módulo

`composer.json` do módulo (só dependências de desenvolvimento, nunca vai para o ZIP):

```json
{
    "require-dev": {
        "phpunit/phpunit": "^10.5",
        "luizmariojs/perfex-module-testkit": "^0.1"
    },
    "repositories": [
        { "type": "vcs", "url": "https://github.com/luizmariojs/perfex-module-testkit" }
    ],
    "config": { "platform": { "php": "8.1" } },
    "scripts": { "test": "phpunit" }
}
```

`phpunit.xml.dist`:

```xml
<phpunit bootstrap="tests/bootstrap.php" colors="true" failOnWarning="true" failOnRisky="true">
    <testsuites>
        <testsuite name="Unit"><directory>tests/Unit</directory></testsuite>
    </testsuites>
</phpunit>
```

`tests/bootstrap.php`, o bootstrap mínimo:

```php
<?php

require dirname(__DIR__) . '/vendor/autoload.php';

\PerfexTestkit\Testkit::boot(dirname(__DIR__)); // raiz do módulo
```

Depois é só rodar `composer install` e `composer test`.

Exclua do pacote de distribuição do módulo: `tests/`, `phpunit.xml.dist`, `composer.json`, `vendor/`
e `.github/`.

---

## Exemplos

Os exemplos usam o módulo de exemplo `sample_module`, em `tests/fixtures/sample_module/`. Todos são
executados em `tests/Docs/ReadmeExamplesTest.php`.

### Model com banco falso

```php
use PerfexTestkit\PerfexTestCase;
use PerfexTestkit\Testkit;

final class SampleModelTest extends PerfexTestCase
{
    public function test_muda_status(): void
    {
        Testkit::db()->table('tblsample_records', ['id', 'invoice_id', 'status'], [
            ['id' => 1, 'invoice_id' => 10, 'status' => 'pending'],
        ]);

        $CI = &get_instance();
        $CI->load->model('sample_module/sample_model');
        $affected = $CI->sample_model->set_status(10, 'done');

        $this->assertSame(1, $affected);
        $this->assertRowExists('tblsample_records', ['invoice_id' => 10, 'status' => 'done']);
    }

    public function test_sql_cru_programado(): void
    {
        Testkit::db()->respond('/GROUP BY status/', [['status' => 'done', 'total' => 3]]);

        get_instance()->load->model('sample_module/sample_model');

        $this->assertSame(3, get_instance()->sample_model->totals_by_status()[0]['total']);
    }
}
```

### Webhook (controller público)

```php
public function test_token_invalido(): void
{
    require_once Testkit::moduleRoot() . '/controllers/Sample_webhook.php';
    Testkit::db()->table('tblsample_records', ['id', 'invoice_id', 'status']);
    Testkit::option('sample_webhook_token', 'segredo');

    Testkit::request('POST', ['X-Sample-Token' => 'errado'], ['event' => 'RECORD_DONE', 'invoice_id' => 10]);
    $response = Testkit::call(fn () => (new Sample_webhook())->index());

    $this->assertStatus(401, $response);
    $this->assertBody('Unauthorized', $response);
    $this->assertNoWrites();
}
```

### Chamada HTTP de saída (transporte injetado)

```php
use PerfexTestkit\Http\RecordingTransport;

public function test_agenda_nota(): void
{
    Testkit::option('sample_api_key', 'chave');
    $transport = (new RecordingTransport())->queue(200, ['id' => 'inv_1', 'status' => 'SCHEDULED']);

    get_instance()->load->library('sample_module/sample_api', $transport);
    $result = get_instance()->sample_api->schedule_invoice(['value' => 100, 'customer' => 'cus_1']);

    $this->assertSame('SCHEDULED', $result['data']['status']);
    $transport->assertSent('POST', '/v3/invoices', fn ($body) => $body['customer'] === 'cus_1');
}
```

### Hooks do arquivo principal

```php
public function test_hook_de_fatura(): void
{
    Testkit::load('sample_module.php'); // registra os hooks do módulo

    Testkit::fireAction('after_invoice_added', 42);

    $this->assertOptionEquals('sample_last_invoice', 42);
    $this->assertLogged('Fatura adicionada #42');
}
```

`Testkit::load()` inclui o arquivo uma única vez por processo, sem redeclarar funções, e **reaplica os
hooks** que ele registrou nos testes seguintes. Carregue arquivos que registram hooks sempre por ele,
não com `require`/`include`.

---

## Referência rápida

### `Testkit` (fachada)

| Método | Uso |
|---|---|
| `boot($raiz, $opções)` | No bootstrap. Define `BASEPATH`, `APPPATH`, `FCPATH` (diretório temporário), `APP_MODULES_PATH` e `ENVIRONMENT=testing`, e carrega os stubs. Opções: `base_url`, `fcpath` |
| `option($nome, $valor)` / `options([...])` | Define options; com um argumento, lê |
| `actingAs($id, ['feature' => ['view', 'edit']], $admin)` / `asGuest()` | Staff logado e permissões |
| `double('invoices_model', $obj)` | Dublê de componente que não é do módulo (models nativos do Perfex) |
| `db()` | Banco falso: `table()`, `respond()`, `rows()`, `log()`, `writes()` |
| `load('arquivo.php')` | Inclui um arquivo do módulo e reaplica seus hooks nos testes seguintes |
| `fireAction($tag, ...)` / `applyFilter($tag, $valor, ...)` | Dispara hooks registrados |
| `activity()` / `alerts()` / `hooks()` | Efeitos capturados |
| `request($método, $headers, $corpo, $get, $post)` | Requisição simulada (corpo array vira JSON) |
| `call(fn () => ...)` | Captura a saída e o código HTTP (200 se não definido) → `Response` (`status`, `body`, `json()`) |

### Asserções de `PerfexTestCase`

`assertOptionEquals`, `assertOptionMissing`, `assertLogged`, `assertNotLogged`, `assertAlert`,
`assertHookRegistered`, `assertStatus`, `assertBody`, `assertBodyContains`, `assertRowExists`,
`assertRowMissing`, `assertNoWrites`, `assertQueried`.

### Funções com saída fixa

| Função | Saída no kit |
|---|---|
| `_l('chave', $args)` | A própria chave (com `sprintf` dos argumentos) |
| `db_prefix()` | `tbl` |
| `admin_url('x')` / `site_url('x')` / `base_url('x')` | `http://localhost/admin/x` / `http://localhost/x` |
| `app_format_money(1234.5, 'R$')` | `R$ 1.234,50` |
| `format_invoice_number(123)` | `INV-000123` |
| `_d('2026-09-23')` | `23/09/2026` |
| `get_staff_user_id()` / `is_admin()` / `has_permission()` / `staff_can()` | Conforme `Testkit::actingAs()`; admin tem tudo |

### Banco falso

- **Modo tabela** (executado em memória): `select` de colunas, `from`, `where` (igualdade,
  `!=`/`<>`/`>`/`<`/`>=`/`<=` na chave, `IS NULL`), `or_where`, `where_in`/`where_not_in`,
  `like`/`not_like`, `order_by`, `limit`/`offset`, `get`, `get_where`, `count_all_results`,
  `count_all`, `insert` (+ `insert_id`, auto-incremento de `id`), `insert_batch`, `update`
  (+ `set`), `delete`, `affected_rows`.
- **Modo roteiro** (nunca interpretado, só respondido): `query()` cru, `join`, `group_by`,
  `having`, `select_sum/max/min/avg`, `distinct`, `group_start`, select/order com função, `where` em
  texto livre, `set(..., false)`. Programe com `respond('/regex/' ou 'trecho', $linhas)`. A última
  resposta registrada vence. Para `count_all_results`, passe um `int`.
- **Semântica próxima do MySQL:** comparação numérica entre números, strings sem diferenciar
  maiúsculas/minúsculas, `NULL` só casa com `IS NULL`, `affected_rows` conta só linhas alteradas,
  coluna inexistente numa escrita falha ("Unknown column"), `AND` tem precedência sobre `OR`.
- Tabelas precisam ser declaradas com `table()`. `table_exists`, `field_exists` e `list_fields`
  respondem pelo que foi declarado.

### Loader

- `load->model('<modulo>/<nome>_model')`, `load->library('<modulo>/<nome>', $params)` e
  `load->helper('<modulo>/<nome>')` resolvem os arquivos do **módulo em teste**.
- Componentes de fora do módulo (ex.: `invoices_model`) precisam de `Testkit::double()`; sem isso, o
  teste falha nomeando o componente.
- Helpers nativos (`url`, `date`...) são aceitos sem efeito, porque as funções vêm dos stubs.

### Violações

Consulta sem resposta programada, chamada HTTP inesperada, componente não resolvido e coluna
inexistente lançam um `\Error`, que não é capturado por `catch (Exception $e)`. Além disso, ficam
registrados: o `PerfexTestCase` **falha o teste** mesmo que o código do módulo capture `\Throwable`.

---

## Limitações e como contornar

| Não interceptável | Por quê | Como contornar |
|---|---|---|
| `curl_*` direto | Funções internas do PHP não podem ser substituídas | Padrão de **injeção de transporte** (abaixo) |
| `exit` / `die` | Encerram o processo do PHPUnit | Em código novo, `return` após responder; em código legado, testar o que vem antes ou usar `#[RunInSeparateProcess]` |
| `header()` | Sem efeito na CLI (`headers_list()` vazio) | Verificar o código HTTP (`http_response_code()`) e o corpo; isolar a montagem de headers numa função testável |
| Lógica interna do Perfex | Cálculo de fatura, e-mails, custom fields completos não são reproduzidos | Dublês com `Testkit::double()` |
| SQL real | O banco falso não interpreta SQL | Modo roteiro; consultas complexas em métodos pequenos de model; integração com MySQL real (camada L2) numa etapa futura |
| Testes em paralelo | O estado do kit é global ao processo | Rodar o PHPUnit sequencialmente (padrão) |

### Padrão de injeção de transporte HTTP

A library HTTP do módulo aceita um transporte **opcional**. Em produção usa curl; no teste recebe o
`RecordingTransport`. O módulo **não** depende do kit: basta um objeto com
`request($method, $url, $headers, $body)` que devolva algo com `status` e `body`.

```php
class Minha_api
{
    private $transport;

    public function __construct($transport = null)
    {
        $this->transport = $transport;
    }

    private function request($method, $url, $body = null)
    {
        $headers = ['access_token' => get_option('minha_api_key')];

        if ($this->transport !== null) {
            $r = $this->transport->request($method, $url, $headers, $body);
            return ['status' => $r->status, 'body' => $r->body];
        }

        // ... curl em produção
    }
}
```

Exemplo completo: `tests/fixtures/sample_module/libraries/Sample_api.php`.

---

## CI no módulo

`.github/workflows/tests.yml`:

```yaml
name: Testes
on:
  pull_request:
    branches: [develop, main]
jobs:
  phpunit:
    uses: luizmariojs/perfex-module-testkit/.github/workflows/php-unit.yml@v0
    with:
      php-versions: '["8.1", "8.3"]'
```

Como o kit é público, o workflow não precisa de secrets, mesmo em repositório privado.
`@v0` acompanha as versões compatíveis `0.x`.

---

## Contribuindo

Veja `CONTRIBUTING.md` e `CLAUDE.md`. Resumo:

- toda mudança parte de uma issue, numa branch `feature/*`, com PR para `develop`;
- releases com tag `v0.x.y` e atualização da tag `v0`;
- stub novo só entra com autoteste.

Licença: MIT.
