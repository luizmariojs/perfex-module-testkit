## Purpose

Disponibiliza um workflow do GitHub Actions reutilizável que roda a bateria de testes de qualquer
módulo em matriz de versões do PHP, para que cada módulo adote o CI com poucas linhas e sem
segredos.

## ADDED Requirements

### Requirement: Workflow chamável por outros repositórios
O repositório SHALL publicar um workflow com gatilho `workflow_call` que:
1. faz checkout do repositório chamador;
2. instala o PHP de cada versão da matriz;
3. usa cache do Composer, executa `composer install` e em seguida `composer test`.

As versões do PHP MUST ser um input, com padrão `["8.1"]`. O workflow MUST funcionar em
repositórios chamadores privados sem secrets, porque o kit é público.

#### Scenario: Módulo privado chama o workflow
- **WHEN** um módulo privado declara um job `uses: luizmariojs/perfex-module-testkit/.github/workflows/php-unit.yml@v0` com `php-versions: '["8.1","8.3"]'`
- **THEN** a bateria do módulo roda nas duas versões e cada uma aparece como verificação no PR

#### Scenario: Falha em uma versão
- **WHEN** a bateria falha em uma das versões
- **THEN** o job daquela versão fica vermelho e as demais versões continuam executando (`fail-fast: false`)

### Requirement: Referência estável por versão maior
O workflow SHALL poder ser referenciado por uma tag móvel de versão maior (`v0`), atualizada a cada
release compatível, e por tags imutáveis (`v0.1.0`).

#### Scenario: Release compatível
- **WHEN** uma versão `v0.1.1` é publicada
- **THEN** a tag `v0` passa a apontar para ela, e os módulos que usam `@v0` recebem a atualização sem mudar o arquivo

### Requirement: CI do próprio kit
O repositório do kit SHALL rodar a própria bateria de autoteste em todo pull request, em todas as
versões de PHP suportadas (8.1 até a mais recente estável).

#### Scenario: PR no kit
- **WHEN** um PR é aberto no repositório do kit
- **THEN** a bateria do kit roda em todas as versões suportadas e o resultado aparece no PR
