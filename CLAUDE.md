# CLAUDE.md — perfex-module-testkit

Kit compartilhado de testes (PHPUnit + stubs Perfex/CodeIgniter 3) para os módulos Perfex CRM
`connect_*`. Consumido pelos módulos apenas como `require-dev` — nunca entra nos ZIPs.

## Regras

- **Nenhum código sem issue GitHub aberta** neste repositório. Correção colateral inseparável:
  documentar no commit e abrir a issue retroativamente antes do push.
- Trabalho sempre em `feature/*` a partir de `develop`; nunca commitar direto em `develop`/`main`.
- Commits e PRs em português, **sem co-autoria do Claude**. Referenciar `Refs #N` (branch/PR para
  `develop`) e `Closes #N` só no PR `develop` → `main`.
- Planejamento via OpenSpec (`openspec/changes/<change>/`), em pt-BR.
- Repositório **público**: nunca incluir código do Perfex CRM, credenciais, dados de clientes ou
  URLs de instâncias reais.

## Convenções do código

- Classes em `src/`, namespace `PerfexTestkit\` (PSR-4).
- Stubs globais em `stubs/`, sem namespace, cada função com `if (!function_exists(...))` e docblock
  citando a função original do Perfex/CI3.
- Stubs só são carregados por `PerfexTestkit\Testkit::boot()` — nunca por `autoload.files`.
- Todo estado passa por `PerfexTestkit\State` e é zerado por `PerfexTestCase::setUp()`.
- Stub novo só entra quando um módulo consumidor precisa, sempre com autoteste em `tests/`.
- PHP mínimo 8.1 (sem recursos de 8.2+ no código do kit).

## Versionamento

- SemVer `v0.x.y`. Em `0.x`, mudança incompatível em stub/API sobe o **minor**; correções sobem o patch.
- A cada release: tag imutável `v0.x.y` + mover a tag `v0` (usada pelos módulos no workflow reutilizável):
  ```bash
  git tag -a v0.1.0 -m "perfex-module-testkit v0.1.0"
  git tag -f v0 && git push origin v0.1.0 && git push -f origin v0
  ```
- Registrar toda mudança no `CHANGELOG.md`.
