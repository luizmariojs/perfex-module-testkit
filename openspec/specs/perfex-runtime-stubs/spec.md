# perfex-runtime-stubs Specification

## Purpose

Fornece aos testes de módulos do Perfex CRM um runtime Perfex/CodeIgniter 3 falso, determinístico
e controlável, para que o código do módulo seja carregado e executado sem instalação do Perfex.

## Requirements

### Requirement: Inicialização explícita
O kit SHALL ser ativado por uma chamada explícita de inicialização no bootstrap do módulo, que
recebe a raiz do módulo e define `BASEPATH`, `APPPATH` e `FCPATH`. `FCPATH` MUST apontar para um
diretório temporário descartável. Carregar o pacote pelo autoload, sem essa chamada, MUST NOT
definir nenhuma função global nem constante.

#### Scenario: Arquivo do módulo carregável
- **WHEN** o bootstrap inicializa o kit e carrega um arquivo do módulo que começa com `defined('BASEPATH') or exit`
- **THEN** o arquivo é carregado sem encerrar o processo

#### Scenario: Escrita em disco isolada
- **WHEN** o código do módulo grava arquivos sob `FCPATH`
- **THEN** os arquivos ficam no diretório temporário do kit, e não no repositório do módulo

### Requirement: Stubs não sobrescrevem definições existentes
Cada função global do kit SHALL ser definida apenas se ainda não existir, para que helpers reais do
módulo, carregados antes, prevaleçam.

#### Scenario: Helper do módulo com o mesmo nome
- **WHEN** o módulo carrega um helper próprio que define uma função também fornecida pelo kit
- **THEN** a versão do módulo é usada e nenhum erro de redeclaração ocorre

### Requirement: Options controláveis
`get_option`, `update_option`, `add_option` e `delete_option` SHALL operar sobre um armazenamento em
memória. O teste MUST poder pré-definir valores e verificar o que foi gravado. Option inexistente
MUST retornar string vazia, como no Perfex.

#### Scenario: Pré-definir option
- **WHEN** o teste define a option `regime` = `4` e o código chama `get_option('regime')`
- **THEN** o retorno é `4`

#### Scenario: Option inexistente
- **WHEN** o código lê uma option nunca definida
- **THEN** o retorno é string vazia

#### Scenario: Verificar gravação
- **WHEN** o código chama `update_option('x', 'y')`
- **THEN** o teste consegue verificar que `x` vale `y`

### Requirement: Instância CI e loader
`get_instance()` SHALL devolver uma instância única por teste, com `db`, `load` e as propriedades
anexadas pelo loader. O loader SHALL carregar models, libraries e helpers do módulo a partir da
raiz informada (`load->model('modulo/nome_model')` cria `$CI->nome_model`). Componentes que não
são do módulo, como os models nativos do Perfex, MUST poder ser registrados como dublês. Pedir um
model ou library não resolvível MUST falhar com mensagem que nomeia o componente. Helpers nativos do
Perfex/CI (sem o prefixo do módulo) SHALL ser aceitos sem efeito, já que suas funções vêm dos stubs.

#### Scenario: Model do módulo
- **WHEN** o código chama `$this->load->model('connect_asaas_nf/connect_asaas_nf_model')`
- **THEN** a classe do arquivo `models/Connect_asaas_nf_model.php` do módulo fica disponível em `$CI->connect_asaas_nf_model`

#### Scenario: Model nativo registrado como dublê
- **WHEN** o teste registra um dublê para `invoices_model` e o código o carrega
- **THEN** o código recebe o dublê

#### Scenario: Componente desconhecido
- **WHEN** o código carrega um model que não existe no módulo e não foi registrado
- **THEN** o teste falha com erro que informa o nome do model

### Requirement: Classes base do Perfex/CI3
O kit SHALL fornecer `CI_Controller`, `CI_Model`, `App_Model`, `AdminController` e
`App_module_migration`. Todas MUST expor as propriedades da instância CI (`db`, `load`, componentes
carregados) como no CodeIgniter. `AdminController` MUST NOT exigir sessão ou login.

#### Scenario: Model acessa o banco
- **WHEN** uma classe que estende `App_Model` usa `$this->db`
- **THEN** ela usa o banco falso da instância atual

#### Scenario: Controller acessa componente carregado
- **WHEN** um controller carrega um model no construtor e depois usa `$this->nome_model`
- **THEN** o mesmo objeto carregado é retornado

### Requirement: Funções utilitárias determinísticas
O kit SHALL fornecer versões determinísticas das funções mais usadas pelos módulos:
- `_l` devolve a chave (com `sprintf` dos argumentos, quando houver);
- `db_prefix` devolve `tbl`;
- `admin_url`, `site_url` e `base_url` montam URLs sob uma base fixa;
- `app_format_money`, `format_invoice_number` e `_d` têm formatação fixa e documentada;
- `get_staff_user_id`, `is_admin`, `has_permission` e `staff_can` são configuráveis pelo teste.

#### Scenario: Tradução
- **WHEN** o código chama `_l('minha_chave')`
- **THEN** o retorno é `minha_chave`

#### Scenario: Permissão negada
- **WHEN** o teste configura o usuário sem a permissão `x` e o código consulta `staff_can('view', 'x')`
- **THEN** o retorno é falso

### Requirement: Efeitos colaterais capturados
`log_activity`, `set_alert` e o registro de hooks (`hooks()->add_action`, `add_filter`) SHALL ser
capturados para asserção. O teste MUST poder disparar uma ação ou aplicar um filtro registrado.

#### Scenario: Log registrado
- **WHEN** o código chama `log_activity('mensagem')`
- **THEN** o teste consegue verificar que `mensagem` foi registrada

#### Scenario: Disparar hook registrado
- **WHEN** o arquivo principal do módulo registra um callback em `after_invoice_added` e o teste dispara essa ação com um id
- **THEN** o callback do módulo é executado com esse id

#### Scenario: Arquivo principal carregado em vários testes
- **WHEN** dois testes carregam o arquivo principal do módulo pelo kit
- **THEN** ambos encontram os hooks do módulo registrados, sem redeclarar as funções do arquivo

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
