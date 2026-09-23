## Why

Nenhum dos nossos módulos do Perfex CRM tem testes automatizados:
`connect_asaas`, `connect_asaas_nf`, `connect_envios`, `connect_finance`, `connect_triggers` e
`connect_essential_tools`. O motivo é o mesmo em todos: o código pressupõe o CodeIgniter 3 e o
Perfex carregados (`get_instance()`, `get_option()`, `$this->db`, `App_Model`, `hooks()`…), e montar
esse ambiente falso em cada módulo seria trabalho repetido e divergente.

Este repositório é a **Fase 0a** do roteiro decidido em 23/09/2026 (ver
`connect_asaas_nf/openspec/changes/adopt-unit-test-suite/proposal.md`, decisões T1–T5). Ele entrega
**uma** base de testes, versionada e reutilizável. O primeiro consumidor é o `connect_asaas_nf`
(Fase 0b); os demais módulos adotam a mesma base depois.

## What Changes

- **Pacote Composer** `luizmariojs/perfex-module-testkit` (PHP ≥ 8.1, PHPUnit 10.5+), consumido
  pelos módulos apenas como `require-dev`.
- **Stubs do runtime Perfex/CI3**, definidos só quando ainda não existem:
  - constantes `BASEPATH`, `APPPATH` e `FCPATH`;
  - `get_instance()` com loader que carrega models, libraries e helpers do próprio módulo;
  - classes base `CI_Controller`, `CI_Model`, `App_Model`, `AdminController` e
    `App_module_migration`;
  - options (`get_option`/`update_option`/`add_option`/`delete_option`) em memória;
  - `_l`, `db_prefix`, URLs, permissões, usuário logado, alertas e `log_activity` capturados;
  - `hooks()` que registra ações e filtros e permite dispará-los no teste;
  - helpers de formatação mais usados, com saída determinística.
- **Banco de dados falso** compatível com o query builder do CI3: tabelas em memória para
  CRUD simples e respostas programadas para SQL cru e consultas complexas, com registro de toda
  operação para asserções.
- **Testes de pontos de entrada HTTP:**
  - requisição simulada (método, headers, corpo em `php://input`);
  - captura do código de resposta e do corpo;
  - gravador de chamadas HTTP de saída com respostas enfileiradas, para módulos que injetam o
    transporte.
- **`PerfexTestCase`**: classe base do PHPUnit que zera todo o estado entre testes e oferece
  asserções prontas (option gravada, log registrado, linha no banco, hook registrado).
- **Workflow reutilizável do GitHub Actions** (`php-unit.yml`, `workflow_call`) com matriz de
  versões do PHP e cache do Composer, que qualquer módulo chama com poucas linhas.
- **Autoteste do kit** e CI próprio no repositório, que é público: minutos de Actions ilimitados.
- **Documentação de adoção**: README com o passo a passo para um módulo novo e o padrão de
  "ponto de injeção de HTTP" para código novo.

## Capabilities

### New Capabilities
- `perfex-runtime-stubs`: ambiente Perfex/CI3 falso e controlável (constantes, funções globais,
  classes base, loader, options, hooks, permissões, captura de logs), com estado zerado entre
  testes.
- `fake-database`: banco em memória compatível com o query builder do CI3, com respostas
  programadas para SQL cru e registro de operações.
- `http-entrypoint-testing`: simulação de requisições a controllers (webhooks, AJAX), captura da
  resposta e gravação de chamadas HTTP de saída.
- `reusable-ci-workflow`: workflow do GitHub Actions reutilizável pelos módulos, com matriz de PHP.

### Modified Capabilities
<!-- Nenhuma: repositório novo. -->

## Impact

- **Repositório novo e público:** `github.com/luizmariojs/perfex-module-testkit`, com branches
  `main` e `develop`. Não contém código do Perfex CRM nem segredos.
- **Consumidores:** `connect_asaas_nf` na Fase 0b; os outros cinco módulos em changes próprias,
  quando for conveniente.
- **Versionamento:** SemVer com tags `v0.x.y` e uma tag móvel `v0` para o workflow reutilizável.
  Mudança incompatível nos stubs exige bump de minor enquanto estiver em `0.x`.
- **Sem efeito em produção:** só é instalado como dependência de desenvolvimento e nunca entra nos
  ZIPs dos módulos.
- **Processo:** o mesmo fluxo dos módulos (issue no GitHub → `feature/*` → PR para `develop` →
  PR para `main` + tag).
