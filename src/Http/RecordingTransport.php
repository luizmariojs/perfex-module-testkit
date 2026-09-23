<?php

declare(strict_types=1);

namespace PerfexTestkit\Http;

use PerfexTestkit\TestkitError;
use PHPUnit\Framework\Assert;

/**
 * Transporte HTTP falso: devolve respostas enfileiradas pelo teste e registra cada chamada.
 * Nunca abre conexão de rede. Chamada sem resposta enfileirada falha o teste.
 */
final class RecordingTransport implements Transport
{
    /** @var list<HttpResponse> */
    private array $queue = [];

    /** @var list<array{method: string, url: string, headers: array<string, string>, body: mixed}> */
    private array $calls = [];

    /**
     * Enfileira uma resposta. Corpo array/objeto é serializado como JSON.
     *
     * @param array<string, string> $headers
     */
    public function queue(int $status, mixed $body = '', array $headers = []): self
    {
        $this->queue[] = new HttpResponse($status, is_string($body) ? $body : (string) json_encode($body), $headers);

        return $this;
    }

    public function request(string $method, string $url, array $headers = [], mixed $body = null): HttpResponse
    {
        $this->calls[] = ['method' => strtoupper($method), 'url' => $url, 'headers' => $headers, 'body' => $body];

        if ($this->queue === []) {
            throw new UnexpectedRequestException(sprintf(
                '[perfex-module-testkit] Chamada HTTP inesperada: %s %s (nenhuma resposta enfileirada com queue()).',
                strtoupper($method),
                $url
            ));
        }

        return array_shift($this->queue);
    }

    /** @return list<array{method: string, url: string, headers: array<string, string>, body: mixed}> */
    public function calls(): array
    {
        return $this->calls;
    }

    /** @return array{method: string, url: string, headers: array<string, string>, body: mixed}|null */
    public function lastCall(): ?array
    {
        return $this->calls === [] ? null : $this->calls[count($this->calls) - 1];
    }

    /**
     * Verifica que houve uma chamada com o método e a URL contendo $urlContains; $bodyCheck recebe o
     * corpo (decodificado de JSON quando possível) e deve retornar true.
     *
     * @param (callable(mixed): bool)|null $bodyCheck
     */
    public function assertSent(string $method, string $urlContains, ?callable $bodyCheck = null): void
    {
        foreach ($this->calls as $call) {
            if ($call['method'] !== strtoupper($method) || !str_contains($call['url'], $urlContains)) {
                continue;
            }
            $body = $call['body'];
            if (is_string($body)) {
                $decoded = json_decode($body, true);
                $body    = json_last_error() === JSON_ERROR_NONE ? $decoded : $body;
            }
            if ($bodyCheck === null || $bodyCheck($body)) {
                Assert::assertTrue(true);

                return;
            }
        }

        Assert::fail(sprintf(
            "Nenhuma chamada %s contendo \"%s\" corresponde. Chamadas feitas:\n%s",
            strtoupper($method),
            $urlContains,
            implode("\n", array_map(static fn ($c) => '  ' . $c['method'] . ' ' . $c['url'], $this->calls)) ?: '  (nenhuma)'
        ));
    }

    public function assertNothingSent(): void
    {
        Assert::assertSame([], $this->calls, 'Esperava nenhuma chamada HTTP.');
    }

    /** Respostas enfileiradas e não consumidas. */
    public function pending(): int
    {
        return count($this->queue);
    }
}

