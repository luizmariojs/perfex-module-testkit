<?php

declare(strict_types=1);

namespace PerfexTestkit;

use PerfexTestkit\Http\Response;
use PHPUnit\Framework\TestCase;

/**
 * Classe base dos testes de módulos Perfex.
 *
 * - zera todo o estado do kit antes de cada teste;
 * - restaura a requisição simulada ao fim;
 * - falha o teste se o kit registrou violações (consulta não programada, chamada HTTP inesperada,
 *   componente não resolvido), mesmo que o código testado tenha capturado a exceção.
 */
abstract class PerfexTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!Testkit::booted()) {
            self::fail('[perfex-module-testkit] Chame \PerfexTestkit\Testkit::boot($raizDoModulo) no bootstrap dos testes.');
        }

        State::reset();
    }

    protected function tearDown(): void
    {
        State::$request?->restore();
        State::$request = null;

        parent::tearDown();
    }

    protected function assertPostConditions(): void
    {
        parent::assertPostConditions();

        if (State::$violations !== []) {
            $violations        = State::$violations;
            State::$violations = [];
            self::fail("Violações do perfex-module-testkit durante o teste:\n- " . implode("\n- ", $violations));
        }
    }

    // --- options ------------------------------------------------------------------------------------

    protected function assertOptionEquals(string $name, mixed $expected, string $message = ''): void
    {
        self::assertArrayHasKey($name, State::$options, $message ?: "A option \"{$name}\" não foi definida.");
        self::assertSame((string) $expected, State::$options[$name], $message ?: "Valor da option \"{$name}\".");
    }

    protected function assertOptionMissing(string $name): void
    {
        self::assertArrayNotHasKey($name, State::$options, "A option \"{$name}\" não deveria existir.");
    }

    // --- efeitos capturados ---------------------------------------------------------------------------

    /** Verifica que alguma entrada do activity log contém $needle. */
    protected function assertLogged(string $needle): void
    {
        foreach (State::$activity as $entry) {
            if (str_contains($entry['description'], $needle)) {
                self::assertTrue(true);

                return;
            }
        }

        self::fail(sprintf(
            "Nenhuma entrada do activity log contém \"%s\". Registradas:\n%s",
            $needle,
            implode("\n", array_map(static fn ($e) => '  ' . $e['description'], State::$activity)) ?: '  (nenhuma)'
        ));
    }

    protected function assertNotLogged(string $needle): void
    {
        foreach (State::$activity as $entry) {
            self::assertStringNotContainsString($needle, $entry['description'], 'Entrada inesperada no activity log.');
        }
        self::assertTrue(true);
    }

    protected function assertAlert(string $type, ?string $contains = null): void
    {
        foreach (State::$alerts as $alert) {
            if ($alert['type'] === $type && ($contains === null || str_contains($alert['message'], $contains))) {
                self::assertTrue(true);

                return;
            }
        }

        self::fail(sprintf('Nenhum alerta "%s"%s foi definido.', $type, $contains !== null ? " contendo \"{$contains}\"" : ''));
    }

    protected function assertHookRegistered(string $tag, string $type = 'action'): void
    {
        $registered = $type === 'filter' ? State::hooks()->has_filter($tag) : State::hooks()->has_action($tag);
        self::assertTrue($registered, "Nenhum {$type} registrado em \"{$tag}\".");
    }

    // --- HTTP ------------------------------------------------------------------------------------------

    protected function assertStatus(int $expected, Response $response): void
    {
        self::assertSame($expected, $response->status, 'Código de resposta HTTP. Corpo: ' . $response->body);
    }

    protected function assertBody(string $expected, Response $response): void
    {
        self::assertSame($expected, $response->body, 'Corpo da resposta HTTP.');
    }

    protected function assertBodyContains(string $needle, Response $response): void
    {
        self::assertStringContainsString($needle, $response->body, 'Corpo da resposta HTTP.');
    }

    // --- banco ---------------------------------------------------------------------------------------

    /**
     * Verifica que existe na tabela ao menos uma linha com todos os campos informados
     * (comparação frouxa: '1' == 1).
     *
     * @param array<string, mixed> $fields
     */
    protected function assertRowExists(string $table, array $fields): void
    {
        $rows = State::db()->rows($table);
        foreach ($rows as $row) {
            if ($this->rowMatches($row, $fields)) {
                self::assertTrue(true);

                return;
            }
        }

        self::fail(sprintf(
            "Nenhuma linha de %s com %s.\nLinhas atuais:\n%s",
            $table,
            json_encode($fields, JSON_UNESCAPED_UNICODE),
            implode("\n", array_map(static fn ($r) => '  ' . json_encode($r, JSON_UNESCAPED_UNICODE), $rows)) ?: '  (tabela vazia)'
        ));
    }

    /** @param array<string, mixed> $fields */
    protected function assertRowMissing(string $table, array $fields): void
    {
        foreach (State::db()->rows($table) as $row) {
            self::assertFalse($this->rowMatches($row, $fields), sprintf('Linha inesperada em %s: %s', $table, json_encode($row, JSON_UNESCAPED_UNICODE)));
        }
        self::assertTrue(true);
    }

    protected function assertNoWrites(): void
    {
        $writes = State::db()->writes();
        self::assertSame([], $writes, "Esperava nenhuma escrita no banco. Escritas:\n" . implode("\n", array_column($writes, 'sql')));
    }

    /** Verifica que alguma consulta executada casa com $pattern (regex `/.../` ou trecho de texto). */
    protected function assertQueried(string $pattern): void
    {
        $sqls = array_column(State::db()->log(), 'sql');
        foreach ($sqls as $sql) {
            $isRegex = strlen($pattern) > 2 && in_array($pattern[0], ['/', '#', '~'], true) && @preg_match($pattern, '') !== false;
            if ($isRegex ? preg_match($pattern, $sql) === 1 : stripos($sql, $pattern) !== false) {
                self::assertTrue(true);

                return;
            }
        }

        self::fail(sprintf("Nenhuma consulta casa com %s. Executadas:\n%s", $pattern, implode("\n", array_map(static fn ($s) => '  ' . $s, $sqls)) ?: '  (nenhuma)'));
    }

    /**
     * @param array<string, mixed> $row
     * @param array<string, mixed> $fields
     */
    private function rowMatches(array $row, array $fields): bool
    {
        foreach ($fields as $column => $value) {
            if (!array_key_exists($column, $row)) {
                return false;
            }
            if ($value === null ? $row[$column] !== null : (string) $row[$column] !== (string) $value) {
                return false;
            }
        }

        return true;
    }
}
