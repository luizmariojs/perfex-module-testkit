<?php

declare(strict_types=1);

namespace PerfexTestkit;

use PerfexTestkit\Database\FakeDatabase;
use PerfexTestkit\Http\RequestSimulator;

/**
 * Registro único de todo o estado do runtime falso.
 *
 * Os stubs globais (stubs/*.php) apenas leem e gravam aqui. PerfexTestCase::setUp() chama reset(),
 * garantindo que nada vaze de um teste para outro.
 *
 * @internal Use a fachada {@see Testkit} nos testes.
 */
final class State
{
    public static ?string $moduleRoot = null;

    /** @var array<string, mixed> */
    public static array $config = [];

    /** @var array<string, string> */
    public static array $options = [];

    /** Objeto devolvido por get_instance(): CiInstance, ou o controller construído no teste. */
    public static ?object $instance = null;

    public static ?FakeDatabase $db = null;

    public static ?Hooks $hooks = null;

    public static ?RequestSimulator $request = null;

    /** @var list<array{description: string, staffid: mixed}> */
    public static array $activity = [];

    /** @var list<array{type: string, message: string}> */
    public static array $alerts = [];

    /** @var array<string, object> dublês por nome de propriedade (ex.: invoices_model) */
    public static array $doubles = [];

    /** @var int|false */
    public static int|false $staffId = false;

    public static bool $isAdmin = false;

    /** @var array<string, list<string>> feature => capabilities */
    public static array $permissions = [];

    /**
     * Violações detectadas pelo kit (consulta não programada, chamada HTTP inesperada...).
     * Registradas mesmo quando o código testado engole a exceção; PerfexTestCase falha o teste.
     *
     * @var list<string>
     */
    public static array $violations = [];

    public static function reset(): void
    {
        self::$request?->restore();
        self::$request = null;

        self::$options     = [];
        self::$instance    = null;
        self::$db          = new FakeDatabase();
        self::$hooks       = new Hooks();
        self::$activity    = [];
        self::$alerts      = [];
        self::$doubles     = [];
        self::$staffId     = false;
        self::$isAdmin     = false;
        self::$permissions = [];
        self::$violations  = [];
    }

    /**
     * Referência ao objeto da instância CI (get_instance() devolve por referência, como no CI3).
     */
    public static function &instance(): object
    {
        if (self::$instance === null) {
            self::$instance = new CiInstance();
        }

        return self::$instance;
    }

    /**
     * Um controller construído no teste passa a ser a instância CI (no CI3 o controller É o get_instance()).
     * Componentes já carregados na instância anterior são transferidos.
     */
    public static function becomeInstance(object $controller): void
    {
        $previous = self::$instance;

        if ($previous !== null && $previous !== $controller) {
            foreach (get_object_vars($previous) as $name => $value) {
                if (!isset($controller->{$name})) {
                    $controller->{$name} = $value;
                }
            }
        }

        self::$instance = $controller;

        if (!isset($controller->load)) {
            $controller->load = new Loader();
        }
        if (!isset($controller->db)) {
            $controller->db = self::db();
        }
    }

    public static function db(): FakeDatabase
    {
        if (self::$db === null) {
            self::$db = new FakeDatabase();
        }

        return self::$db;
    }

    public static function hooks(): Hooks
    {
        if (self::$hooks === null) {
            self::$hooks = new Hooks();
        }

        return self::$hooks;
    }

    public static function violation(string $message): void
    {
        self::$violations[] = $message;
    }
}
