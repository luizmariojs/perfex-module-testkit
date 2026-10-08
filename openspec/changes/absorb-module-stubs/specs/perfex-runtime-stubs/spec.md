## ADDED Requirements

### Requirement: Navegação e erros interrompem como exceções
`redirect()`, `show_404()` e `access_denied()` SHALL interromper o fluxo do código testado lançando,
respectivamente, `TestRedirect` (mensagem = destino), `TestNotFound` e `TestAccessDenied` (mensagem = permissão),
para que o teste verifique o desvio sem encerrar o PHPUnit.

#### Scenario: Redirecionamento
- **WHEN** o código chama `redirect(admin_url('x'))`
- **THEN** o teste recebe `TestRedirect` com o destino na mensagem

#### Scenario: Acesso negado
- **WHEN** o código chama `access_denied('meu_modulo')`
- **THEN** o teste recebe `TestAccessDenied` com `meu_modulo` na mensagem

### Requirement: Campos personalizados controláveis
`get_custom_field_value($relId, $slug, $fieldTo)` SHALL devolver o valor definido pelo teste para o par slug/relid e
string vazia quando nada foi definido.

#### Scenario: Valor definido
- **WHEN** o teste define o campo `customers_bairro` do cliente 10 como `Centro`
- **THEN** `get_custom_field_value(10, 'customers_bairro', 'customers')` devolve `Centro`

#### Scenario: Valor ausente
- **WHEN** o teste não define o campo
- **THEN** a função devolve string vazia

### Requirement: Área do cliente controlável
O teste SHALL poder simular um contato logado (cliente e permissões de contato) e consultar os itens de menu da área
do cliente registrados pelo módulo. Sem contato definido, `is_client_logged_in()` MUST devolver falso.

#### Scenario: Contato com permissão de faturas
- **WHEN** o teste simula o cliente 5 com a permissão `invoices`
- **THEN** `get_client_user_id()` devolve 5 e `has_contact_permission('invoices')` devolve verdadeiro

#### Scenario: Menu do cliente
- **WHEN** o módulo chama `add_theme_menu_item('notas', [...])`
- **THEN** o teste encontra o item `notas` no menu da área do cliente

### Requirement: Registro do módulo e permissões da equipe
`register_activation_hook()` e `register_deactivation_hook()` SHALL ser aceitos sem efeito, e
`register_staff_capabilities($feature, $config, $name)` SHALL acrescentar a permissão ao filtro `staff_permissions`,
como o core.

#### Scenario: Permissão registrada
- **WHEN** o módulo registra a capacidade `meu_modulo` com `view` e `create`
- **THEN** aplicar o filtro `staff_permissions` devolve `meu_modulo` com essas capacidades e o nome

### Requirement: Helpers de view determinísticos
Os helpers usados por views (`init_head`, `init_tail`, `html_escape`, `e`, `_dt`, `module_dir_url`,
`get_base_currency`, `format_invoice_status`, `form_open`, `form_close`, `form_hidden`) SHALL existir com saída fixa e
documentada, sem tema, sessão ou banco, para que o teste renderize uma view com `include`.

#### Scenario: Escape de HTML
- **WHEN** a view imprime `html_escape('<b>"x"</b>')`
- **THEN** a saída tem as entidades escapadas

#### Scenario: Status da fatura
- **WHEN** a view chama `format_invoice_status(2)`
- **THEN** a saída é um rótulo com o nome do status "Pago"

### Requirement: Gateway de pagamento capturado
O kit SHALL fornecer a classe base `App_gateway` com os métodos de configuração usados pelos gateways dos módulos e
`addPayment()` registrando o pagamento para asserção, sem banco.

#### Scenario: Pagamento registrado
- **WHEN** um gateway do módulo chama `addPayment(['amount' => 10, 'invoiceid' => 3])`
- **THEN** o teste encontra esse pagamento na lista de pagamentos do kit

## MODIFIED Requirements

### Requirement: Isolamento entre testes
Todo estado do kit (options, banco, instância CI, logs, alertas, hooks, usuário, requisição simulada, campos
personalizados, contato da área do cliente e seu menu, pagamentos de gateway) SHALL ser zerado antes de cada teste que
usa a classe base do kit.

#### Scenario: Option não vaza
- **WHEN** um teste grava uma option e o teste seguinte a lê
- **THEN** o teste seguinte recebe string vazia

#### Scenario: Campo personalizado não vaza
- **WHEN** um teste define um campo personalizado e o teste seguinte o lê
- **THEN** o teste seguinte recebe string vazia
