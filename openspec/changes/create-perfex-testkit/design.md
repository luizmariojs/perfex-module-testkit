## Context

Veja `proposal.md` (Why). Levantamento de uso nos 6 módulos (23/09/2026):

- **Funções globais do Perfex mais usadas** (arquivos que usam): `db_prefix` 228, `admin_url` 226,
  `get_instance` 164, `log_activity` 138, `get_option` 134, `_l` 120, `update_option` 80,
  `has_permission` 70, `is_admin` 47, `_d` 44, `get_staff_user_id` 42, `hooks` 41, `set_alert` 40,
  `app_format_money` 36, `format_invoice_number` 33, `module_dir_path` 31.
- **Query builder** (chamadas): `where` 2031, `get` 815, **`query` (SQL cru) 712**, `select` 576,
  `table_exists` 335, `field_exists` 334, `update` 307, `from` 307, `insert` 251, `order_by` 222,
  `join` 148, `delete` 147. Resultados via `row` 644, `result_array` 326, `result` 210.
- **Classes base:** `App_module_migration` (119), `AdminController` (49), `App_Model` (31),
  `CI_Controller`.
- **HTTP de saída:** feito com `curl_*` direto em 38 arquivos, sem ponto de injeção.
- **Primeiro consumidor** (`connect_asaas_nf`, Fase 0b): `Nfse_payload_builder`,
  `Connect_asaas_nf_model` e o webhook `Nfse_webhook` (`CI_Controller`, `php://input`,
  `http_response_code`, grava `FCPATH/modules/.../logs/webhook.log`).
- Os módulos **não usam namespaces** e protegem helpers com `function_exists`.

## Goals / Non-Goals

**Goals:**
- Cobrir as necessidades da Fase 0b por completo e ser útil aos outros módulos sem mudar o código deles.
- API pequena e explícita: o teste declara o mundo (options, tabelas, usuário, requisição) e depois verifica efeitos.
- Zero dependência de rede, banco ou Perfex.

**Non-Goals:**
- Interpretar SQL (MySQL ou outro): consultas complexas usam respostas programadas.
- Reproduzir a lógica interna do Perfex (cálculo de fatura, envio de e-mail, custom fields
  completos). Dublês de models nativos são responsabilidade do teste.
- Interceptar `curl_*`, `exit`/`die` e `header()`.
- Camada L2 (Perfex real em container): change futura, possivelmente em outro repositório.

## Decisions

### 1. Estrutura do pacote e namespace
- Classes do kit em `src/` sob o namespace `PerfexTestkit\` (PSR-4).
- Stubs globais em `stubs/` sem namespace (funções e classes do Perfex/CI3), carregados **somente**
  por `PerfexTestkit\Testkit::boot(string $moduleRoot, array $options = [])`.
- Não usar `autoload.files` do Composer, para não poluir o escopo global de quem só instala o pacote
  (requisito "Inicialização explícita").

*Alternativa:* `autoload.files`. É mais simples, mas define constantes e funções assim que o
autoload carrega, mesmo fora dos testes.

### 2. Estado central único, zerado por teste
- Um registro interno (`PerfexTestkit\State`) guarda options, instância CI, banco, logs, alertas,
  hooks, usuário/permissões e requisição.
- Os stubs globais são finos e só leem e gravam nesse estado.
- `PerfexTestCase::setUp()` chama `State::reset()`, e `tearDown()` restaura superglobais e o
  stream wrapper.

Consequência: um único ponto de verdade e isolamento garantido. O efeito colateral é que o kit não
suporta testes paralelos no mesmo processo; o PHPUnit é sequencial por padrão.

### 3. Loader que resolve pela raiz do módulo
- `load->model('modulo/nome_model')` resolve `<moduleRoot>/models/Nome_model.php` (primeira letra
  maiúscula, convenção do Perfex), faz `require_once`, instancia a classe e anexa em
  `$CI->nome_model`. O mesmo vale para `library` (`libraries/`) e `helper` (`helpers/<nome>_helper.php`).
- Prefixos de outro módulo, ou nomes sem prefixo (models nativos como `invoices_model`), só
  resolvem por `Testkit::double('invoices_model', $obj)`.
- Nome não resolvido lança `UnresolvedComponentException` com o nome pedido.

*Alternativa:* autoload geral dos diretórios do módulo. Esconderia dependências nativas não
dubladas, e o erro explícito é mais útil.

Helpers nativos (`load->helper('url')`) são aceitos sem efeito. Arquivos que registram hooks (o
arquivo principal do módulo) são carregados por `Testkit::load()`: o arquivo é incluído uma vez por
processo, e os registros de hooks da primeira carga são reaplicados nos testes seguintes. Isso é
necessário porque o estado é zerado a cada teste e reincluir o arquivo redeclararia funções.
Incluir o arquivo por fora e depois chamar `load()` gera erro explícito.

### 4. Classes base com propriedades delegadas à instância
- `CI_Controller` se registra como instância CI ao ser construída, como no CI3, em que o controller
  **é** o `get_instance()`.
- `CI_Model`, `App_Model` e `App_module_migration` usam `__get` para delegar ao `get_instance()`.
- `AdminController` estende `CI_Controller` sem checagem de login.
- Assim o código do módulo usa `$this->db` e `$this->nome_model` como no Perfex.

### 5. Banco falso híbrido
- **Modo tabela:** para CRUD simples sobre uma tabela (`where`, `where_in`, `or_where`, `like`,
  `order_by`, `limit`, `select`, `count_all_results`, `insert`, `update`, `delete`, `get_where`),
  executa sobre arrays e grava a operação.
- **Modo roteiro:** para `query()` cru e builder com `join`, `group_by`, `select_sum`,
  `group_start` ou `having`, monta o SQL aproximado (só para exibição e casamento) e procura uma
  resposta programada por `Testkit::db()->respond($pattern, $rows)`. Sem resposta, falha com o SQL
  montado.
- **Schema declarado:** `Testkit::db()->table('tblinvoices', ['id', 'hash', ...], $rows)` define as
  colunas, que respondem a `field_exists`/`table_exists`/`list_fields`.

*Alternativas descartadas:*
- **SQLite em memória com o driver real do CI3:** comportamento mais fiel do builder, mas o SQL cru
  é MySQL (ENUM, `ON DUPLICATE KEY`, funções de data), traria o `codeigniter/framework` como
  dependência e exigiria schema por teste. Fica como possível evolução, se o modo roteiro se
  mostrar insuficiente.
- **Mocks do PHPUnit por chamada:** frágeis diante da interface fluente do builder.

### 6. Requisição e resposta HTTP
- **`php://input`:** um stream wrapper próprio substitui o protocolo `php` enquanto a requisição
  simulada está ativa. `php://input` devolve o corpo simulado; os outros caminhos `php://`
  (`memory`, `temp`, `stdout`, `stderr`, `output`) são repassados ao wrapper nativo, com
  restauração garantida no `tearDown`.
