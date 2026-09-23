<?php

declare(strict_types=1);

namespace PerfexTestkit;

use PerfexTestkit\Database\FakeDatabase;
use PerfexTestkit\Http\RequestSimulator;
use PerfexTestkit\Http\Response;

/**
 * Fachada do kit: inicialização e controle do "mundo" falso do Perfex durante o teste.
 *
 * Uso típico no bootstrap do módulo:
 *
 *     require __DIR__ . '/../vendor/autoload.php';
 *     \PerfexTestkit\Testkit::boot(dirname(__DIR__));
 */
final class Testkit
{
    public const BASE_URL = 'http://localhost/';

    private static bool $booted = false;

    /**
     * Inicializa o runtime falso: define as constantes (BASEPATH, APPPATH, FCPATH, APP_MODULES_PATH,
     * ENVIRONMENT) e carrega os stubs globais. Idempotente para a mesma raiz.
     *
     * @param string               $moduleRoot raiz do módulo em teste (diretório com o arquivo principal do módulo)
     * @param array<string, mixed> $options    base_url (padrão http://localhost/), fcpath (padrão: diretório temporário)
     */
    public static function boot(string $moduleRoot, array $options = []): void
    {
        $root = realpath($moduleRoot);
        if ($root === false || !is_dir($root)) {
            throw new \InvalidArgumentException('[perfex-module-testkit] Raiz do módulo inexistente: ' . $moduleRoot);
        }

        if (self::$booted) {
            if (State::$moduleRoot !== $root) {
                throw new \LogicException('[perfex-module-testkit] Kit já inicializado para ' . State::$moduleRoot);
            }

            return;
        }

        $fcpath = $options['fcpath'] ?? sys_get_temp_dir() . '/perfex-testkit-' . getmypid() . '-' . bin2hex(random_bytes(4));
        $fcpath = rtrim((string) $fcpath, '/') . '/';
        if (!is_dir($fcpath) && !mkdir($fcpath, 0777, true) && !is_dir($fcpath)) {
            throw new \RuntimeException('[perfex-module-testkit] Não foi possível criar FCPATH: ' . $fcpath);
        }

        self::define('ENVIRONMENT', 'testing');
        self::define('FCPATH', $fcpath);
        self::define('BASEPATH', $fcpath . 'system/');
        self::define('APPPATH', $fcpath . 'application/');
        self::define('APP_MODULES_PATH', dirname($root) . '/');

        State::$moduleRoot = $root;
        State::$config     = ['base_url' => rtrim((string) ($options['base_url'] ?? self::BASE_URL), '/') . '/'];

        require_once dirname(__DIR__) . '/stubs/functions.php';
        require_once dirname(__DIR__) . '/stubs/classes.php';

        State::reset();
        self::$booted = true;
    }

    public static function booted(): bool
    {
        return self::$booted;
    }

    public static function moduleRoot(): string
    {
        return (string) State::$moduleRoot;
    }

    // --- options -----------------------------------------------------------------------------------

    /**
     * Sem $value: lê a option (string vazia se inexistente). Com $value: define.
     */
    public static function option(string $name, mixed $value = null): string
    {
        if (func_num_args() >= 2) {
            State::$options[$name] = (string) $value;
        }

        return State::$options[$name] ?? '';
    }

    /** @param array<string, mixed> $options */
    public static function options(array $options): void
    {
        foreach ($options as $name => $value) {
            State::$options[$name] = (string) $value;
        }
    }

    // --- usuário -----------------------------------------------------------------------------------

    /**
     * Define o staff logado. Permissões no formato ['feature' => ['view', 'create', ...]].
     * Admin tem todas as permissões, como no Perfex.
     *
     * @param array<string, list<string>> $permissions
     */
    public static function actingAs(int $staffId, array $permissions = [], bool $admin = false): void
    {
        State::$staffId     = $staffId;
        State::$permissions = $permissions;
        State::$isAdmin     = $admin;
    }

    public static function asGuest(): void
    {
        State::$staffId     = false;
        State::$permissions = [];
        State::$isAdmin     = false;
    }

