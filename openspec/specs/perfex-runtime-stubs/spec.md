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

### Requirement: Isolamento entre testes
Todo estado do kit (options, banco, instância CI, logs, alertas, hooks, usuário, requisição
simulada) SHALL ser zerado antes de cada teste que usa a classe base do kit.

#### Scenario: Option não vaza
- **WHEN** um teste grava uma option e o teste seguinte a lê
- **THEN** o teste seguinte recebe string vazia
