# Changelog — perfex-module-testkit

Formato baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/); versões seguem
[SemVer](https://semver.org/lang/pt-BR/). Em `0.x`, mudanças incompatíveis sobem o minor.

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
