<?php

declare(strict_types=1);

namespace PerfexTestkit\Http;

/**
 * Resposta devolvida pelo transporte HTTP falso.
 */
final class HttpResponse
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly int $status,
        public readonly string $body = '',
        public readonly array $headers = [],
    ) {
    }

    public function json(): mixed
    {
        return json_decode($this->body, true);
    }
}