- **Resposta:** `Testkit::call(fn () => $controller->index())` envolve a chamada em output
  buffering e lê `http_response_code()`, que funciona na CLI (verificado no PHP 8.5: define e
  devolve o código).
- **Fallback:** se o wrapper se mostrar instável, o kit oferece o atributo
  `#[RunInSeparateProcess]` como recomendação documentada.

### 7. Transporte HTTP de saída
- Interface `PerfexTestkit\Http\Transport` (`request(method, url, headers, body): Response`) e
  implementação `RecordingTransport`, com fila de respostas e registro de chamadas.
- A interface é só um contrato sugerido. Para usá-lo, o módulo aceita um objeto com o método
  `request` (duck typing), sem depender do kit em produção.
- O README documenta o padrão: a library HTTP do módulo recebe o transporte opcional no construtor
  e usa curl quando não recebe. É o padrão que o `Nfse_service` da Fase 1 deve seguir.

### 8. Versionamento, CI e distribuição
- **Versões:** SemVer `v0.x.y`, com tag móvel `v0` atualizada a cada release compatível; o CHANGELOG
  registra cada stub novo ou alterado.
- **Workflow reutilizável** `.github/workflows/php-unit.yml` (`workflow_call`):
  - input `php-versions` (JSON, padrão `["8.1"]`) e `working-directory`;
  - `shivammathur/setup-php`, cache do Composer, `composer install --no-interaction` e
    `composer test`;
  - `fail-fast: false`.
- **CI do kit:** `.github/workflows/ci.yml` em `pull_request`, matriz 8.1–8.5 (repositório público,
  minutos ilimitados).
- **Dependências:** `phpunit/phpunit` `^10.5 || ^11` em `require` (o kit é, por natureza, uma
  dependência de teste). PHP `>=8.1`.

### 9. Mesmo fluxo de trabalho dos módulos
- Branches `main`/`develop`, `feature/*` e PR com squash para `develop`; PR `develop` → `main` com
  merge e tag.
- Issue no GitHub antes de qualquer código.
- `CLAUDE.md` e `CONTRIBUTING.md` do kit com essas regras, sem as regras de migration, que não se
  aplicam.

## Risks / Trade-offs

- **[Stub divergir do Perfex real]** Assinatura ou retorno diferente mascara um bug.
  → Stubs espelham as assinaturas do Perfex 3.x; cada stub referencia a função original no docblock;
  a homologação e, depois, a L2 cobrem a diferença.
- **[Modo roteiro frágil]** Testes que dependem de SQL montado quebram com mudanças cosméticas na
  consulta.
  → Casamento por padrão (regex) sobre trechos relevantes; recomendação de mover consultas
  complexas para métodos de model pequenos, testados à parte.
- **[Stream wrapper `php`]** Pode interferir em outros usos de `php://` no processo.
  → Repasse dos demais caminhos ao wrapper nativo, restauração no `tearDown` e autoteste
  específico; fallback com processo separado.
- **[Mudança no kit quebrar módulos]** → SemVer; mudanças incompatíveis sobem o minor em `0.x`; os
  módulos fixam `^0.1` e atualizam de forma consciente.
- **[Escopo crescer sem limite]** → Stubs novos só entram quando um módulo consumidor precisa, com
  teste de autoverificação.

## Migration Plan

Repositório novo, sem migração. Publicação:

1. `feature/create-perfex-testkit` → PR para `develop`, com o CI do kit verde.
2. PR `develop` → `main`.
3. Tag `v0.1.0` e tag móvel `v0`.
4. A Fase 0b (`connect_asaas_nf`) passa a requerer `^0.1`.

Rollback: a Fase 0b fixa a versão, e uma release com problema é corrigida com `v0.1.1`.
