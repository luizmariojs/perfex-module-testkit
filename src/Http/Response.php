<?php

declare(strict_types=1);

namespace PerfexTestkit\Http;

/**
 * Resposta capturada por Testkit::call(): código de http_response_code() e saída emitida.
 */
final class Response
{
    public function __construct(
        public readonly int $status,
        public readonly string $body,
    ) {
    }

    public function json(): mixed
    {
        return json_decode($this->body, true);
    }
}
