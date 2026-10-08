## Context

- Os stubs atuais leem e gravam só em `State`, zerado por `PerfexTestCase::setUp()`; a fachada `Testkit` é a única
  API para os testes (`State` é `@internal`).
- As versões locais do `connect_asaas_nf` (`tests/bootstrap.php`) e do `connect_asaas` (`tests/stubs/App_gateway.php`)
  estão em produção de testes há semanas (425 e ~20 testes) e servem de referência de comportamento.
- Os stubs do kit são carregados por `Testkit::boot()` **antes** do bootstrap do módulo; com `function_exists`, o do
  kit prevalece sobre o local.

## Goals / Non-Goals

**Goals:**
- Os dois consumidores ficam sem stubs próprios, com o mesmo comportamento de hoje.
- Estado novo no `State`, nunca em `$GLOBALS`.

**Non-Goals:**
- Stubs que nenhum consumidor usa hoje (a refatoração do `connect_asaas` pedirá os seus, com issue própria).
- Renderizar views pelo `load->view()` do controller (os testes incluem a view diretamente).

## Decisions

**D1 — Fachadas novas em `Testkit`:** `customField(string $slug, int|string $relId, mixed $value)`,
`actingAsContact(int|false $clientId, array $permissions = [])`, `clientMenu(): array`, `payments(): array`.
*Alternativa:* expor `$GLOBALS` como hoje. Rejeitada — não é zerado e contraria a convenção do kit.

**D2 — Exceções em `stubs/classes.php`, sem namespace:** `TestRedirect`, `TestNotFound`, `TestAccessDenied`
(estendem `RuntimeException`), com os mesmos nomes já usados pelo NF, para a migração não tocar nos `catch`.

**D3 — Saídas fixas idênticas às do NF** (`html_escape` com `ENT_QUOTES`, `format_invoice_status` com rótulos em pt-BR
e classe `invoice-status-N`, `module_dir_url` = `/modules/<m>/<seg>`, `get_base_currency` = BRL/R$): os asserts
existentes dos consumidores continuam válidos.

**D4 — `App_gateway::$payments` deixa de ser público:** `addPayment()` grava em `State::$payments`; o teste lê por
`Testkit::payments()`. Os métodos de configuração (`setId`, `setName`, `setSettings`, `getSetting`) continuam sem
efeito.

**D5 — Versão 0.2.0** (minor em 0.x por mudar o que o módulo recebe e exigir migração de testes); tag `v0` movida.

## Risks / Trade-offs

- [Consumidor com stub local de mesmo nome passa a usar o do kit, com comportamento levemente diferente] → D3 copia o
  comportamento atual; a adoção em cada módulo roda a suíte completa antes do merge.
- [Workflow reutilizável usa `v0` e pega a 0.2.0 assim que a tag mover] → os consumidores fixam `^0.1` no
  `composer.lock` até adotarem `^0.2` em mudança própria; a tag `v0` só move depois que o NF adotar e ficar verde.

## Migration Plan

1. Kit: implementar, autotestes, README/CHANGELOG, PR para `develop`, release `v0.2.0` (sem mover `v0` ainda).
2. `connect_asaas_nf` (#225): `^0.2`, remove stubs locais, troca `$GLOBALS` por fachadas; suíte verde.
3. Mover `v0` para `v0.2.0`. O `connect_asaas` adota no início da sua refatoração.
