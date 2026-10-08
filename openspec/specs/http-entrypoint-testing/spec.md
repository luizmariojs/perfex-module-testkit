# http-entrypoint-testing Specification

## Purpose

Permite testar pontos de entrada HTTP dos módulos (webhooks públicos, endpoints AJAX) e o código
que chama APIs externas, sem servidor web e sem rede.

## Requirements

### Requirement: Requisição simulada
O kit SHALL permitir montar uma requisição com método, headers e corpo. O corpo MUST ficar
disponível ao código via `file_get_contents('php://input')`, e método e headers via `$_SERVER`
(com headers no formato `HTTP_*`). Parâmetros `$_GET`/`$_POST` MUST poder ser definidos e SHALL ficar acessíveis também por
`$this->input` (`post`, `get`, `method`, `is_ajax_request`, `get_request_header`), como nos
controllers do Perfex. Tudo MUST
ser restaurado ao fim do teste.

#### Scenario: Webhook lê o corpo
- **WHEN** o teste simula um `POST` com corpo JSON e o header `asaas-access-token`, e chama o método do controller
- **THEN** o controller lê o mesmo JSON de `php://input` e o token em `$_SERVER['HTTP_ASAAS_ACCESS_TOKEN']`

#### Scenario: Estado restaurado
- **WHEN** um teste simula uma requisição e o teste seguinte não simula nenhuma
- **THEN** o segundo teste não vê método, headers nem corpo do primeiro

### Requirement: Captura da resposta
O kit SHALL capturar o corpo emitido (`echo`/saída) e o código definido com `http_response_code()`
durante a chamada, e oferecer asserções sobre ambos.

#### Scenario: Resposta de não autorizado
- **WHEN** o controller chama `http_response_code(401)` e imprime `Unauthorized`
- **THEN** o teste verifica o código 401 e o corpo `Unauthorized`

### Requirement: Gravador de chamadas HTTP de saída
O kit SHALL fornecer um transporte HTTP falso que registra cada chamada (método, URL, headers,
corpo) e devolve respostas enfileiradas pelo teste (status, corpo). O transporte é usado pelo
código do módulo que aceita injeção de transporte. Chamada sem resposta enfileirada MUST falhar o
teste. O kit MUST NOT abrir conexão de rede.

#### Scenario: Resposta enfileirada
- **WHEN** o teste enfileira `200 {"id":"inv_1","status":"SCHEDULED"}` e o código faz um `POST /v3/invoices` pelo transporte injetado
- **THEN** o código recebe essa resposta, e o teste verifica a URL e o corpo enviados

#### Scenario: Chamada inesperada
- **WHEN** o código faz uma chamada sem resposta enfileirada
- **THEN** o teste falha informando método e URL da chamada

### Requirement: Limitações documentadas
A documentação do kit SHALL informar o que não pode ser interceptado e como contornar:
- chamadas diretas a `curl_*` sem ponto de injeção;
- `exit`/`die` no código testado;
- `header()` na CLI.

#### Scenario: Consulta à documentação
- **WHEN** um desenvolvedor lê o README do kit
- **THEN** encontra a seção de limitações com o padrão recomendado de injeção de transporte HTTP
