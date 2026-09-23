<?php

declare(strict_types=1);

namespace PerfexTestkit;

/**
 * Substituto mínimo de CI_Input ($this->input), lendo as superglobais preenchidas por Testkit::request().
 */
final class Input
{
    public function post(?string $index = null, bool $xss_clean = false): mixed
    {
        return $index === null ? $_POST : ($_POST[$index] ?? null);
    }

    public function get(?string $index = null, bool $xss_clean = false): mixed
    {
        return $index === null ? $_GET : ($_GET[$index] ?? null);
    }

    public function post_get(string $index, bool $xss_clean = false): mixed
    {
        return $_POST[$index] ?? $_GET[$index] ?? null;
    }

    public function get_post(string $index, bool $xss_clean = false): mixed
    {
        return $_GET[$index] ?? $_POST[$index] ?? null;
    }

    public function server(string $index, bool $xss_clean = false): mixed
    {
        return $_SERVER[$index] ?? null;
    }

    public function method(bool $upper = false): string
    {
        $method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');

        return $upper ? strtoupper($method) : strtolower($method);
    }

    public function is_ajax_request(): bool
    {
        return strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'xmlhttprequest';
    }

    public function get_request_header(string $index, bool $xss_clean = false): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $index));

        return isset($_SERVER[$key]) ? (string) $_SERVER[$key] : null;
    }

    public function ip_address(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
    }

    public function raw_input_stream(): string
    {
        return (string) file_get_contents('php://input');
    }
}
