## 0. Pré-requisitos

- [x] 0.1 Issue #5 aberta; branch `feature/absorb-module-stubs` a partir da `develop` atualizada. Verificar: `git status`
      limpo na branch

## 1. Testes primeiro

- [x] 1.1 Autotestes em `tests/Unit/` para cada requisito novo (navegação/erros, campo personalizado, área do cliente,
      registro do módulo, helpers de view, `App_gateway`) e para o isolamento do estado novo. Verificar: falham antes
      da implementação

## 2. Implementação

- [x] 2.1 Estado novo em `State` (`customFields`, `contact`, `contactPermissions`, `clientMenu`, `payments`) zerado
      em `reset()`; fachadas `Testkit::customField()`, `actingAsContact()`, `clientMenu()`, `payments()` (D1, D4).
      Verificar: testes de isolamento verdes
- [x] 2.2 Stubs em `stubs/functions.php` e exceções + `App_gateway` em `stubs/classes.php`, com `function_exists`/
      `class_exists` e docblock citando a função original (D2, D3). Verificar: 1.1 verde
- [x] 2.3 README com a seção dos stubs novos (exemplos cobertos por `ReadmeExamplesTest`) e `CHANGELOG.md` [0.2.0].
      Verificar: suíte completa verde em PHP 8.1

## 3. Validação com os consumidores

- [x] 3.1 `connect_asaas_nf` apontando para a branch do kit: suíte de 425 testes verde sem os stubs locais (feito na
      mudança #225 daquele repositório). Verificar: `vendor/bin/phpunit` verde lá
- [x] 3.2 `connect_asaas` apontando para a branch do kit, sem `tests/stubs/App_gateway.php`: suíte verde. Verificar:
      `vendor/bin/phpunit` verde lá (sem commit no `connect_asaas`; a adoção fica para a refatoração dele)

## 4. Entrega

- [x] 4.1 Commit `Refs #5`, PR para `develop` (squash), CI verde
- [x] 4.2 PR `develop` → `main` (`Closes #5`), tag `v0.2.0`; mover `v0` só depois do 3.1 mesclado no NF