    // --- instância CI, dublês e banco ---------------------------------------------------------------

    public static function instance(): object
    {
        return State::instance();
    }

    /**
     * Registra um dublê para um componente que não pertence ao módulo (ex.: 'invoices_model').
     * O loader o entrega quando o código pedir esse componente; também fica disponível na instância CI.
     */
    public static function double(string $name, object $double): void
    {
        State::$doubles[$name] = $double;
        $property = strtolower(basename($name));
        State::instance()->{$property} = $double;
    }

    public static function db(): FakeDatabase
    {
        return State::db();
    }

    // --- efeitos capturados ---------------------------------------------------------------------------

    /** @return list<array{description: string, staffid: mixed}> */
    public static function activity(): array
    {
        return State::$activity;
    }

    /** @return list<array{type: string, message: string}> */
    public static function alerts(): array
    {
        return State::$alerts;
    }

    public static function hooks(): Hooks
    {
        return State::hooks();
    }

    public static function fireAction(string $tag, mixed ...$args): void
    {
        State::hooks()->do_action($tag, ...$args);
    }

    public static function applyFilter(string $tag, mixed $value, mixed ...$args): mixed
    {
        return State::hooks()->apply_filters($tag, $value, ...$args);
    }

    /** @var array<string, list<array{type: string, tag: string, callback: mixed, priority: int, accepted_args: int}>> */
    private static array $loadedFiles = [];

    /**
     * Carrega um arquivo do módulo (caminho relativo à raiz), ex.: o arquivo principal que registra hooks.
     *
     * O arquivo é incluído uma única vez por processo (require_once — evita redeclarar funções). Os hooks
     * que ele registrou na primeira carga são capturados e reaplicados nas chamadas seguintes, já que o
     * estado é zerado entre testes. Outros efeitos da carga (options, banco) não são reaplicados.
     */
    public static function load(string $relativePath): void
    {
        $file = realpath(State::$moduleRoot . '/' . ltrim($relativePath, '/'));
        if ($file === false || !is_file($file)) {
            throw new TestkitError('[perfex-module-testkit] Arquivo do módulo não encontrado: ' . State::$moduleRoot . '/' . ltrim($relativePath, '/'));
        }

        if (array_key_exists($file, self::$loadedFiles)) {
            State::hooks()->replay(self::$loadedFiles[$file]);

            return;
        }

        if (in_array($file, get_included_files(), true)) {
            throw new TestkitError('[perfex-module-testkit] ' . $file . ' já foi incluído fora de Testkit::load(); '
                . 'os hooks dele não podem ser capturados. Carregue arquivos que registram hooks sempre com Testkit::load().');
        }

        $before = count(State::hooks()->registrations());
        require_once $file;
        self::$loadedFiles[$file] = array_slice(State::hooks()->registrations(), $before);
    }

    // --- HTTP ------------------------------------------------------------------------------------------

    /**
     * Simula uma requisição até o fim do teste (ou até a próxima chamada).
     *
     * @param array<string, string> $headers
     * @param string|array<mixed>   $body    array é enviado como JSON
     * @param array<string, mixed>  $get
     * @param array<string, mixed>  $post
     */
    public static function request(string $method, array $headers = [], string|array $body = '', array $get = [], array $post = []): RequestSimulator
    {
        State::$request?->restore();
        State::$request = new RequestSimulator($method, $headers, $body, $get, $post);

        return State::$request;
    }

    /**
     * Executa $callable capturando a saída e o código de http_response_code() (200 se não definido).
     */
    public static function call(callable $callable): Response
    {
        http_response_code(200);
        $level = ob_get_level();
        ob_start();

        try {
            $callable();
        } finally {
            $body = '';
            while (ob_get_level() > $level) {
                $body = (string) ob_get_clean() . $body;
            }
        }

        $status = http_response_code();

        return new Response(is_int($status) ? $status : 200, $body);
    }

    private static function define(string $name, string $value): void
    {
        if (!defined($name)) {
            define($name, $value);
        }
    }
}
