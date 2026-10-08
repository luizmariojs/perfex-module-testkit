## Why

O `connect_asaas_nf` mantém 24 stubs próprios em `tests/bootstrap.php` e o `connect_asaas` um `App_gateway` em
`tests/stubs/` — todos funções e classes do Perfex/CI3 que qualquer módulo `connect_*` usa. Os do NF guardam estado
em `$GLOBALS`, fora do `State` do kit, e por isso não são zerados entre testes. A refatoração completa do
`connect_asaas`, que começa a seguir, vai depender desses mesmos stubs (issue #5).

## What Changes

- Novos stubs de navegação e erro: `redirect()`, `show_404()` e `access_denied()` lançam exceções do kit
  (`TestRedirect`, `TestNotFound`, `TestAccessDenied`) com o destino ou a permissão na mensagem.
- `get_custom_field_value()` lendo valores definidos pelo teste (`Testkit::customField()`).
- Área do cliente: `is_client_logged_in()`, `get_client_user_id()`, `has_contact_permission()`,
  `redirect_after_login_to_current_url()` e `add_theme_menu_item()`, controlados por `Testkit::actingAsContact()` e
  consultados por `Testkit::clientMenu()`.
- Registro do módulo: `register_activation_hook()`, `register_deactivation_hook()` (sem efeito) e
  `register_staff_capabilities()` (aplica o filtro `staff_permissions`, como o core).
- Render de views: `init_head()`, `init_tail()`, `html_escape()`, `e()`, `_dt()`, `module_dir_url()`,
  `get_base_currency()`, `format_invoice_status()`, `form_open()`, `form_close()`, `form_hidden()`.
- Classe `App_gateway` mínima, com os pagamentos de `addPayment()` consultáveis por `Testkit::payments()`.
- Todo estado novo em `State`, zerado por `PerfexTestCase::setUp()`.
- **BREAKING (0.x → minor 0.2.0):** módulos que definem esses stubs localmente passam a receber os do kit (carregados
  antes); testes que gravavam em `$GLOBALS['cf']`/`$GLOBALS['client_area']` ou em `App_gateway::$payments` migram
  para as fachadas.

## Capabilities

### New Capabilities

(nenhuma)

### Modified Capabilities

- `perfex-runtime-stubs`: novos stubs (navegação/erros, campos personalizados, área do cliente, registro do módulo,
  helpers de view, `App_gateway`) e isolamento cobrindo o estado novo.

## Impact

- `stubs/functions.php`, `stubs/classes.php`, `src/State.php`, `src/Testkit.php`; autotestes em `tests/Unit/`.
- `README.md` (exemplos testados por `ReadmeExamplesTest`), `CHANGELOG.md`, tag `v0.2.0` e `v0` movida.
- Consumidores: `connect_asaas_nf` (#225 daquele repositório) e `connect_asaas` adotam `^0.2` em mudanças próprias.
