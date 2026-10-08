## 1. Processo e repositório

- [x] 1.1 Abrir no GitHub do kit a issue "Criar perfex-module-testkit v0.1.0" com os critérios de validação iguais aos requisitos das 4 specs. Verificar: `gh issue list -R luizmariojs/perfex-module-testkit` mostra a issue `#N`
- [x] 1.2 Criar a branch `feature/create-perfex-testkit` a partir de `develop`. Verificar: `git branch --show-current`
- [x] 1.3 Adicionar `CLAUDE.md` e `CONTRIBUTING.md` com o fluxo (issue → feature → PR develop → PR main + tag, commits em português, sem co-autoria) e a regra de versionamento SemVer/`v0`. Verificar: arquivos no diff
- [x] 1.4 Adicionar `LICENSE` (MIT, salvo decisão contrária do usuário) e versionar o diretório `openspec/`. Verificar: arquivos no diff

## 2. Pacote base

- [x] 2.1 Criar `composer.json` (`luizmariojs/perfex-module-testkit`, PHP `>=8.1`, `phpunit/phpunit` `^10.5 || ^11`, PSR-4 `PerfexTestkit\` → `src/`, script `test`), sem `autoload.files`. Verificar: `composer validate --strict` e `composer install` sem erro
- [x] 2.2 Criar `phpunit.xml.dist` e `tests/bootstrap.php` do autoteste. Verificar: `composer test` roda (0 testes, sem erro)
- [x] 2.3 Implementar `PerfexTestkit\State` (registro único) e `PerfexTestkit\Testkit::boot($moduleRoot, $options)`, que define `BASEPATH`, `APPPATH` e `FCPATH` (diretório temporário) e carrega `stubs/`. Verificar: autoteste confirma que, antes do `boot`, nenhuma constante nem função do Perfex existe e que, depois dele, existem
- [x] 2.4 Implementar `PerfexTestkit\PerfexTestCase` com `State::reset()` no `setUp` e restauração no `tearDown`. Verificar: autoteste com dois testes em sequência confirma que options e banco não vazam

## 3. Stubs do runtime (`perfex-runtime-stubs`)

- [x] 3.1 Options: `get_option`, `update_option`, `add_option`, `delete_option` com guarda `function_exists`, mais a API `Testkit::option()` e a asserção `assertOptionEquals`. Verificar: autotestes dos cenários da spec (pré-definir, inexistente → `''`, gravação)
- [x] 3.2 Utilitárias: `_l` (chave + `sprintf`), `db_prefix`, `admin_url`, `site_url`, `base_url`, `module_dir_path`, `app_format_money`, `format_invoice_number`, `_d`, com formatos documentados no docblock. Verificar: autotestes com saídas esperadas
- [x] 3.3 Usuário e permissões: `get_staff_user_id`, `is_admin`, `has_permission`, `staff_can`, configuráveis por `Testkit::actingAs(id, permissões, admin)`. Verificar: autoteste de permissão negada e concedida
- [x] 3.4 Capturas: `log_activity`, `set_alert` e `hooks()` (`add_action`, `add_filter`, `do_action`, `apply_filters`), com `Testkit::fireAction()`/`applyFilter()` e as asserções `assertLogged`, `assertAlert` e `assertHookRegistered`. Verificar: autoteste registra um callback e o dispara
- [x] 3.5 Instância CI e loader: `get_instance()`, loader de `model`/`library`/`helper` pela raiz do módulo, `Testkit::double()` e `UnresolvedComponentException`. Verificar: autoteste com um módulo de exemplo em `tests/fixtures/sample_module/` (model, library, helper) e o caso de componente desconhecido
- [x] 3.6 Classes base: `CI_Controller` (registra-se como instância), `CI_Model`, `App_Model`, `AdminController` e `App_module_migration` com delegação por `__get`. Verificar: autoteste com controller de exemplo que carrega um model no construtor e o usa depois
- [x] 3.7 Guarda contra redeclaração: autoteste carrega um helper de exemplo que define `_l` antes do `boot` e confirma que a versão do helper prevalece

## 4. Banco falso (`fake-database`)

- [x] 4.1 Declaração de schema e dados (`table()`), `table_exists`, `field_exists` e `list_fields`. Verificar: autoteste do cenário "coluna de outro módulo ausente"
- [x] 4.2 Modo tabela: `select`, `from`, `where` (igualdade e operadores), `where_in`, `or_where`, `like`, `order_by`, `limit`, `get`, `get_where`, `count_all_results`, com reset do builder após a execução. Verificar: autotestes dos cenários "inserir e ler" e "builder limpo"
- [x] 4.3 Escritas: `insert` (+ `insert_id`), `update` (+ `set`), `delete` e `affected_rows`. Verificar: autoteste "atualizar com filtro"
- [x] 4.4 Resultados: `row`, `row_array`, `result`, `result_array` e `num_rows`, com os casos vazios. Verificar: autoteste "nenhuma linha"
- [x] 4.5 Modo roteiro: `query()` cru e builder com `join`/`group_by`/`select_sum`/`group_start`/`having` montando SQL aproximado; `respond($pattern, $rows)`; falha com o SQL quando não houver resposta. Verificar: autotestes "SQL cru programado" e "consulta não programada"
- [x] 4.6 Registro de operações e asserções: `assertRowExists`, `assertNoWrites` e `assertQueried`. Verificar: autotestes dos dois cenários da spec

## 5. HTTP (`http-entrypoint-testing`)

- [x] 5.1 Requisição simulada: `Testkit::request(method, headers, body, get, post)` preenchendo `$_SERVER` (`HTTP_*`), `$_GET`/`$_POST`, e stream wrapper de `php://input` com repasse dos outros caminhos `php://`. Verificar: autoteste lê o corpo via `file_get_contents('php://input')`; `php://memory` e `php://temp` continuam funcionando
- [x] 5.2 Restauração no `tearDown`: autoteste confirma que o teste seguinte não vê a requisição anterior e que o wrapper nativo foi restaurado
- [x] 5.3 Captura da resposta: `Testkit::call(callable)` → objeto com `status` e `body`, e as asserções `assertStatus`/`assertBody`. Verificar: autoteste com controller de exemplo respondendo 401 `Unauthorized`
- [x] 5.4 Transporte de saída: interface `Http\Transport`, `RecordingTransport` com fila, registro e falha em chamada inesperada. Verificar: autotestes "resposta enfileirada" e "chamada inesperada"

## 6. CI e documentação (`reusable-ci-workflow`)

- [x] 6.1 Criar `.github/workflows/php-unit.yml` (`workflow_call`, inputs `php-versions` e `working-directory`, `setup-php`, cache do Composer, `fail-fast: false`). Verificar: `actionlint` (se disponível) ou execução real na tarefa 6.3
- [x] 6.2 Criar `.github/workflows/ci.yml` (`pull_request`, matriz 8.1–8.5) rodando o autoteste. Verificar: no PR desta change, as 5 verificações aparecem verdes
- [x] 6.3 Validar o workflow reutilizável de ponta a ponta: um job em `ci.yml` que chama `./.github/workflows/php-unit.yml` sobre o próprio kit. Verificar: job verde no PR
- [x] 6.4 Escrever o README: instalação (`repositories` vcs + `require-dev`), bootstrap mínimo, exemplo de teste de model, de webhook e de transporte HTTP, workflow de 5 linhas para o módulo, limitações (`curl_*`, `exit`/`die`, `header()`) e o padrão de injeção de transporte. Verificar: exemplos do README copiados para `tests/Docs/ReadmeExamplesTest.php` passam
- [x] 6.5 Criar `CHANGELOG.md` com a entrada `0.1.0`. Verificar: arquivo no diff

## 7. Publicação

- [x] 7.1 Abrir o PR `feature/create-perfex-testkit` → `develop` (`Refs #N`) e mesclar com squash só com o CI verde. Verificar: PR mesclado
- [x] 7.2 Abrir o PR `develop` → `main` (`Closes #N`), mesclar com merge, criar a tag `v0.1.0` e a tag móvel `v0` e fazer push. Verificar: `git ls-remote --tags origin` mostra `v0.1.0` e `v0`, e a issue está fechada
- [x] 7.3 Teste de consumo: num diretório temporário, um `composer.json` com `repositories` vcs do kit e `require-dev ^0.1`; `composer install` resolve `v0.1.0`. Verificar: `composer show luizmariojs/perfex-module-testkit` mostra 0.1.0
- [x] 7.4 Marcar como concluído o pré-requisito 1.2 da change `adopt-unit-test-suite` no `connect_asaas_nf`. Verificar: checkbox marcado
