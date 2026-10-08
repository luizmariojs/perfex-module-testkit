# Changelog — perfex-module-testkit

Formato baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/); versões seguem
[SemVer](https://semver.org/lang/pt-BR/). Em `0.x`, mudanças incompatíveis sobem o minor.

## [0.2.0] - 2026-10-08

Stubs absorvidos dos módulos consumidores (issue #5): o `connect_asaas_nf` mantinha 24 em `tests/bootstrap.php` (com
estado em `$GLOBALS`) e o `connect_asaas` um `App_gateway` em `tests/stubs/`.

### Adicionado
- **Navegação e erros:** `redirect()`, `show_404()` e `access_denied()` lançam `TestRedirect` (mensagem = destino),
  `TestNotFound` e `TestAccessDenied` (mensagem = permissão).
- **Campos personalizados:** `get_custom_field_value()` + `Testkit::customField($slug, $relId, $valor)`.
- **Área do cliente:** `is_client_logged_in()`, `get_client_user_id()`, `has_contact_permission()`,
  `redirect_after_login_to_current_url()`, `add_theme_menu_item()` + `Testkit::actingAsContact()` e
  `Testkit::clientMenu()`.
- **Registro do módulo:** `register_activation_hook()`, `register_deactivation_hook()` (sem efeito) e
  `register_staff_capabilities()` (filtro `staff_permissions`, como o core).
- **Render de views:** `init_head`, `init_tail`, `html_escape`, `e`, `_dt`, `module_dir_url`, `get_base_currency`,
  `format_invoice_status`, `form_open`, `form_close`, `form_hidden`, com saída fixa (ver README).
- **Classe `App_gateway`** mínima; `addPayment()` registrado em `Testkit::payments()`.
- Isolamento: campos personalizados, contato, menu do cliente e pagamentos zerados a cada teste.

### Alterado (incompatível em 0.x)
- Módulos que definiam esses stubs localmente passam a receber os do kit (carregados antes). Testes que gravavam em
  `$GLOBALS['cf']`, `$GLOBALS['client_area']` ou `App_gateway::$payments` migram para as fachadas.

## [0.1.0] - 2026-09-23

Primeira versão (Fase 0a do roteiro de testes dos módulos Perfex, issue #1).

### Adicionado
- `Testkit::boot()`: inicialização explícita. Define `BASEPATH`, `APPPATH`, `FCPATH` (diretório
  temporário), `APP_MODULES_PATH` e `ENVIRONMENT` e carrega os stubs; o autoload sozinho não define
  nada no escopo global.
- **Stubs de funções**, todas protegidas por `function_exists`:
  - instância e hooks: `get_instance()` (por referência) e `hooks()`;
  - options: `get_option`, `update_option`, `add_option`, `delete_option`;
  - tradução, banco e URLs: `_l`, `db_prefix`, `base_url`, `site_url`, `admin_url`,
    `module_dir_path`;
  - formatação fixa: `app_format_money`, `format_invoice_number`, `_d`;
  - usuário e permissões: `get_staff_user_id`, `is_staff_logged_in`, `is_admin`, `has_permission`,
    `staff_can`;
  - efeitos capturados: `log_activity`, `set_alert`.
- **Stubs de classes:** `CI_Controller` (vira a instância CI), `App_Controller`,
  `AdminController`, `ClientsController`, `CI_Model`, `App_Model` e `App_module_migration`.
- **Loader** (`load->model/library/helper`) que resolve pela raiz do módulo, com
  `Testkit::double()` para componentes nativos e `UnresolvedComponentException`.
- **`$this->input` mínimo** (`post`, `get`, `method`, `is_ajax_request`, `get_request_header`...).
- **Hooks** com prioridade e `accepted_args`. `Testkit::fireAction()`/`applyFilter()`, e
  `Testkit::load()`, que reaplica os hooks de um arquivo nos testes seguintes.
- **Banco falso** `FakeDatabase`:
  - modo tabela (CRUD em memória, semântica próxima do MySQL);
  - modo roteiro (`respond()`) para SQL cru e consultas complexas;
  - registro de operações e checagem de colunas nas escritas.
- **HTTP:**
  - `Testkit::request()` com stream wrapper de `php://input` (demais caminhos `php://` repassados
    ao nativo);
  - `Testkit::call()` captura código e corpo da resposta;
  - `RecordingTransport` para chamadas de saída.
- **`PerfexTestCase`:** estado zerado por teste, falha em violações engolidas pelo código testado
  e asserções de options, log, alertas, hooks, HTTP e banco.
- **Workflow reutilizável** `.github/workflows/php-unit.yml` e CI do kit (PHP 8.1–8.5).
- Módulo de exemplo (`tests/fixtures/sample_module`) e exemplos do README verificados por teste.
