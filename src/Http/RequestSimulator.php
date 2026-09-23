<?php

declare(strict_types=1);

namespace PerfexTestkit\Http;

/**
 * Requisição HTTP simulada: preenche $_SERVER (método e headers HTTP_*), $_GET, $_POST, $_REQUEST e o
 * corpo lido por php://input. restore() devolve tudo ao estado anterior.
 *
 * @internal Use Testkit::request().
 */
final class RequestSimulator
{
    /** @var array<string, mixed> */
    private array $server;

    /** @var array<string, mixed> */
    private array $get;

    /** @var array<string, mixed> */
    private array $post;

    /** @var array<string, mixed> */
    private array $request;

    private bool $active = false;

    /**
     * @param array<string, string>  $headers ex.: ['asaas-access-token' => 'abc', 'Content-Type' => 'application/json']
     * @param string|array<mixed>    $body    array é serializado como JSON
     * @param array<string, mixed>   $get
     * @param array<string, mixed>   $post
     */
    public function __construct(
        public readonly string $method,
        public readonly array $headers = [],
        string|array $body = '',
        public readonly array $getParams = [],
        public readonly array $postParams = [],
    ) {
        $this->server  = $_SERVER;
        $this->get     = $_GET;
        $this->post    = $_POST;
        $this->request = $_REQUEST;

        $_SERVER['REQUEST_METHOD'] = strtoupper($method);
        foreach ($headers as $name => $value) {
            $key = strtoupper(str_replace('-', '_', $name));
            if (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'], true)) {
                $_SERVER[$key] = $value;
            }
            $_SERVER['HTTP_' . $key] = $value;
        }

        $_GET     = $getParams;
        $_POST    = $postParams;
        $_REQUEST = array_merge($getParams, $postParams);

        InputStreamWrapper::$body = is_array($body) ? (string) json_encode($body) : $body;
        InputStreamWrapper::register();
        $this->active = true;
    }

    public function restore(): void
    {
        if (!$this->active) {
            return;
        }

        $_SERVER  = $this->server;
        $_GET     = $this->get;
        $_POST    = $this->post;
        $_REQUEST = $this->request;

        InputStreamWrapper::$body = '';
        InputStreamWrapper::unregister();
        $this->active = false;
    }
}
