<?php

declare(strict_types=1);

namespace PerfexTestkit\Http;

/**
 * Contrato sugerido para o transporte HTTP de saída de um módulo.
 *
 * O módulo NÃO precisa depender do kit: basta que a sua library HTTP aceite, opcionalmente, um objeto
 * com um método `request($method, $url, $headers, $body)` que devolva algo com `status` e `body`
 * (duck typing). Em produção a library usa curl; no teste recebe um {@see RecordingTransport}.
 */
interface Transport
{
    /**
     * @param array<string, string> $headers
     */
    public function request(string $method, string $url, array $headers = [], mixed $body = null): HttpResponse;
}
